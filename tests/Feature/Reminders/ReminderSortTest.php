<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use App\Services\Reminders\ReminderSorting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderSortTest extends TestCase
{
    use RefreshDatabase;

    private function make(User $user, string $title, array $attributes = []): Reminder
    {
        $reminder = Reminder::factory()->create($attributes + ['created_by' => $user->id, 'title' => $title]);
        app(ReminderEngine::class)->sync($reminder);

        // Keep recurring reminders out of the "Due now" list, which has its own order.
        if (! isset($attributes['due_at'])) {
            $reminder->recipients()->update(['next_notify_at' => now()->addDay()]);
        }

        return $reminder;
    }

    /**
     * The titles in the order the page lists them, ignoring the confirmation texts that repeat a title.
     *
     * @return array<int, string>
     */
    private function titles(string $html): array
    {
        preg_match_all('#<h3 class="font-medium[^"]*">([^<]+)</h3>#', $html, $matches);

        return array_map('trim', $matches[1]);
    }

    public function test_reminders_are_sorted_by_next_reminder_by_default(): void
    {
        $user = User::factory()->create();
        $this->make($user, 'Later', ['due_at' => now()->addDays(5)]);
        $this->make($user, 'Sooner', ['due_at' => now()->addDays(2)]);

        $this->assertSame(['Sooner', 'Later'], $this->titles($this->actingAs($user)->get('/reminders')->getContent()));
    }

    public function test_every_sort_option_works(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->managedBy($user)->create();
        $this->make($user, 'Bravo', ['due_at' => now()->addDays(3), 'created_at' => now()->subDays(1), 'updated_at' => now()->subHours(5)]);
        $this->make($user, 'Alpha', ['due_at' => now()->addDays(9), 'created_at' => now()->subDays(3), 'updated_at' => now()->subHours(1), 'team_id' => $team->id]);
        $this->make($user, 'Charlie', ['due_at' => now()->addDays(20), 'created_at' => now()->subDays(2), 'updated_at' => now()->subHours(9)]);
        $this->make($user, 'Delta', ['type' => Reminder::REPEATING, 'due_at' => null, 'interval_minutes' => 60, 'created_at' => now()->subDays(4), 'updated_at' => now()->subHours(12)]);

        $expected = [
            'title' => ['Alpha', 'Bravo', 'Charlie', 'Delta'],
            'title_desc' => ['Delta', 'Charlie', 'Bravo', 'Alpha'],
            'newest' => ['Bravo', 'Charlie', 'Alpha', 'Delta'],
            'oldest' => ['Delta', 'Alpha', 'Charlie', 'Bravo'],
            'deadline' => ['Bravo', 'Alpha', 'Charlie', 'Delta'],
            'changed' => ['Alpha', 'Bravo', 'Charlie', 'Delta'],
        ];

        foreach ($expected as $sort => $titles) {
            $this->assertSame($titles, $this->titles($this->actingAs($user)->get('/reminders?sort='.$sort)->getContent()), "sort={$sort}");
        }

        $byTeam = $this->titles($this->actingAs($user)->get('/reminders?sort=team')->getContent());
        $this->assertSame('Alpha', end($byTeam), 'team reminders come after personal ones');

        $byType = $this->titles($this->actingAs($user)->get('/reminders?sort=type')->getContent());
        $this->assertSame('Delta', end($byType), 'recurring reminders come after deadlines');
    }

    public function test_the_choice_is_remembered_in_a_cookie(): void
    {
        $user = User::factory()->create();
        $this->make($user, 'Bravo', ['due_at' => now()->addDays(1)]);
        $this->make($user, 'Alpha', ['due_at' => now()->addDays(8)]);

        $response = $this->actingAs($user)->get('/reminders?sort=title')->assertCookie(ReminderSorting::COOKIE);
        $this->assertSame(['Alpha', 'Bravo'], $this->titles($response->getContent()));

        $cookie = $response->getCookie(ReminderSorting::COOKIE, false)->getValue();
        $again = $this->actingAs($user)->withUnencryptedCookie(ReminderSorting::COOKIE, $cookie)->get('/reminders');
        $this->assertSame(['Alpha', 'Bravo'], $this->titles($again->getContent()));
    }

    public function test_unknown_sort_values_fall_back_to_the_default(): void
    {
        $user = User::factory()->create();
        $this->make($user, 'Only one', ['due_at' => now()->addDays(1)]);

        $this->actingAs($user)->get('/reminders?sort=nonsense&folders=%00&done[]=x')->assertOk()->assertSee('Only one');
    }

    public function test_the_done_list_can_be_sorted_by_title(): void
    {
        $user = User::factory()->create();
        $first = $this->make($user, 'Zulu', ['due_at' => now()->addDays(1)]);
        $second = $this->make($user, 'Alpha', ['due_at' => now()->addDays(1)]);
        $first->forceFill(['completed_at' => now()])->save();
        $second->forceFill(['completed_at' => now()->subHour()])->save();

        $this->assertSame(['Zulu', 'Alpha'], $this->titles($this->actingAs($user)->get('/reminders?done=recent')->getContent()));
        $this->assertSame(['Alpha', 'Zulu'], $this->titles($this->actingAs($user)->get('/reminders?done=title')->getContent()));
    }

    public function test_folders_can_be_sorted_by_name_due_count_and_own_order(): void
    {
        $user = User::factory()->create();
        $beta = $user->folders()->create(['name' => 'Beta', 'position' => 1]);
        $alpha = $user->folders()->create(['name' => 'Alpha', 'position' => 2]);
        $gamma = $user->folders()->create(['name' => 'Gamma', 'position' => 3]);
        $user->fileReminder($this->make($user, 'In beta one', ['due_at' => now()->addDays(2)]), $beta->id);
        $user->fileReminder($this->make($user, 'In beta two', ['due_at' => now()->addDays(3)]), $beta->id);
        $due = $this->make($user, 'Due gamma', ['due_at' => now()->addDays(2)]);
        $user->fileReminder($due, $gamma->id);
        $due->recipients()->update(['next_notify_at' => now()->subMinute(), 'pending_since' => now()->subMinute()]);

        $order = fn (string $sort) => $this->folderNames($this->actingAs($user)->get('/reminders?folders='.$sort)->getContent());

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $order('name'));
        $this->assertSame(['Gamma', 'Alpha', 'Beta'], $order('due'));
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], $order('count'));
        $this->assertSame(['Beta', 'Alpha', 'Gamma'], $order('manual'));
    }

    public function test_folders_can_be_moved_up_and_down_in_their_own_order(): void
    {
        $user = User::factory()->create();
        $one = $user->folders()->create(['name' => 'One', 'position' => 1]);
        $two = $user->folders()->create(['name' => 'Two', 'position' => 2]);
        $three = $user->folders()->create(['name' => 'Three', 'position' => 3]);

        $names = fn () => $user->folders()->orderBy('position')->pluck('name')->all();
        $this->actingAs($user)->post(route('folders.reorder', $three), ['direction' => 'up']);
        $this->assertSame(['One', 'Three', 'Two'], $names());

        $this->actingAs($user)->post(route('folders.reorder', $one), ['direction' => 'down']);
        $this->assertSame(['Three', 'One', 'Two'], $names());

        $this->actingAs($user)->post(route('folders.reorder', $three), ['direction' => 'up']);
        $this->assertSame(['Three', 'One', 'Two'], $names());

        $this->actingAs($user)->post(route('folders.reorder', $two), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_someone_elses_folder_cannot_be_moved(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $folder = $owner->folders()->create(['name' => 'Private']);

        $this->actingAs($other)->post(route('folders.reorder', $folder), ['direction' => 'up'])->assertNotFound();
    }

    public function test_the_move_buttons_only_show_in_own_order(): void
    {
        $user = User::factory()->create();
        $user->folders()->create(['name' => 'One']);
        $user->folders()->create(['name' => 'Two']);

        $this->actingAs($user)->get('/reminders?folders=name')->assertDontSee('Move up');
        $this->actingAs($user)->get('/reminders?folders=manual')->assertSee('Move up')->assertSee('Move down');
    }

    /**
     * @return array<int, string>
     */
    private function folderNames(string $html): array
    {
        preg_match_all('#<span class="min-w-0 flex-1 truncate font-medium">([^<]+)</span>#', $html, $matches);

        return array_map('trim', $matches[1]);
    }
}

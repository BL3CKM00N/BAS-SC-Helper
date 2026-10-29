<?php

namespace Tests\Feature\Reminders;

use App\Models\Folder;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attributes = []): Reminder
    {
        $reminder = Reminder::factory()->create($attributes);
        app(ReminderEngine::class)->sync($reminder);

        return $reminder;
    }

    public function test_a_person_can_add_rename_and_delete_a_folder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/folders', ['name' => '  Fleet  '])->assertRedirect();
        $folder = $user->folders()->firstOrFail();
        $this->assertSame('Fleet', $folder->name);

        $this->actingAs($user)->patch(route('folders.update', $folder), ['name' => 'Trucks'])->assertRedirect();
        $this->assertSame('Trucks', $folder->fresh()->name);

        $this->actingAs($user)->delete(route('folders.destroy', $folder))->assertRedirect();
        $this->assertSame(0, Folder::query()->count());
    }

    public function test_folder_names_are_required_and_unique_per_person(): void
    {
        [$user, $other] = User::factory()->count(2)->create();
        $user->folders()->create(['name' => 'Fleet']);

        $this->actingAs($user)->post('/folders', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($user)->post('/folders', ['name' => 'Fleet'])->assertSessionHasErrors('name');
        $this->actingAs($other)->post('/folders', ['name' => 'Fleet'])->assertSessionHasNoErrors();
    }

    public function test_folders_are_private_to_their_owner(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $folder = $owner->folders()->create(['name' => 'Private']);

        $this->actingAs($other)->patch(route('folders.update', $folder), ['name' => 'Mine now'])->assertNotFound();
        $this->actingAs($other)->delete(route('folders.destroy', $folder))->assertNotFound();
        $this->actingAs($other)->get('/reminders')->assertDontSee('Private');
        $this->assertSame('Private', $folder->fresh()->name);
    }

    public function test_deleting_a_folder_keeps_its_reminders(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Fleet']);
        $reminder = $this->make(['created_by' => $user->id, 'title' => 'Check tyres']);
        $user->fileReminder($reminder, $folder->id);

        $this->actingAs($user)->delete(route('folders.destroy', $folder));

        $this->assertModelExists($reminder);
        $this->actingAs($user)->get('/reminders')->assertSee('Check tyres');
    }

    public function test_a_filed_reminder_is_listed_in_its_folder_and_not_below(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Fleet']);
        $filed = $this->make(['created_by' => $user->id, 'title' => 'Check tyres']);
        $this->make(['created_by' => $user->id, 'title' => 'Loose chore']);
        $user->fileReminder($filed, $folder->id);

        $response = $this->actingAs($user)->get('/reminders')->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '>Check tyres</h3>'));
        $this->assertSame(1, substr_count($html, '>Loose chore</h3>'));
        // The folder comes closed: the page only marks the details element, never `open`.
        $this->assertMatchesRegularExpression('/<details class="group[^>]*data-folder="'.$folder->id.'"[^>]*>/', $html);
        $this->assertLessThan(strpos($html, '>Loose chore</h3>'), strpos($html, '>Check tyres</h3>'));
    }

    public function test_a_reminder_can_be_moved_between_folders_and_out(): void
    {
        $user = User::factory()->create();
        [$one, $two] = [$user->folders()->create(['name' => 'One']), $user->folders()->create(['name' => 'Two'])];
        $reminder = $this->make(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('reminders.folder', $reminder), ['folder_id' => $one->id]);
        $this->assertSame([$one->id], $reminder->folders()->pluck('folders.id')->all());

        $this->actingAs($user)->post(route('reminders.folder', $reminder), ['folder_id' => $two->id]);
        $this->assertSame([$two->id], $reminder->folders()->pluck('folders.id')->all());

        $this->actingAs($user)->post(route('reminders.folder', $reminder), ['folder_id' => '']);
        $this->assertSame(0, $reminder->folders()->count());
    }

    public function test_someone_elses_folder_cannot_be_used(): void
    {
        [$user, $other] = User::factory()->count(2)->create();
        $foreign = $other->folders()->create(['name' => 'Theirs']);
        $reminder = $this->make(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('reminders.folder', $reminder), ['folder_id' => $foreign->id])->assertSessionHasErrors('folder_id');
        $this->assertSame(0, $reminder->folders()->count());
    }

    public function test_a_reminder_cannot_be_filed_without_access_to_it(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $folder = $other->folders()->create(['name' => 'Mine']);
        $reminder = $this->make(['created_by' => $owner->id]);

        $this->actingAs($other)->post(route('reminders.folder', $reminder), ['folder_id' => $folder->id])->assertForbidden();
    }

    public function test_team_reminders_can_be_filed_privately_by_every_member(): void
    {
        [$manager, $member] = User::factory()->count(2)->create();
        $team = Team::factory()->managedBy($manager)->create();
        $team->users()->attach($member);
        $reminder = $this->make(['created_by' => $manager->id, 'team_id' => $team->id, 'title' => 'Team chore']);
        $mine = $manager->folders()->create(['name' => 'Manager folder']);

        $this->actingAs($manager)->post(route('reminders.folder', $reminder), ['folder_id' => $mine->id])->assertRedirect();
        $this->actingAs($member)->post(route('reminders.folder', $reminder), ['folder_id' => ''])->assertRedirect();

        $this->assertSame([$mine->id], $reminder->folders()->pluck('folders.id')->all());
        $this->actingAs($member)->get('/reminders')->assertSee('Team chore')->assertDontSee('Manager folder');
    }

    public function test_the_reminder_form_picks_a_folder_when_creating_and_editing(): void
    {
        $user = User::factory()->create();
        [$one, $two] = [$user->folders()->create(['name' => 'One']), $user->folders()->create(['name' => 'Two'])];

        $this->actingAs($user)->post('/reminders', [
            'title' => 'Filed on creation', 'type' => 'repeating', 'frequency' => 'interval', 'interval_minutes' => 60, 'folder_id' => $one->id,
        ])->assertSessionHasNoErrors();
        $reminder = Reminder::query()->where('title', 'Filed on creation')->firstOrFail();
        $this->assertSame([$one->id], $reminder->folders()->pluck('folders.id')->all());

        $this->actingAs($user)->get(route('reminders.edit', $reminder))->assertSee('Folder (optional)');

        $this->actingAs($user)->patch(route('reminders.update', $reminder), [
            'title' => 'Filed on creation', 'type' => 'repeating', 'frequency' => 'interval', 'interval_minutes' => 60, 'folder_id' => $two->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame([$two->id], $reminder->folders()->pluck('folders.id')->all());
    }

    public function test_due_reminders_stay_in_the_due_list_when_filed(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Fleet']);
        $reminder = $this->make(['created_by' => $user->id, 'title' => 'Urgent thing']);
        $user->fileReminder($reminder, $folder->id);
        $reminder->recipients()->update(['next_notify_at' => now()->subMinute(), 'pending_since' => now()->subMinute()]);

        $html = $this->actingAs($user)->get('/reminders')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '>Urgent thing</h3>'));
        $this->assertStringContainsString('1 due now', $html);
    }

    public function test_the_page_marks_reminders_as_draggable_and_folders_as_drop_targets(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Fleet']);
        $this->make(['created_by' => $user->id, 'title' => 'Loose chore']);

        $html = $this->actingAs($user)->get('/reminders')->assertOk()->getContent();

        $this->assertStringContainsString('>New folder</summary>', $html);
        $this->assertStringContainsString('data-reminder-card', $html);
        $this->assertStringContainsString('data-drop-folder="'.$folder->id.'"', $html);
        $this->assertStringContainsString('data-drop-folder=""', $html);
    }
}

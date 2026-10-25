<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Admin\AccountAdmin;
use App\Services\Reminders\ReminderEngine;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): int
    {
        return Role::query()->firstOrCreate(['name' => $name])->id;
    }

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return User::factory()->create(['role_id' => $this->role(Role::ADMIN)]);
    }

    public function test_disabled_people_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1', 'disabled_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabling_signs_the_person_out_on_their_next_request(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        app(AccountAdmin::class)->disable($user, $admin);

        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertTrue(AuditLog::query()->where('action', 'user.disabled')->exists());
    }

    public function test_a_disabled_admin_cannot_open_the_panel(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        app(AccountAdmin::class)->disable($other, $admin);

        $this->assertFalse($other->fresh()->canAccessPanel(Filament::getCurrentPanel()));
    }

    public function test_the_last_admin_is_protected(): void
    {
        $only = $this->admin();
        $actor = User::factory()->create();
        $service = app(AccountAdmin::class);

        $this->assertTrue($service->isLastAdmin($only));

        foreach (['disable', 'anonymise'] as $method) {
            try {
                $service->{$method}($only, $actor);
                $this->fail("{$method} should have been refused");
            } catch (InvalidArgumentException) {
                $this->assertNull($only->fresh()->disabled_at);
            }
        }

        $second = $this->admin();
        $this->assertTrue(Gate::forUser($second)->allows('delete', $only));
        $this->assertFalse(Gate::forUser($only)->allows('delete', $second) && $service->isLastAdmin($second));
    }

    public function test_you_cannot_disable_yourself(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->expectException(InvalidArgumentException::class);
        app(AccountAdmin::class)->disable($admin, $admin);
    }

    public function test_reset_password_gives_a_temporary_password_and_signs_out(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => str_repeat('a', 40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $temporary = app(AccountAdmin::class)->resetPassword($user, $admin);

        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(password_verify($temporary, $user->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_anonymise_wipes_the_person_but_keeps_team_reminders(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->create(['role_id' => $this->role(Role::TEAM_MANAGER)]);
        $team = $manager->managedTeam()->first();
        $member = User::factory()->create(['name' => 'Pat Example']);
        $team->users()->attach($member);
        Reminder::factory()->create(['created_by' => $member->id, 'assignee_id' => $member->id, 'team_id' => null]);

        app(AccountAdmin::class)->anonymise($member, $admin);

        $member->refresh();
        $this->assertSame('Former user', $member->name);
        $this->assertStringEndsWith('@removed.invalid', $member->email);
        $this->assertTrue($member->isDisabled());
        $this->assertSame(0, $member->teams()->count());
        $this->assertSame(0, Reminder::query()->where('created_by', $member->id)->count());
        $this->assertTrue(Team::query()->whereKey($team->id)->exists());
    }

    public function test_last_sign_in_is_recorded(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])->assertRedirect();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_reminders_are_not_sent_to_disabled_people(): void
    {
        $user = User::factory()->create(['disabled_at' => now()]);
        $at = CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Amsterdam')->utc();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $at->addHour()]);
        app(ReminderEngine::class)->sync($reminder, $at);

        $this->assertSame(0, app(ReminderEngine::class)->sendDue($at));
    }
}

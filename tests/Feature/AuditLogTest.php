<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AppSettings;
use App\Services\Admin\Audit;
use App\Services\Teams\TeamManagement;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_stores_the_actor_and_keeps_the_name_after_deletion(): void
    {
        $admin = User::factory()->create();
        $entry = Audit::record('test.thing', 'Did a thing', null, ['a' => 1], $admin);

        $this->assertSame($admin->name, $entry->actor_name);
        $admin->delete();
        $this->assertNull($entry->fresh()->actor_id);
        $this->assertSame($admin->name, $entry->fresh()->actor_name);
    }

    public function test_role_changes_and_team_changes_are_logged(): void
    {
        $user = User::factory()->create();
        $managerRole = Role::query()->where('name', Role::TEAM_MANAGER)->first() ?? Role::factory()->create(['name' => Role::TEAM_MANAGER]);
        $user->update(['role_id' => $managerRole->id]);

        $this->assertTrue(AuditLog::query()->where('action', 'user.role_changed')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'team.created')->exists());

        $team = $user->managedTeam()->first();
        $member = User::factory()->create();
        app(TeamManagement::class)->addMember($team, $member);
        app(TeamManagement::class)->removeMember($team, $member);

        $this->assertTrue(AuditLog::query()->where('action', 'team.member_added')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'team.member_removed')->exists());
    }

    public function test_old_entries_are_prunable(): void
    {
        $old = Audit::record('x.old', 'Old');
        $old->forceFill(['created_at' => now()->subDays(AuditLog::KEEP_DAYS + 1)])->save();
        Audit::record('x.new', 'New');

        (new AuditLog)->prunable()->get()->each->delete();

        $this->assertSame(['x.new'], AuditLog::query()->where('action', 'like', 'x.%')->pluck('action')->all());
    }

    public function test_settings_are_stored_and_cached(): void
    {
        $settings = app(AppSettings::class);
        $this->assertSame('fallback', $settings->get('nothing', 'fallback'));

        $settings->set('work.start', '09:00');
        $settings->setMany(['a' => ['x' => 1]]);

        $this->assertSame('09:00', $settings->get('work.start'));
        $this->assertSame(['x' => 1], $settings->get('a'));

        $settings->forget('work.start');
        $this->assertNull($settings->get('work.start'));
    }

    public function test_admins_can_read_the_log_in_the_panel(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create(['role_id' => Role::query()->where('name', Role::ADMIN)->value('id')]);
        Audit::record('user.created', 'Created the account of Pat', null, [], $admin);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->assertSee('Created the account of Pat');
    }
}

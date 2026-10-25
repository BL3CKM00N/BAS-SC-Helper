<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function managerRoleId(): int
    {
        return Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER])->id;
    }

    // ------------------------------------------------------------ getting and losing the role

    public function test_giving_someone_the_manager_role_creates_their_team(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);

        $user->update(['role_id' => $this->managerRoleId()]);

        $team = Team::query()->firstOrFail();
        $this->assertSame("Anna's team", $team->name);
        $this->assertTrue($team->isManagedBy($user));
        $this->assertTrue($team->hasMember($user));
        $this->assertTrue($user->fresh()->isTeamManager());
    }

    public function test_creating_a_user_that_is_a_manager_creates_their_team_too(): void
    {
        $user = User::factory()->create(['name' => 'Bram', 'role_id' => $this->managerRoleId()]);

        $this->assertTrue(Team::query()->firstOrFail()->isManagedBy($user));
    }

    public function test_two_managers_with_the_same_name_get_different_team_names(): void
    {
        User::factory()->create(['name' => 'Anna', 'role_id' => $this->managerRoleId()]);
        User::factory()->create(['name' => 'Anna', 'role_id' => $this->managerRoleId()]);

        $this->assertEqualsCanonicalizing(["Anna's team", "Anna's team 2"], Team::query()->pluck('name')->all());
    }

    public function test_changing_the_role_again_does_not_create_a_second_team(): void
    {
        $user = User::factory()->create(['role_id' => $this->managerRoleId()]);

        $user->update(['name' => 'Renamed']);
        $user->update(['role_id' => $this->managerRoleId()]);

        $this->assertSame(1, Team::query()->count());
    }

    public function test_losing_the_role_makes_the_team_open_and_keeps_the_former_manager_as_member(): void
    {
        $manager = User::factory()->create(['role_id' => $this->managerRoleId()]);
        $member = User::factory()->create();
        $team = $manager->managedTeam()->firstOrFail();
        $team->users()->attach($member);

        $manager->update(['role_id' => Role::query()->where('name', Role::USER)->value('id')]);

        $team->refresh();
        $this->assertTrue($team->isOpen());
        $this->assertNull($team->manager_id);
        $this->assertTrue($team->hasMember($manager));
        $this->assertTrue($team->hasMember($member));
    }

    public function test_deleting_a_manager_opens_the_team(): void
    {
        $manager = User::factory()->create(['role_id' => $this->managerRoleId()]);
        $team = $manager->managedTeam()->firstOrFail();
        $team->users()->attach(User::factory()->create());

        $manager->delete();

        $this->assertTrue($team->fresh()->isOpen());
    }

    public function test_a_manager_of_an_open_team_with_nobody_else_leaves_an_empty_team_that_is_removed(): void
    {
        $manager = User::factory()->create(['role_id' => $this->managerRoleId()]);
        $team = $manager->managedTeam()->firstOrFail();

        $manager->delete();

        $this->assertModelMissing($team);
    }

    // ------------------------------------------------------------ assigning a manager

    public function test_an_admin_can_assign_a_manager_to_an_open_team(): void
    {
        $team = Team::factory()->create();
        $person = User::factory()->create();

        app(TeamManagement::class)->assignManager($team, $person);

        $this->assertTrue($team->fresh()->isManagedBy($person));
        $this->assertTrue($team->hasMember($person));
        $this->assertTrue($person->fresh()->isTeamManager());
        $this->assertSame(1, Team::query()->count(), 'taking over a team must not create another one');
    }

    public function test_an_admin_keeps_the_admin_role_when_managing_a_team(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->admin()->create();

        app(TeamManagement::class)->assignManager($team, $admin);

        $this->assertTrue($team->fresh()->isManagedBy($admin));
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_a_team_that_already_has_a_manager_cannot_get_another(): void
    {
        $team = Team::factory()->managedBy(User::factory()->create())->create();

        $this->expectException(InvalidArgumentException::class);

        app(TeamManagement::class)->assignManager($team, User::factory()->create());
    }

    public function test_a_manager_cannot_manage_two_teams(): void
    {
        $busy = User::factory()->create(['role_id' => $this->managerRoleId()]);

        $this->expectException(InvalidArgumentException::class);

        app(TeamManagement::class)->assignManager(Team::factory()->create(), $busy);
    }

    // ------------------------------------------------------------ members and pruning

    public function test_the_manager_cannot_be_removed_from_their_own_team(): void
    {
        $manager = User::factory()->create(['role_id' => $this->managerRoleId()]);

        $this->expectException(InvalidArgumentException::class);

        app(TeamManagement::class)->removeMember($manager->managedTeam()->firstOrFail(), $manager);
    }

    public function test_an_open_team_is_removed_when_its_last_member_moves_away(): void
    {
        $open = Team::factory()->create();
        $other = Team::factory()->managedBy(User::factory()->create())->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $open->users()->attach([$a->id, $b->id]);
        $service = app(TeamManagement::class);

        $service->addMember($other, $a);
        $service->removeMember($open, $a);
        $this->assertModelExists($open);

        $service->addMember($other, $b);
        $service->removeMember($open, $b);

        $this->assertModelMissing($open);
        $this->assertTrue($other->hasMember($a));
        $this->assertTrue($other->hasMember($b));
    }

    public function test_a_managed_team_is_never_removed_for_being_small(): void
    {
        $manager = User::factory()->create(['role_id' => $this->managerRoleId()]);
        $member = User::factory()->create();
        $team = $manager->managedTeam()->firstOrFail();
        $team->users()->attach($member);

        app(TeamManagement::class)->removeMember($team, $member);

        $this->assertModelExists($team);
    }

    public function test_removing_a_team_removes_its_reminders(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $team->users()->attach($user);
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'team_id' => $team->id]);

        app(TeamManagement::class)->removeMember($team, $user);

        $this->assertModelMissing($team);
        $this->assertModelMissing($reminder);
    }

    public function test_the_prune_command_removes_abandoned_open_teams_only(): void
    {
        $abandoned = Team::factory()->create();
        $withMember = Team::factory()->create();
        $withMember->users()->attach(User::factory()->create());
        $managed = Team::factory()->managedBy(User::factory()->create())->create();

        $this->artisan('teams:prune')->expectsOutput('Removed 1 empty team(s).')->assertSuccessful();

        $this->assertModelMissing($abandoned);
        $this->assertModelExists($withMember);
        $this->assertModelExists($managed);
    }

    public function test_the_prune_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('teams:prune')->assertSuccessful();
    }
}

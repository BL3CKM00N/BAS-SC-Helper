<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private function team(?User $manager = null, ?User $member = null): Team
    {
        $team = Team::factory()->create();

        if ($manager) {
            $team->users()->attach($manager, ['role' => Team::MANAGER]);
        }

        if ($member) {
            $team->users()->attach($member, ['role' => Team::MEMBER]);
        }

        return $team;
    }

    public function test_guests_cannot_see_teams(): void
    {
        $this->get('/teams')->assertRedirect('/');
    }

    public function test_a_user_only_sees_their_own_teams(): void
    {
        $user = User::factory()->create();
        $mine = $this->team(member: $user);
        $other = $this->team(manager: User::factory()->create());

        $this->actingAs($user)->get('/teams')
            ->assertOk()
            ->assertSee($mine->name)
            ->assertDontSee($other->name);
    }

    public function test_only_members_can_open_a_team(): void
    {
        $team = $this->team(manager: User::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('teams.show', $team))->assertForbidden();
        $this->actingAs($team->users()->first())->get(route('teams.show', $team))->assertOk();
    }

    public function test_members_see_no_management_forms(): void
    {
        $member = User::factory()->create();
        $team = $this->team(manager: User::factory()->create(), member: $member);

        $this->actingAs($member)->get(route('teams.show', $team))
            ->assertOk()
            ->assertDontSee('Add a member')
            ->assertDontSee('Team settings');
    }

    public function test_managers_can_add_change_and_remove_members(): void
    {
        $manager = User::factory()->create();
        $newcomer = User::factory()->create(['email' => 'new@example.com']);
        $team = $this->team(manager: $manager);

        $this->actingAs($manager)->post(route('teams.members.add', $team), ['email' => 'new@example.com', 'role' => 'member'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($team->hasMember($newcomer));

        $this->actingAs($manager)->patch(route('teams.members.update', [$team, $newcomer]), ['role' => 'manager']);
        $this->assertTrue($team->isManagedBy($newcomer));

        $this->actingAs($manager)->delete(route('teams.members.remove', [$team, $newcomer]));
        $this->assertFalse($team->hasMember($newcomer));
    }

    public function test_members_cannot_manage_the_team(): void
    {
        $member = User::factory()->create();
        $victim = User::factory()->create();
        $team = $this->team(manager: User::factory()->create(), member: $member);
        $team->users()->attach($victim, ['role' => Team::MEMBER]);

        $this->actingAs($member)->post(route('teams.members.add', $team), ['email' => User::factory()->create()->email, 'role' => 'member'])->assertForbidden();
        $this->actingAs($member)->delete(route('teams.members.remove', [$team, $victim]))->assertForbidden();
        $this->actingAs($member)->patch(route('teams.members.update', [$team, $member]), ['role' => 'manager'])->assertForbidden();
        $this->actingAs($member)->patch(route('teams.update', $team), ['members_can_create' => 1])->assertForbidden();

        $this->assertTrue($team->hasMember($victim));
        $this->assertFalse($team->isManagedBy($member));
    }

    public function test_managers_of_another_team_cannot_manage_this_one(): void
    {
        $team = $this->team(manager: User::factory()->create());
        $outsiderManager = User::factory()->create();
        $this->team(manager: $outsiderManager);

        $this->actingAs($outsiderManager)->post(route('teams.members.add', $team), ['email' => $outsiderManager->email, 'role' => 'member'])->assertForbidden();
    }

    public function test_a_team_always_keeps_a_manager(): void
    {
        $manager = User::factory()->create();
        $team = $this->team(manager: $manager);

        $this->actingAs($manager)->patch(route('teams.members.update', [$team, $manager]), ['role' => 'member'])->assertSessionHasErrors('role');
        $this->actingAs($manager)->delete(route('teams.members.remove', [$team, $manager]))->assertSessionHasErrors('role');

        $this->assertTrue($team->isManagedBy($manager));
    }

    public function test_adding_an_unknown_or_existing_member_fails(): void
    {
        $manager = User::factory()->create();
        $team = $this->team(manager: $manager);

        $this->actingAs($manager)->post(route('teams.members.add', $team), ['email' => 'nobody@example.com', 'role' => 'member'])->assertSessionHasErrors('email');
        $this->actingAs($manager)->post(route('teams.members.add', $team), ['email' => $manager->email, 'role' => 'member'])->assertSessionHasErrors('email');
    }

    public function test_managers_can_toggle_whether_members_may_create_reminders(): void
    {
        $manager = User::factory()->create();
        $team = $this->team(manager: $manager);

        $this->actingAs($manager)->patch(route('teams.update', $team), ['members_can_create' => 1]);
        $this->assertTrue($team->fresh()->members_can_create);

        $this->actingAs($manager)->patch(route('teams.update', $team), []);
        $this->assertFalse($team->fresh()->members_can_create);
    }

    public function test_admins_manage_teams_in_the_panel_but_normal_users_cannot(): void
    {
        $team = $this->team(manager: User::factory()->create());

        $this->actingAs(User::factory()->admin()->create())->get('/admin/teams')->assertOk()->assertSee($team->name);
        $this->actingAs(User::factory()->create())->get('/admin/teams')->assertForbidden();
    }
}

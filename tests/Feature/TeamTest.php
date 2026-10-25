<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private function team(?User $manager = null, ?User $member = null): Team
    {
        $team = $manager ? Team::factory()->managedBy($manager)->create() : Team::factory()->create();

        if ($member) {
            $team->users()->attach($member);
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

    public function test_a_user_can_be_in_several_teams(): void
    {
        $user = User::factory()->create();
        $a = $this->team(manager: User::factory()->create(), member: $user);
        $b = $this->team(manager: User::factory()->create(), member: $user);

        $this->actingAs($user)->get('/teams')->assertSee($a->name)->assertSee($b->name);
    }

    public function test_the_team_page_shows_who_manages_it_and_open_teams(): void
    {
        $manager = User::factory()->create(['name' => 'Marian Manager']);
        $member = User::factory()->create();
        $managed = $this->team(manager: $manager, member: $member);
        $open = $this->team(member: $member);

        $this->actingAs($member)->get(route('teams.show', $managed))->assertOk()->assertSee('Manager: Marian Manager');
        $this->actingAs($member)->get(route('teams.show', $open))->assertOk()->assertSee('no manager');
        $this->actingAs($member)->get('/teams')->assertSee('No manager yet');
    }

    public function test_only_members_can_open_a_team(): void
    {
        $manager = User::factory()->create();
        $team = $this->team(manager: $manager);

        $this->actingAs(User::factory()->create())->get(route('teams.show', $team))->assertForbidden();
        $this->actingAs($manager)->get(route('teams.show', $team))->assertOk();
    }

    public function test_members_see_no_settings_form(): void
    {
        $member = User::factory()->create();
        $team = $this->team(manager: User::factory()->create(), member: $member);

        $this->actingAs($member)->get(route('teams.show', $team))
            ->assertOk()
            ->assertDontSee('Team settings');
    }

    public function test_the_manager_can_rename_the_team_and_toggle_member_creation(): void
    {
        $manager = User::factory()->create();
        $team = $this->team(manager: $manager);

        $this->actingAs($manager)->patch(route('teams.update', $team), ['name' => 'Night shift', 'members_can_create' => 1])->assertSessionHasNoErrors();

        $this->assertSame('Night shift', $team->fresh()->name);
        $this->assertTrue($team->fresh()->members_can_create);

        $this->actingAs($manager)->patch(route('teams.update', $team), ['name' => 'Night shift']);
        $this->assertFalse($team->fresh()->members_can_create);
    }

    public function test_a_team_name_must_be_unique(): void
    {
        $manager = User::factory()->create();
        $team = $this->team(manager: $manager);
        $other = Team::factory()->create(['name' => 'Taken']);

        $this->actingAs($manager)->patch(route('teams.update', $team), ['name' => 'Taken'])->assertSessionHasErrors('name');
        $this->assertNotSame($other->name, $team->fresh()->name);
    }

    public function test_members_and_outsiders_cannot_change_the_team(): void
    {
        $member = User::factory()->create();
        $team = $this->team(manager: User::factory()->create(), member: $member);
        $outsiderManager = User::factory()->create();
        $this->team(manager: $outsiderManager);

        $this->actingAs($member)->patch(route('teams.update', $team), ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($outsiderManager)->patch(route('teams.update', $team), ['name' => 'Hacked'])->assertForbidden();
        $this->assertNotSame('Hacked', $team->fresh()->name);
    }

    public function test_admins_manage_teams_in_the_panel_but_normal_users_cannot(): void
    {
        $team = $this->team(manager: User::factory()->create());

        $this->actingAs(User::factory()->admin()->create())->get('/admin/teams')->assertOk()->assertSee($team->name);
        $this->actingAs(User::factory()->create())->get('/admin/teams')->assertForbidden();
        $this->assertSame(Role::ADMIN, Role::query()->where('name', Role::ADMIN)->value('name'));
    }

    public function test_the_teams_tab_only_shows_for_people_in_a_team(): void
    {
        $loner = User::factory()->create();
        $member = User::factory()->create();
        $this->team(manager: User::factory()->create(), member: $member);

        $this->actingAs($loner)->get('/dashboard')->assertOk()->assertDontSee(route('teams.index'));
        $this->actingAs($member)->get('/dashboard')->assertOk()->assertSee('href="'.route('teams.index').'"', false);
    }

    public function test_an_admin_without_a_team_has_no_teams_tab_but_keeps_the_admin_tab(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('teams.index').'"', false)
            ->assertSee('href="/admin"', false);
    }

    public function test_someone_without_a_team_who_opens_the_page_anyway_gets_a_clear_message(): void
    {
        $this->actingAs(User::factory()->create())->get('/teams')->assertOk()->assertSee('You are not in a team');
    }

    public function test_the_tab_appears_as_soon_as_someone_becomes_a_team_manager(): void
    {
        $user = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER])->id]);

        $this->actingAs($user)->get('/dashboard')->assertSee('href="'.route('teams.index').'"', false);
    }
}

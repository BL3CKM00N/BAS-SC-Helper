<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\RelationManagers\UsersRelationManager;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_team_list_shows_open_and_managed_teams(): void
    {
        $manager = User::factory()->create(['name' => 'Marian Manager']);
        $managed = Team::factory()->managedBy($manager)->create(['name' => 'Managed team']);
        $open = Team::factory()->create(['name' => 'Open team']);

        $this->get('/admin/teams')
            ->assertOk()
            ->assertSee('Managed team')
            ->assertSee('Marian Manager')
            ->assertSee('Open team')
            ->assertSee('No manager');

        $this->assertTrue($open->isOpen());
        $this->assertFalse($managed->isOpen());
    }

    public function test_assigning_a_manager_to_an_open_team(): void
    {
        $team = Team::factory()->create();
        $person = User::factory()->create();

        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->assertActionVisible('assignManager')
            ->callAction('assignManager', ['user_id' => $person->getKey()])
            ->assertNotified('Manager assigned');

        $this->assertTrue($team->fresh()->isManagedBy($person));
        $this->assertTrue($person->fresh()->isTeamManager());
    }

    public function test_the_assign_action_is_hidden_when_the_team_has_a_manager(): void
    {
        $team = Team::factory()->managedBy(User::factory()->create())->create();

        Livewire::test(EditTeam::class, ['record' => $team->getKey()])->assertActionHidden('assignManager');
    }

    public function test_someone_who_manages_another_team_cannot_be_picked_as_manager(): void
    {
        $busy = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER])->id]);
        $team = Team::factory()->create();

        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->callAction('assignManager', ['user_id' => $busy->getKey()])
            ->assertHasActionErrors(['user_id']);

        $this->assertTrue($team->fresh()->isOpen());
    }

    public function test_the_manager_cannot_be_detached_but_members_can(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->managedBy($manager)->create();
        $team->users()->attach($member);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $team, 'pageClass' => EditTeam::class])
            ->assertTableActionHidden('detach', $manager)
            ->assertTableActionVisible('detach', $member)
            ->callTableAction('detach', $member);

        $this->assertFalse($team->hasMember($member));
        $this->assertModelExists($team);
    }

    public function test_detaching_the_last_member_of_an_open_team_removes_the_team(): void
    {
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->users()->attach($member);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $team, 'pageClass' => EditTeam::class])
            ->callTableAction('detach', $member);

        $this->assertModelMissing($team);
    }

    public function test_the_users_page_offers_the_team_manager_role(): void
    {
        $this->get('/admin/users/create')->assertOk();

        Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER]);
        $this->get('/admin/users')->assertOk();
    }

    public function test_the_admin_panel_has_no_top_bar_and_keeps_the_logo_and_profile_menu_in_the_sidebar(): void
    {
        $this->assertFalse(Filament::getPanel('admin')->hasTopbar());

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('fi-topbar', $html);
        $this->assertStringContainsString('fi-sidebar', $html);
        $this->assertStringContainsString('fi-user-menu', $html);
        $this->assertStringContainsString('/logo.svg', $html);
    }
}

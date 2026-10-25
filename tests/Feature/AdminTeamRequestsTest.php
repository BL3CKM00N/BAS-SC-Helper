<?php

namespace Tests\Feature;

use App\Filament\Resources\TeamRequests\Pages\ListTeamRequests;
use App\Filament\Resources\TeamRequests\TeamRequestResource;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTeamRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Role::query()->firstOrCreate(['name' => Role::USER]);
        $this->actingAs(User::factory()->admin()->create());
        $this->manager = User::factory()->create(['name' => 'Marian Manager']);
        $this->team = Team::factory()->managedBy($this->manager)->create(['name' => 'Service desk']);
    }

    private function request(array $attributes = []): TeamRequest
    {
        return TeamRequest::factory()->create($attributes + ['team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'name' => 'Nina New', 'email' => 'nina@example.com']);
    }

    public function test_only_admins_can_open_the_requests(): void
    {
        $this->get('/admin/team-requests')->assertOk();

        $this->actingAs($this->manager)->get('/admin/team-requests')->assertForbidden();
    }

    public function test_the_list_shows_pending_requests_and_the_badge_counts_them(): void
    {
        $this->request();
        $this->request(['email' => 'other@example.com', 'name' => 'Other One']);
        $this->request(['email' => 'done@example.com', 'name' => 'Done Already', 'status' => TeamRequest::APPROVED]);

        $this->assertSame('2', TeamRequestResource::getNavigationBadge());

        Livewire::test(ListTeamRequests::class)
            ->assertCanSeeTableRecords(TeamRequest::query()->where('status', TeamRequest::PENDING)->get())
            ->assertCanNotSeeTableRecords(TeamRequest::query()->where('status', TeamRequest::APPROVED)->get())
            ->assertSee('Service desk')
            ->assertSee('Marian Manager');
    }

    public function test_approving_a_new_account_with_a_temporary_password(): void
    {
        $request = $this->request();

        Livewire::test(ListTeamRequests::class)
            ->callTableAction('approve', $request, ['delivery' => 'password'])
            ->assertNotified('Account created for Nina New');

        $user = User::query()->where('email', 'nina@example.com')->firstOrFail();
        $this->assertTrue($this->team->hasMember($user));
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($request->fresh()->status === TeamRequest::APPROVED);
        $this->assertFalse(Hash::check('', $user->password));
    }

    public function test_the_email_option_is_disabled_without_mail_settings(): void
    {
        config(['mail.default' => 'log']);
        $request = $this->request();

        Livewire::test(ListTeamRequests::class)
            ->callTableAction('approve', $request, ['delivery' => 'email'])
            ->assertHasTableActionErrors(['delivery']);

        $this->assertTrue($request->fresh()->isPending());
        $this->assertSame(0, User::query()->where('email', 'nina@example.com')->count());
    }

    public function test_approving_an_add_request_needs_no_delivery_choice(): void
    {
        $user = User::factory()->create();
        $request = TeamRequest::factory()->create(['type' => TeamRequest::ADD_MEMBER, 'team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'target_user_id' => $user->id]);

        Livewire::test(ListTeamRequests::class)
            ->callTableAction('approve', $request)
            ->assertNotified('Request approved');

        $this->assertTrue($this->team->hasMember($user));
    }

    public function test_rejecting_a_request_with_a_reason(): void
    {
        $request = $this->request();

        Livewire::test(ListTeamRequests::class)
            ->callTableAction('reject', $request, ['reason' => 'Not this month'])
            ->assertNotified('Request rejected');

        $this->assertSame(TeamRequest::REJECTED, $request->fresh()->status);
        $this->assertSame('Not this month', $request->fresh()->decision_note);
    }

    public function test_decided_requests_have_no_actions(): void
    {
        $request = $this->request(['status' => TeamRequest::APPROVED]);

        Livewire::test(ListTeamRequests::class)
            ->filterTable('status', TeamRequest::APPROVED)
            ->assertTableActionHidden('approve', $request)
            ->assertTableActionHidden('reject', $request);
    }
}

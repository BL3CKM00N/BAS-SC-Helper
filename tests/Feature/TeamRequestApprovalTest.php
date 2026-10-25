<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use App\Notifications\AccountInvite;
use App\Notifications\TeamRequestDecided;
use App\Services\Teams\TeamRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Team $team;

    private TeamRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->create();
        $this->team = Team::factory()->managedBy($this->manager)->create();
        $this->service = app(TeamRequestService::class);
        Role::query()->firstOrCreate(['name' => Role::USER]);
    }

    private function newMemberRequest(): TeamRequest
    {
        return TeamRequest::factory()->create([
            'team_id' => $this->team->id,
            'requested_by' => $this->manager->id,
            'name' => 'Nina New',
            'email' => 'nina@example.com',
        ]);
    }

    // ------------------------------------------------------------ a new account

    public function test_approving_with_a_temporary_password_creates_the_account(): void
    {
        $request = $this->newMemberRequest();

        $approval = $this->service->approve($request, $this->admin, TeamRequestService::DELIVER_PASSWORD);

        $user = User::query()->where('email', 'nina@example.com')->firstOrFail();
        $this->assertSame('Nina New', $user->name);
        $this->assertSame(Role::USER, $user->role->name);
        $this->assertTrue($this->team->hasMember($user));
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->invited_at);
        $this->assertNotEmpty($approval->temporaryPassword);
        $this->assertTrue(Hash::check($approval->temporaryPassword, $user->password));

        $request->refresh();
        $this->assertSame(TeamRequest::APPROVED, $request->status);
        $this->assertSame($this->admin->id, $request->decided_by);
        $this->assertNotNull($request->decided_at);
    }

    public function test_approving_with_an_email_invitation_sends_the_mail_and_sets_no_known_password(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $request = $this->newMemberRequest();

        $approval = $this->service->approve($request, $this->admin, TeamRequestService::DELIVER_EMAIL);

        $user = $approval->user;
        $this->assertNull($approval->temporaryPassword);
        $this->assertNotNull($user->invited_at);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue($this->team->hasMember($user));
        Notification::assertSentTo($user, AccountInvite::class);
    }

    public function test_an_email_invitation_is_refused_when_the_app_cannot_send_mail(): void
    {
        config(['mail.default' => 'log']);
        $request = $this->newMemberRequest();

        try {
            $this->service->approve($request, $this->admin, TeamRequestService::DELIVER_EMAIL);
            $this->fail('This should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery', $exception->errors());
        }

        $this->assertSame(0, User::query()->where('email', 'nina@example.com')->count());
        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_when_the_mail_server_fails_nothing_is_created_and_the_request_stays_open(): void
    {
        config(['mail.default' => 'smtp']);
        $request = $this->newMemberRequest();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        try {
            $this->service->approve($request, $this->admin, TeamRequestService::DELIVER_EMAIL);
            $this->fail('This should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery', $exception->errors());
        }

        $this->assertSame(0, User::query()->where('email', 'nina@example.com')->count());
        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_approving_fails_when_the_email_got_an_account_in_the_meantime(): void
    {
        $request = $this->newMemberRequest();
        User::factory()->create(['email' => 'nina@example.com']);

        $this->expectException(ValidationException::class);

        $this->service->approve($request, $this->admin);
    }

    // ------------------------------------------------------------ adding and removing

    public function test_approving_an_add_request_puts_the_user_in_the_team(): void
    {
        $user = User::factory()->create();
        $request = TeamRequest::factory()->create(['type' => TeamRequest::ADD_MEMBER, 'team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'target_user_id' => $user->id, 'name' => null, 'email' => null]);

        $this->service->approve($request, $this->admin);

        $this->assertTrue($this->team->hasMember($user));
        $this->assertSame(TeamRequest::APPROVED, $request->fresh()->status);
    }

    public function test_a_user_can_be_in_several_teams_after_an_add_request(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->managedBy(User::factory()->create())->create();
        $otherTeam->users()->attach($user);
        $request = TeamRequest::factory()->create(['type' => TeamRequest::ADD_MEMBER, 'team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'target_user_id' => $user->id]);

        $this->service->approve($request, $this->admin);

        $this->assertTrue($this->team->hasMember($user));
        $this->assertTrue($otherTeam->hasMember($user));
    }

    public function test_approving_a_remove_request_takes_the_member_out(): void
    {
        $user = User::factory()->create();
        $this->team->users()->attach($user);
        $request = TeamRequest::factory()->create(['type' => TeamRequest::REMOVE_MEMBER, 'team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'target_user_id' => $user->id]);

        $this->service->approve($request, $this->admin);

        $this->assertFalse($this->team->hasMember($user));
        $this->assertModelExists($this->team);
    }

    public function test_moving_the_last_member_out_of_an_open_team_removes_the_team(): void
    {
        $open = Team::factory()->create();
        $user = User::factory()->create();
        $open->users()->attach($user);
        $request = TeamRequest::factory()->create(['type' => TeamRequest::REMOVE_MEMBER, 'team_id' => $open->id, 'requested_by' => $this->manager->id, 'target_user_id' => $user->id]);

        $this->service->approve($request, $this->admin);

        $this->assertModelMissing($open);
    }

    // ------------------------------------------------------------ deciding

    public function test_a_request_can_only_be_decided_once(): void
    {
        $request = $this->newMemberRequest();
        $this->service->approve($request, $this->admin);

        $this->expectException(ValidationException::class);

        $this->service->approve($request->fresh(), $this->admin);
    }

    public function test_rejecting_keeps_the_reason_and_changes_nothing_else(): void
    {
        $request = $this->newMemberRequest();

        $this->service->reject($request, $this->admin, 'We are not hiring');

        $request->refresh();
        $this->assertSame(TeamRequest::REJECTED, $request->status);
        $this->assertSame('We are not hiring', $request->decision_note);
        $this->assertSame($this->admin->id, $request->decided_by);
        $this->assertSame(0, User::query()->where('email', 'nina@example.com')->count());
    }

    public function test_the_manager_is_told_about_the_decision(): void
    {
        Notification::fake();
        $this->manager->updatePushSubscription('https://push.example.com/send/mgr-1', 'key', 'token');

        $this->service->reject($this->newMemberRequest(), $this->admin, 'No');

        Notification::assertSentTo($this->manager, TeamRequestDecided::class);
    }
}

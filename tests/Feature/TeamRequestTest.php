<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use App\Notifications\TeamRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->team = Team::factory()->managedBy($this->manager)->create();
    }

    private function send(array $data, ?User $as = null, ?Team $team = null)
    {
        return $this->actingAs($as ?? $this->manager)->post(route('teams.requests.store', $team ?? $this->team), $data);
    }

    // ------------------------------------------------------------ who may ask

    public function test_guests_cannot_send_requests(): void
    {
        $this->post(route('teams.requests.store', $this->team), ['type' => 'new_member'])->assertRedirect('/');
    }

    public function test_only_the_manager_of_the_team_can_send_requests(): void
    {
        $member = User::factory()->create();
        $this->team->users()->attach($member);
        $otherManager = User::factory()->create();
        Team::factory()->managedBy($otherManager)->create();
        $data = ['type' => 'new_member', 'name' => 'New Person', 'email' => 'new@example.com'];

        $this->send($data, $member)->assertForbidden();
        $this->send($data, $otherManager)->assertForbidden();
        $this->send($data, User::factory()->create())->assertForbidden();

        $this->assertSame(0, TeamRequest::query()->count());
    }

    public function test_a_manager_has_no_direct_way_to_change_members(): void
    {
        $member = User::factory()->create();
        $this->team->users()->attach($member);

        $this->actingAs($this->manager)->post("/teams/{$this->team->id}/members", ['email' => $member->email])->assertNotFound();
        $this->actingAs($this->manager)->delete("/teams/{$this->team->id}/members/{$member->id}")->assertNotFound();

        $this->assertTrue($this->team->hasMember($member));
    }

    // ------------------------------------------------------------ a new account

    public function test_a_manager_can_request_a_new_account(): void
    {
        $this->send(['type' => 'new_member', 'name' => 'Nina New', 'email' => 'Nina@Example.com', 'note' => 'Starts Monday'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $request = TeamRequest::query()->firstOrFail();
        $this->assertSame(TeamRequest::PENDING, $request->status);
        $this->assertSame($this->team->id, $request->team_id);
        $this->assertSame($this->manager->id, $request->requested_by);
        $this->assertSame('nina@example.com', $request->email, 'email addresses are stored in lower case');
        $this->assertSame('Starts Monday', $request->note);
    }

    public function test_a_new_account_needs_a_name_and_a_valid_email(): void
    {
        $this->send(['type' => 'new_member', 'name' => '', 'email' => 'nina@example.com'])->assertSessionHasErrors('email');
        $this->send(['type' => 'new_member', 'name' => 'Nina', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertSame(0, TeamRequest::query()->count());
    }

    public function test_an_account_cannot_be_requested_for_an_existing_user_or_twice(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->send(['type' => 'new_member', 'name' => 'Someone', 'email' => 'TAKEN@example.com'])->assertSessionHasErrors('email');

        $this->send(['type' => 'new_member', 'name' => 'Nina', 'email' => 'nina@example.com'])->assertSessionHasNoErrors();
        $this->send(['type' => 'new_member', 'name' => 'Nina', 'email' => 'nina@example.com'])->assertSessionHasErrors('email');

        $this->assertSame(1, TeamRequest::query()->count());
    }

    // ------------------------------------------------------------ adding and removing

    public function test_a_manager_can_request_to_add_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'carl@example.com']);

        $this->send(['type' => 'add_member', 'email' => 'carl@example.com'])->assertSessionHasNoErrors();

        $request = TeamRequest::query()->firstOrFail();
        $this->assertSame(TeamRequest::ADD_MEMBER, $request->type);
        $this->assertSame($user->id, $request->target_user_id);
    }

    public function test_adding_fails_for_unknown_people_current_members_and_duplicates(): void
    {
        $member = User::factory()->create();
        $this->team->users()->attach($member);
        $other = User::factory()->create();

        $this->send(['type' => 'add_member', 'email' => 'nobody@example.com'])->assertSessionHasErrors('email');
        $this->send(['type' => 'add_member', 'email' => $member->email])->assertSessionHasErrors('email');

        $this->send(['type' => 'add_member', 'email' => $other->email])->assertSessionHasNoErrors();
        $this->send(['type' => 'add_member', 'email' => $other->email])->assertSessionHasErrors('email');
    }

    public function test_a_manager_can_request_to_remove_a_member_but_not_themselves(): void
    {
        $member = User::factory()->create();
        $this->team->users()->attach($member);

        $this->send(['type' => 'remove_member', 'user_id' => $member->id])->assertSessionHasNoErrors();
        $this->send(['type' => 'remove_member', 'user_id' => $member->id])->assertSessionHasErrors('user_id');
        $this->send(['type' => 'remove_member', 'user_id' => $this->manager->id])->assertSessionHasErrors('user_id');
        $this->send(['type' => 'remove_member', 'user_id' => User::factory()->create()->id])->assertSessionHasErrors('user_id');

        $this->assertSame(1, TeamRequest::query()->count());
        $this->assertTrue($this->team->hasMember($member), 'the member stays until an admin approves');
    }

    // ------------------------------------------------------------ the manager's view

    public function test_the_manager_sees_the_forms_and_the_requests_but_members_do_not(): void
    {
        $member = User::factory()->create();
        $this->team->users()->attach($member);
        TeamRequest::factory()->create(['team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'name' => 'Nina New', 'email' => 'nina@example.com']);

        $this->actingAs($this->manager)->get(route('teams.show', $this->team))
            ->assertOk()
            ->assertSee('Request a new account')
            ->assertSee('Request to add someone who already has an account')
            ->assertSee('Request removal')
            ->assertSee('New account for Nina New')
            ->assertSee('pending');

        $this->actingAs($member)->get(route('teams.show', $this->team))
            ->assertOk()
            ->assertDontSee('Request a new account')
            ->assertDontSee('Request removal')
            ->assertDontSee('Nina New');
    }

    public function test_a_manager_can_withdraw_a_pending_request_but_not_someone_elses_or_a_decided_one(): void
    {
        $mine = TeamRequest::factory()->create(['team_id' => $this->team->id, 'requested_by' => $this->manager->id]);
        $decided = TeamRequest::factory()->create(['team_id' => $this->team->id, 'requested_by' => $this->manager->id, 'status' => TeamRequest::APPROVED]);
        $other = User::factory()->create();
        $theirs = TeamRequest::factory()->create(['team_id' => $this->team->id, 'requested_by' => $other->id]);

        $this->actingAs($this->manager)->delete(route('teams.requests.destroy', [$this->team, $decided]))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('teams.requests.destroy', [$this->team, $theirs]))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('teams.requests.destroy', [$this->team, $mine]))->assertRedirect();

        $this->assertModelMissing($mine);
        $this->assertModelExists($decided);
        $this->assertModelExists($theirs);
    }

    // ------------------------------------------------------------ the admins hear about it

    public function test_admins_are_notified_of_a_new_request(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $admin->updatePushSubscription('https://push.example.com/send/admin-1', 'key', 'token');
        $normal = User::factory()->create();

        $this->send(['type' => 'new_member', 'name' => 'Nina', 'email' => 'nina@example.com']);

        Notification::assertSentTo($admin, TeamRequestSubmitted::class);
        Notification::assertNotSentTo($normal, TeamRequestSubmitted::class);
    }

    public function test_the_team_request_roles_exist_for_every_type(): void
    {
        $this->assertSame('team_manager', Role::TEAM_MANAGER);
        $this->assertSame(['new_member', 'add_member', 'remove_member'], [TeamRequest::NEW_MEMBER, TeamRequest::ADD_MEMBER, TeamRequest::REMOVE_MEMBER]);
    }
}

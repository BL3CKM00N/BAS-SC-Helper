<?php

namespace Tests\Feature;

use App\Filament\Pages\ActiveSessions;
use App\Filament\Resources\LoginAttempts\Pages\ListLoginAttempts;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\Role;
use App\Models\User;
use App\Services\Account\UserSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::ADMIN])->id]);
    }

    private function addSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.5',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605 Version/17.0 Mobile Safari/604.1',
            'payload' => '', 'last_activity' => time(),
        ]);
    }

    public function test_the_page_lists_sessions_without_exposing_session_ids(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Pat Example']);
        $this->addSession($user, str_repeat('s', 40));

        $component = Livewire::actingAs($admin)->test(ActiveSessions::class)->assertSee('Pat Example')->assertSee('Safari on iOS');

        $this->assertStringNotContainsString(str_repeat('s', 40), $component->html());
    }

    public function test_an_admin_can_end_someone_elses_session(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->addSession($user, str_repeat('s', 40));
        $handle = app(UserSessions::class)->handle(str_repeat('s', 40));

        Livewire::actingAs($admin)->test(ActiveSessions::class)->callTableAction('signOut', $handle);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'session.revoked')->exists());
    }

    public function test_sign_ins_are_logged_with_the_result(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'x']);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1']);

        $this->assertSame(2, LoginAttempt::query()->where('successful', false)->count());
        $this->assertSame('Unknown email address', LoginAttempt::query()->where('email', 'nobody@example.com')->value('reason'));
        $this->assertSame(1, LoginAttempt::query()->where('successful', true)->count());
    }

    public function test_the_log_page_renders_and_old_attempts_are_pruned(): void
    {
        $admin = $this->admin();
        LoginAttempt::query()->create(['email' => 'old@example.com', 'successful' => false, 'created_at' => now()->subDays(LoginAttempt::KEEP_DAYS + 1)]);
        LoginAttempt::query()->create(['email' => 'new@example.com', 'successful' => true, 'created_at' => now()]);

        (new LoginAttempt)->prunable()->get()->each->delete();

        Livewire::actingAs($admin)->test(ListLoginAttempts::class)->assertSee('new@example.com')->assertDontSee('old@example.com');
    }
}

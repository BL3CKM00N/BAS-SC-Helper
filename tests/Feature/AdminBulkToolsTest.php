<?php

namespace Tests\Feature;

use App\Filament\Pages\AppDefaultsPage;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Admin\AppDefaults;
use App\Services\Admin\CsvExport;
use App\Services\Admin\UserImport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBulkToolsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::ADMIN])->id]);
    }

    public function test_import_creates_accounts_with_temporary_passwords_and_teams(): void
    {
        Role::query()->firstOrCreate(['name' => Role::USER]);
        Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER]);
        $team = Team::factory()->create(['name' => 'Alpha']);
        $csv = "name,email,role,team\nAnna Test,anna@example.com,,Alpha\nBert Test,BERT@example.com,team_manager,\n";

        $result = app(UserImport::class)->run($csv, UserImport::DELIVER_PASSWORD, $this->admin());

        $this->assertCount(2, $result['created']);
        $this->assertSame([], $result['skipped']);
        $anna = User::query()->where('email', 'anna@example.com')->firstOrFail();
        $this->assertTrue($anna->must_change_password);
        $this->assertTrue($team->hasMember($anna));
        $this->assertTrue(password_verify($result['created'][0]['password'], $anna->password));
        $this->assertTrue(User::query()->where('email', 'bert@example.com')->firstOrFail()->isTeamManager());
        $this->assertTrue(AuditLog::query()->where('action', 'user.imported')->exists());
    }

    public function test_import_skips_bad_rows_and_reports_them(): void
    {
        Role::query()->firstOrCreate(['name' => Role::USER]);
        User::factory()->create(['email' => 'taken@example.com']);
        $csv = "name;email;role;team\nOk Person;ok@example.com;;\n;nobody@example.com;;\nTaken;taken@example.com;;\nWrong Role;wr@example.com;king;\nNo Team;nt@example.com;;Nope\n";

        $result = app(UserImport::class)->run($csv, UserImport::DELIVER_PASSWORD, $this->admin());

        $this->assertCount(1, $result['created']);
        $this->assertCount(4, $result['skipped']);
        $this->assertSame([3, 4, 5, 6], array_column($result['skipped'], 'row'));
    }

    public function test_import_rejects_files_without_the_right_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UserImport::class)->run("title,mail\nA,b@example.com\n", UserImport::DELIVER_PASSWORD, $this->admin());
    }

    public function test_email_delivery_needs_working_mail(): void
    {
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';

        $this->expectException(InvalidArgumentException::class);

        app(UserImport::class)->run("name,email\nA,a@example.com\n", UserImport::DELIVER_EMAIL, $this->admin());
    }

    public function test_exports_are_safe_against_spreadsheet_formulas(): void
    {
        $user = User::factory()->create(['name' => '=HYPERLINK("http://evil")']);

        $csv = app(CsvExport::class)->users();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($user->email, $csv);
        $this->assertStringContainsString('team', app(CsvExport::class)->teams());
    }

    public function test_bulk_add_and_remove_from_a_team(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->create();
        $team = Team::factory()->managedBy($manager)->create();
        $people = User::factory()->count(2)->create();

        Livewire::actingAs($admin)->test(ListUsers::class)
            ->callTableBulkAction('addToTeam', $people, ['team_id' => $team->id]);

        $this->assertTrue($team->fresh()->hasMember($people[0]) && $team->fresh()->hasMember($people[1]));

        Livewire::actingAs($admin)->test(ListUsers::class)
            ->callTableBulkAction('removeFromTeam', $people->concat([$manager]), ['team_id' => $team->id]);

        $this->assertFalse($team->fresh()->hasMember($people[0]));
        $this->assertTrue($team->fresh()->hasMember($manager), 'the manager stays');
    }

    public function test_defaults_apply_to_new_accounts_and_teams_only(): void
    {
        $existing = User::factory()->create();
        app(AppDefaults::class)->save(['timezone' => 'Europe/London', 'work_days' => '1,2,3', 'work_start' => '09:00', 'work_end' => '16:00', 'members_can_create' => true]);

        $new = User::factory()->create();
        $manager = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER])->id]);

        $this->assertSame('Europe/Amsterdam', $existing->fresh()->timezone);
        $this->assertSame('Europe/London', $new->fresh()->timezone);
        $this->assertSame('09:00', $new->fresh()->work_start);
        $this->assertTrue($manager->managedTeam()->first()->members_can_create);
    }

    public function test_the_defaults_page_saves(): void
    {
        Livewire::actingAs($this->admin())->test(AppDefaultsPage::class)
            ->fillForm(['timezone' => 'Europe/Paris', 'work_days' => [1, 2], 'work_start' => '07:30', 'work_end' => '16:00', 'members_can_create' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Europe/Paris', app(AppDefaults::class)->all()['timezone']);
        $this->assertSame('1,2', app(AppDefaults::class)->all()['work_days']);
        $this->assertTrue(app(AppDefaults::class)->membersCanCreate());
    }
}

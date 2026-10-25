<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_roles_and_the_default_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing([Role::ADMIN, Role::USER], Role::query()->pluck('name')->all());

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('Admin', $admin->name);
        $this->assertTrue(Hash::check('CHANGEME!', $admin->password));
    }

    public function test_running_it_again_keeps_a_changed_password_and_name(): void
    {
        $this->seed(DatabaseSeeder::class);

        User::query()->where('email', 'admin@example.com')->firstOrFail()
            ->update(['name' => 'Daan', 'password' => 'a-new-password']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->count());
        $admin = User::query()->firstOrFail();
        $this->assertSame('Daan', $admin->name);
        $this->assertTrue(Hash::check('a-new-password', $admin->password));
        $this->assertTrue($admin->isAdmin());
    }

    public function test_it_restores_the_admin_role_if_it_was_removed(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::query()->where('email', 'admin@example.com')->update(['role_id' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@example.com')->firstOrFail()->isAdmin());
    }

    public function test_the_admin_email_and_password_come_from_the_environment(): void
    {
        foreach (['ADMIN_MAIL' => 'boss@example.org', 'ADMIN_PASSWORD' => 'from-the-env'] as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        try {
            $this->seed(DatabaseSeeder::class);

            $admin = User::query()->where('email', 'boss@example.org')->firstOrFail();
            $this->assertTrue(Hash::check('from-the-env', $admin->password));
            $this->assertTrue($admin->isAdmin());
        } finally {
            foreach (['ADMIN_MAIL', 'ADMIN_PASSWORD'] as $key) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            }
        }
    }
}

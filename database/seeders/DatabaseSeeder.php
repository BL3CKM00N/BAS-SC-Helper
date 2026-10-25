<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $adminEmail = env('ADMIN_MAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'CHANGEME!');
        $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');

        // The name and password are only set when the account is created, so running the seeder
        // on every deploy never overwrites a password that was changed in the admin panel.
        $admin = User::query()->firstOrNew(['email' => $adminEmail], ['name' => 'Admin']);

        if (! $admin->exists) {
            $admin->password = $adminPassword;
        }

        $admin->role_id = $adminRoleId;
        $admin->save();
    }
}

<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([Role::ADMIN, Role::TEAM_MANAGER, Role::USER] as $name) {
            Role::query()->firstOrCreate(['name' => $name]);
        }
    }
}

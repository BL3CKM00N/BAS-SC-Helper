<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A team has at most one manager, who manages at most one team. The manager is stored on the team
 * (`manager_id`, empty means the team is open) instead of as a role on the team_user pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->unique()->constrained('users')->nullOnDelete();
        });

        $managerRoleId = DB::table('roles')->where('name', 'team_manager')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'team_manager', 'created_at' => now(), 'updated_at' => now()]);
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        // Carry the existing managers over: the first manager of each team, and nobody twice.
        $taken = [];

        foreach (DB::table('team_user')->where('role', 'manager')->orderBy('id')->get() as $row) {
            if (in_array($row->user_id, $taken, true)) {
                continue;
            }

            $taken[] = $row->user_id;

            DB::table('teams')->where('id', $row->team_id)->whereNull('manager_id')->update(['manager_id' => $row->user_id]);
            DB::table('users')->where('id', $row->user_id)->where(fn ($query) => $query->whereNull('role_id')->orWhere('role_id', '!=', $adminRoleId))->update(['role_id' => $managerRoleId]);
        }

        Schema::table('team_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            $table->string('role')->default('member');
        });

        foreach (DB::table('teams')->whereNotNull('manager_id')->get() as $team) {
            DB::table('team_user')->where('team_id', $team->id)->where('user_id', $team->manager_id)->update(['role' => 'manager']);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};

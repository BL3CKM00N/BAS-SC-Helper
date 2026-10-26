<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // "On chosen days" became a setting of the repeating kind.
        DB::table('reminders')->where('type', 'scheduled')->update(['type' => 'repeating']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reminders')->where('type', 'repeating')->whereNotNull('recurrence')->whereNull('interval_minutes')->update(['type' => 'scheduled']);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('recurrence')->nullable(); // weekly or monthly, for reminders "on chosen days"
            $table->string('weekdays')->nullable(); // ISO weekdays, "1,3,5"
            $table->string('month_days')->nullable(); // days of the month, -1 is the last day
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['recurrence', 'weekdays', 'month_days']);
        });
    }
};

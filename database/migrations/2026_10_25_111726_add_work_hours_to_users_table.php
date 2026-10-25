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
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->default('Europe/Amsterdam');
            $table->string('work_days')->default('1,2,3,4,5');
            $table->string('work_start', 5)->default('08:00');
            $table->string('work_end', 5)->default('17:30');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'work_days', 'work_start', 'work_end']);
        });
    }
};

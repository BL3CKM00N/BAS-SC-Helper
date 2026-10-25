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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('type'); // deadline or repeating
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('lead_minutes')->default(240);
            $table->unsignedInteger('nag_minutes')->nullable();
            $table->unsignedInteger('interval_minutes')->nullable();
            $table->unsignedSmallInteger('daily_target')->nullable();
            $table->string('completion_mode')->default('anyone'); // anyone or each
            $table->boolean('ignore_work_hours_when_overdue')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Folders are private to the person who made them. A reminder (also a team reminder) can sit in one
     * of a person's folders; what other people do with it does not matter to them.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('folder_reminder', function (Blueprint $table) {
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();

            $table->primary(['folder_id', 'reminder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_reminder');
        Schema::dropIfExists('folders');
    }
};

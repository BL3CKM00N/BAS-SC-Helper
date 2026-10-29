<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The place of a folder when someone sorts their folders in their own order.
     */
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};

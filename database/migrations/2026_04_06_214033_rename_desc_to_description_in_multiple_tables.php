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
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('desc', 'description');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('desc', 'description');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('desc', 'description');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->renameColumn('desc', 'description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('description', 'desc');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('description', 'desc');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('description', 'desc');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->renameColumn('description', 'desc');
        });
    }
};

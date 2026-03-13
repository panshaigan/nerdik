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
            $table->string('nickname')->after('name');
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('discord_handle')->nullable()->after('avatar_path');
            $table->string('current_location')->nullable()->after('discord_handle');
            $table->json('languages')->nullable()->after('current_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'avatar_path',
                'discord_handle',
                'current_location',
                'languages',
            ]);
        });
    }
};

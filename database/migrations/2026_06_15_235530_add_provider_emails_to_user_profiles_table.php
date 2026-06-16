<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('google_email')->nullable()->after('google_avatar_url');
            $table->string('facebook_email')->nullable()->after('facebook_avatar_url');
            $table->string('discord_email')->nullable()->after('discord_avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn(['google_email', 'facebook_email', 'discord_email']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->boolean('show_contact_email')->default(false)->after('discord_email');
            $table->boolean('show_contact_facebook')->default(true)->after('show_contact_email');
            $table->boolean('show_contact_google')->default(true)->after('show_contact_facebook');
            $table->boolean('show_contact_discord')->default(true)->after('show_contact_google');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'show_contact_email',
                'show_contact_facebook',
                'show_contact_google',
                'show_contact_discord',
            ]);
        });
    }
};

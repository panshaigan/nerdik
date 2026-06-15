<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const array AVATAR_SOURCES_WITH_DISCORD = [
        'generated',
        'uploaded',
        'gravatar',
        'google',
        'facebook',
        'discord',
    ];

    /** @var list<string> */
    private const array AVATAR_SOURCES_WITHOUT_DISCORD = [
        'generated',
        'uploaded',
        'gravatar',
        'google',
        'facebook',
    ];

    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('discord_id')->nullable()->after('facebook_id');
            $table->text('discord_avatar_url')->nullable()->after('facebook_avatar_url');
        });

        $this->replaceAvatarSourceConstraint(self::AVATAR_SOURCES_WITH_DISCORD);
    }

    public function down(): void
    {
        DB::table('user_profiles')
            ->where('avatar_source', 'discord')
            ->update(['avatar_source' => 'generated']);

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['discord_id', 'discord_avatar_url']);
        });

        $this->replaceAvatarSourceConstraint(self::AVATAR_SOURCES_WITHOUT_DISCORD);
    }

    /**
     * @param  list<string>  $sources
     */
    private function replaceAvatarSourceConstraint(array $sources): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE user_profiles DROP CONSTRAINT IF EXISTS user_profiles_avatar_source_check');
            $allowed = implode(', ', array_map(static fn (string $source): string => "'{$source}'", $sources));
            DB::statement("ALTER TABLE user_profiles ADD CONSTRAINT user_profiles_avatar_source_check CHECK (avatar_source IN ({$allowed}))");

            return;
        }

        if ($driver === 'mysql') {
            $enumValues = implode("','", $sources);
            DB::statement("ALTER TABLE user_profiles MODIFY avatar_source ENUM('{$enumValues}') NOT NULL DEFAULT 'generated'");
        }
    }
};

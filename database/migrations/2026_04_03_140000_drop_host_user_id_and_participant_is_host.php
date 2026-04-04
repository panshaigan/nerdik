<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_participants', function (Blueprint $table) {
            $table->dropColumn('is_host');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['host_user_id']);
            $table->dropColumn('host_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('host_user_id')->nullable()->after('price')->constrained('users')->nullOnDelete();
        });

        Schema::table('activity_participants', function (Blueprint $table) {
            $table->boolean('is_host')->default(false)->after('user_id');
        });
    }
};

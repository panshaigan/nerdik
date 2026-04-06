<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'description')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->dropColumn('description');
            });
        }

        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'extra')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->dropColumn('extra');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tags') && ! Schema::hasColumn('tags', 'description')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->text('description')->nullable()->after('tag_category_id');
            });
        }

        if (Schema::hasTable('activities') && ! Schema::hasColumn('activities', 'extra')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->json('extra')->nullable();
            });
        }
    }
};

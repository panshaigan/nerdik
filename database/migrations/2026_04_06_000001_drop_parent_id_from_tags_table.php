<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tags', 'parent_id')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tags', 'parent_id')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('tags')->nullOnDelete();
        });
    }
};

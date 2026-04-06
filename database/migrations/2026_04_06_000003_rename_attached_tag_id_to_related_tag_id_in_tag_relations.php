<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tag_relations') || ! Schema::hasColumn('tag_relations', 'attached_tag_id')) {
            return;
        }

        Schema::table('tag_relations', function (Blueprint $table) {
            $table->renameColumn('attached_tag_id', 'related_tag_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tag_relations') || ! Schema::hasColumn('tag_relations', 'related_tag_id')) {
            return;
        }

        Schema::table('tag_relations', function (Blueprint $table) {
            $table->renameColumn('related_tag_id', 'attached_tag_id');
        });
    }
};

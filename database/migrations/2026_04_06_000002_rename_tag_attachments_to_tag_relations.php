<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tag_attachments') && ! Schema::hasTable('tag_relations')) {
            Schema::rename('tag_attachments', 'tag_relations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tag_relations') && ! Schema::hasTable('tag_attachments')) {
            Schema::rename('tag_relations', 'tag_attachments');
        }
    }
};

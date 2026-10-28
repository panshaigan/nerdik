<?php

use App\Actions\Catalog\SyncGameTagSuggestedBringCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suggested_bring_items') || ! Schema::hasTable('suggested_bring_item_translations')) {
            return;
        }

        app(SyncGameTagSuggestedBringCatalog::class)->importFromJsonFile(
            database_path('data/game_tag_suggested_bring_dice.json'),
        );
    }

    public function down(): void
    {
        // Data migration: kit can be re-imported from the catalog file if needed.
    }
};

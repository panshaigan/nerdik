<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggested_bring_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggested_bring_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('label', 255);
            $table->unique(['suggested_bring_item_id', 'locale']);
        });

        if (Schema::hasColumn('suggested_bring_items', 'label')) {
            $now = now();
            DB::table('suggested_bring_items')
                ->orderBy('id')
                ->chunkById(200, function ($items): void {
                    $rows = [];
                    foreach ($items as $item) {
                        $label = trim((string) ($item->label ?? ''));
                        if ($label === '') {
                            continue;
                        }
                        $rows[] = [
                            'suggested_bring_item_id' => (int) $item->id,
                            'locale' => 'en',
                            'label' => mb_substr($label, 0, 255),
                        ];
                    }
                    if ($rows !== []) {
                        DB::table('suggested_bring_item_translations')->insert($rows);
                    }
                });

            Schema::table('suggested_bring_items', function (Blueprint $table) {
                $table->dropColumn('label');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('suggested_bring_items', 'label')) {
            Schema::table('suggested_bring_items', function (Blueprint $table) {
                $table->string('label', 255)->default('')->after('bringable_id');
            });

            $translations = DB::table('suggested_bring_item_translations')
                ->where('locale', 'en')
                ->get(['suggested_bring_item_id', 'label']);

            foreach ($translations as $row) {
                DB::table('suggested_bring_items')
                    ->where('id', (int) $row->suggested_bring_item_id)
                    ->update(['label' => $row->label]);
            }
        }

        Schema::dropIfExists('suggested_bring_item_translations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_links', function (Blueprint $table) {
            $table->id();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id', 'sort_order']);
        });

        if (Schema::hasColumn('places', 'links')) {
            $now = now();

            DB::table('places')
                ->whereNotNull('links')
                ->where('links', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($places) use ($now): void {
                    foreach ($places as $place) {
                        $raw = trim((string) $place->links);
                        if ($raw === '') {
                            continue;
                        }

                        $url = $raw;
                        if (! preg_match('#^https?://#i', $url)) {
                            if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$#i', $url) === 1) {
                                $url = 'https://'.$url;
                            } else {
                                continue;
                            }
                        }

                        $host = parse_url($url, PHP_URL_HOST);
                        $name = is_string($host) && $host !== '' ? $host : 'Link';
                        if (mb_strlen($name) > 100) {
                            $name = mb_substr($name, 0, 100);
                        }

                        DB::table('entity_links')->insert([
                            'linkable_type' => 'place',
                            'linkable_id' => $place->id,
                            'name' => $name,
                            'url' => mb_substr($url, 0, 2048),
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });

            Schema::table('places', function (Blueprint $table) {
                $table->dropColumn('links');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('places', 'links')) {
            Schema::table('places', function (Blueprint $table) {
                $table->string('links')->nullable();
            });
        }

        if (Schema::hasTable('entity_links')) {
            $placeLinks = DB::table('entity_links')
                ->where('linkable_type', 'place')
                ->orderBy('linkable_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $firstByPlace = [];
            foreach ($placeLinks as $link) {
                $placeId = (int) $link->linkable_id;
                if (! array_key_exists($placeId, $firstByPlace)) {
                    $firstByPlace[$placeId] = mb_substr((string) $link->url, 0, 255);
                }
            }

            foreach ($firstByPlace as $placeId => $url) {
                DB::table('places')->where('id', $placeId)->update(['links' => $url]);
            }
        }

        Schema::dropIfExists('entity_links');
    }
};

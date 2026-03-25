<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapse event (parent) + event_instance into a single `events` row per occurrence:
     * rename legacy `events` → `events_legacy`, merge data into `event_instances`, then
     * rename `event_instances` → `events` and repoint slots / proposals / pivots.
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_instances')) {
            return;
        }

        $this->dropMergeForeignKeys();

        Schema::rename('events', 'events_legacy');

        Schema::table('event_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('event_instances', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('event_id');
            }
            if (! Schema::hasColumn('event_instances', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('organization_id');
            }
        });

        if (DB::table('event_instances')->count() === 0 && DB::table('events_legacy')->count() > 0) {
            $ts = now();
            foreach (DB::table('events_legacy')->get() as $leg) {
                $slug = $leg->slug;
                $base = $slug;
                $n = 0;
                while (DB::table('event_instances')->where('slug', $slug)->exists()) {
                    $n++;
                    $slug = $base.'-'.$n;
                }
                DB::table('event_instances')->insert([
                    'event_id' => $leg->id,
                    'name' => $leg->name,
                    'desc' => $leg->desc,
                    'organization_id' => $leg->organization_id,
                    'is_public' => (bool) $leg->is_public,
                    'starts_at' => $ts->copy()->addDay(),
                    'ends_at' => $ts->copy()->addDays(2),
                    'logo_path' => $leg->logo_path,
                    'slug' => $slug,
                    'created_by' => $leg->created_by,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            }
        }

        $legacyRows = DB::table('events_legacy')->get()->keyBy('id');
        $instances = DB::table('event_instances')->get();

        foreach ($instances as $ei) {
            $legacy = $legacyRows->get($ei->event_id);
            if (! $legacy) {
                continue;
            }
            DB::table('event_instances')->where('id', $ei->id)->update([
                'organization_id' => $legacy->organization_id,
                'is_public' => (bool) $legacy->is_public,
                'name' => ($ei->name !== null && $ei->name !== '') ? $ei->name : $legacy->name,
                'desc' => $ei->desc ?? $legacy->desc,
                'created_by' => $ei->created_by ?? $legacy->created_by,
                'logo_path' => $ei->logo_path ?? $legacy->logo_path,
            ]);
        }

        $legacyIdsWithInstances = DB::table('event_instances')->pluck('event_id')->unique()->filter()->values()->all();
        $orphanLegacies = DB::table('events_legacy')->whereNotIn('id', $legacyIdsWithInstances)->get();
        $stamp = now();
        foreach ($orphanLegacies as $leg) {
            $slug = $leg->slug;
            $base = $slug;
            $n = 0;
            while (DB::table('event_instances')->where('slug', $slug)->exists()) {
                $n++;
                $slug = $base.'-'.$n;
            }
            DB::table('event_instances')->insert([
                'event_id' => $leg->id,
                'name' => $leg->name,
                'desc' => $leg->desc,
                'organization_id' => $leg->organization_id,
                'is_public' => (bool) $leg->is_public,
                'starts_at' => $stamp->copy()->addDay(),
                'ends_at' => $stamp->copy()->addDays(2),
                'logo_path' => $leg->logo_path,
                'slug' => $slug,
                'created_by' => $leg->created_by,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);
        }

        $tagRows = DB::table('event_tag')->get();
        DB::table('event_tag')->delete();
        $seen = [];
        $tagStamp = now();
        foreach ($tagRows as $row) {
            $instIds = DB::table('event_instances')->where('event_id', $row->event_id)->pluck('id');
            foreach ($instIds as $iid) {
                $key = $iid.'-'.$row->tag_id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                DB::table('event_tag')->insert([
                    'event_id' => $iid,
                    'tag_id' => $row->tag_id,
                    'created_at' => $tagStamp,
                    'updated_at' => $tagStamp,
                ]);
            }
        }

        $wishRows = DB::table('user_event_wishlist')->get();
        DB::table('user_event_wishlist')->delete();
        foreach ($wishRows as $w) {
            $instIds = DB::table('event_instances')->where('event_id', $w->event_id)->pluck('id');
            foreach ($instIds as $iid) {
                DB::table('user_event_wishlist')->insertOrIgnore([
                    'user_id' => $w->user_id,
                    'event_id' => $iid,
                    'created_at' => $w->created_at,
                    'updated_at' => $w->updated_at,
                ]);
            }
        }

        Schema::table('event_instances', function (Blueprint $table) {
            $table->dropColumn('event_id');
        });

        Schema::rename('event_instances', 'events');

        Schema::table('slots', function (Blueprint $table) {
            $table->renameColumn('event_instance_id', 'event_id');
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->renameColumn('event_instance_id', 'event_id');
        });

        Schema::drop('events_legacy');

        Schema::table('event_tag', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
        Schema::table('user_event_wishlist', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
        Schema::table('slots', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Irreversible data merge; restore from backup if needed.
    }

    private function dropMergeForeignKeys(): void
    {
        Schema::table('event_tag', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });
        Schema::table('user_event_wishlist', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });
        Schema::table('event_instances', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });
        Schema::table('slots', function (Blueprint $table) {
            $table->dropForeign(['event_instance_id']);
        });
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropForeign(['event_instance_id']);
        });
    }
};

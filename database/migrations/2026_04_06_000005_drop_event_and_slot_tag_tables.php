<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('event_tag');
        Schema::dropIfExists('slot_tag');
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_tag')) {
            Schema::create('event_tag', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['event_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable('slot_tag')) {
            Schema::create('slot_tag', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['slot_id', 'tag_id']);
            });
        }
    }
};

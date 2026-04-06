<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tag_contexts')) {
            return;
        }

        Schema::create('tag_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('context_type', 100);
            $table->unsignedBigInteger('context_id');
            $table->timestamps();

            // Fast reverse lookup: context -> tags.
            $table->index(['context_type', 'context_id'], 'tag_contexts_context_lookup_idx');
            // Fast tag-centric lookup with type filter.
            $table->index(['tag_id', 'context_type'], 'tag_contexts_tag_type_idx');
            // Prevent duplicate bindings.
            $table->unique(['tag_id', 'context_type', 'context_id'], 'tag_contexts_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_contexts');
    }
};

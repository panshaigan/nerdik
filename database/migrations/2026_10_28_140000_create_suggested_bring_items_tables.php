<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggested_bring_items', function (Blueprint $table) {
            $table->id();
            $table->string('bringable_type');
            $table->unsignedBigInteger('bringable_id');
            $table->string('label', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('host_provides')->default(false);
            $table->timestamps();

            $table->index(['bringable_type', 'bringable_id', 'sort_order']);
        });

        Schema::create('activity_suggested_bring_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suggested_bring_item_id')->constrained()->cascadeOnDelete();
            $table->unique(['activity_id', 'suggested_bring_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_suggested_bring_provisions');
        Schema::dropIfExists('suggested_bring_items');
    }
};

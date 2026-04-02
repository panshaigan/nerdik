<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('slot_tag')) {
            Schema::create('slot_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['slot_id', 'tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_tag');
    }
};

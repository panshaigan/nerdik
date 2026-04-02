<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('slot_activity_type')) {
            Schema::create('slot_activity_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('slot_id')->constrained()->cascadeOnDelete();

                $table->string('activity_type', 50);
                $table->index('activity_type');

                $table->timestamps();

                $table->unique(['slot_id', 'activity_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_activity_type');
    }
};

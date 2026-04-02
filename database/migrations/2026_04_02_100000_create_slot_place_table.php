<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_place', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('slots')->cascadeOnDelete();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('slot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_place');
    }
};

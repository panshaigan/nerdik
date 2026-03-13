<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('slots')) {
            Schema::create('slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_instance_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
                $table->boolean('requires_approval')->default(false);
                $table->unsignedInteger('max_capacity')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};

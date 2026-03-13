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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // rpg, board, lecture, etc.
            $table->unsignedInteger('min_participants')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->unsignedInteger('age_limit')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_restricted')->default(false);
            $table->unsignedInteger('signoff_deadline_hours')->nullable();
            $table->string('status')->default('planned'); // planned, cancelled, finished etc.
            $table->string('logo_path')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('open_for_observers')->default(false);
            $table->string('slug')->unique();
            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

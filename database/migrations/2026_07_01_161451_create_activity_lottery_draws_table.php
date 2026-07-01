<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_lottery_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 32);
            $table->foreignId('enrollment_window_id')->nullable()->constrained('event_enrollment_windows')->nullOnDelete();
            $table->timestamp('drawn_at');
            $table->timestamps();

            $table->unique(['activity_id', 'trigger', 'enrollment_window_id'], 'activity_lottery_draws_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_lottery_draws');
    }
};

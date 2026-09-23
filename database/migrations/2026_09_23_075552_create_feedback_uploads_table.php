<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('feedback_id')->nullable()->constrained('feedback')->nullOnDelete();
            $table->string('path');
            $table->string('session_hash', 64);
            $table->string('ip_hash', 64);
            $table->unsignedBigInteger('bytes');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['session_hash', 'feedback_id']);
            $table->index(['ip_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_uploads');
    }
};

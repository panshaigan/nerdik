<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('status', 32)->default('pending');
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('message', 500)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->string('resolution_outcome', 32)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['recipient_id', 'status']);
            $table->index(['requester_id', 'status']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                CREATE UNIQUE INDEX user_requests_pending_unique_general
                ON user_requests (type, requester_id, recipient_id, subject_type, subject_id)
                WHERE status = 'pending' AND recipient_id IS NOT NULL
            ");
            DB::statement("
                CREATE UNIQUE INDEX user_requests_pending_organizer_flag
                ON user_requests (type, requester_id)
                WHERE status = 'pending' AND recipient_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS user_requests_pending_unique_general');
            DB::statement('DROP INDEX IF EXISTS user_requests_pending_organizer_flag');
        }

        Schema::dropIfExists('user_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('activities', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('ends_at');
                $table->index('cancelled_at');
            }

            if (! Schema::hasColumn('activities', 'cancelled_by')) {
                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->after('cancelled_at')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('cancelled_by');
            }

            if (! Schema::hasColumn('activities', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }

            if (Schema::hasColumn('activities', 'cancelled_by')) {
                $table->dropIndex(['cancelled_by']);
                $table->dropForeign(['cancelled_by']);
                $table->dropColumn('cancelled_by');
            }

            if (Schema::hasColumn('activities', 'cancelled_at')) {
                $table->dropIndex(['cancelled_at']);
                $table->dropColumn('cancelled_at');
            }
        });
    }
};

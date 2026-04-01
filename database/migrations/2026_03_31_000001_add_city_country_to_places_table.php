<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            if (! Schema::hasColumn('places', 'city')) {
                $table->string('city')->nullable()->after('name');
            }
            if (! Schema::hasColumn('places', 'country')) {
                $table->string('country')->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            if (Schema::hasColumn('places', 'country')) {
                $table->dropColumn('country');
            }
            if (Schema::hasColumn('places', 'city')) {
                $table->dropColumn('city');
            }
        });
    }
};

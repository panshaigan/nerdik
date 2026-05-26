<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('logo_source', ['default', 'upload'])->nullable()->after('logo_path');
            $table->unsignedBigInteger('listing_media_id')->nullable()->after('logo_source');
            $table->foreign('listing_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['listing_media_id']);
            $table->dropColumn(['logo_source', 'listing_media_id']);
        });
    }
};

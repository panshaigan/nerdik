<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->enum('logo_source', ['generated', 'upload'])->default('generated')->after('logo_path');
            $table->string('logo_bg_color', 7)->nullable()->after('logo_source');
            $table->string('logo_text_color', 7)->nullable()->after('logo_bg_color');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['logo_source', 'logo_bg_color', 'logo_text_color']);
        });
    }
};

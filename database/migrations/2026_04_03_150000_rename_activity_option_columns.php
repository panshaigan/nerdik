<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One rename per statement for broad DB compatibility.
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('signoff_deadline_hours', 'cancellation_deadline_in_hours'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('is_restricted', 'requires_approval'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('age_limit', 'minimum_age'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('passive_host', 'is_host_passive'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('open_for_observers', 'allows_observers'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('duration_minutes', 'duration_in_minutes'));
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('cancellation_deadline_in_hours', 'signoff_deadline_hours'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('requires_approval', 'is_restricted'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('minimum_age', 'age_limit'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('is_host_passive', 'passive_host'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('allows_observers', 'open_for_observers'));
        Schema::table('activities', fn (Blueprint $table) => $table->renameColumn('duration_in_minutes', 'duration_minutes'));
    }
};

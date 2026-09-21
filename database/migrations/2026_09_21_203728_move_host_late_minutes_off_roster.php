<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Host late used to upsert an activity_user row, which inflated signup counts.
        // Move those values onto the activity and drop the accidental host enrollments.
        $hostLateRows = DB::table('activity_user as au')
            ->join('activities as a', 'a.id', '=', 'au.activity_id')
            ->whereColumn('au.user_id', 'a.created_by')
            ->whereNotNull('au.late_minutes')
            ->select('au.id', 'au.activity_id', 'au.late_minutes')
            ->get();

        foreach ($hostLateRows as $row) {
            DB::table('activities')
                ->where('id', $row->activity_id)
                ->whereNull('host_late_minutes')
                ->update(['host_late_minutes' => $row->late_minutes]);

            DB::table('activity_user')->where('id', $row->id)->delete();
        }
    }

    public function down(): void
    {
        //
    }
};

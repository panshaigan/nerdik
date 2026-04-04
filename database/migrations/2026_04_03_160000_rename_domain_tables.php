<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('attached_tags', 'tag_attachments');
        Schema::rename('event_signup_periods', 'event_enrollment_windows');
        Schema::rename('activity_participants', 'activity_user');
        Schema::rename('activity_proposal_slots', 'activity_proposal_slot');
        Schema::rename('slot_activity_type', 'activity_type_slot');
        Schema::rename('slot_place', 'place_slot');
        Schema::rename('user_activity_wishlist', 'user_activity_wishes');
        Schema::rename('user_event_wishlist', 'user_event_wishes');
    }

    public function down(): void
    {
        Schema::rename('tag_attachments', 'attached_tags');
        Schema::rename('event_enrollment_windows', 'event_signup_periods');
        Schema::rename('activity_user', 'activity_participants');
        Schema::rename('activity_proposal_slot', 'activity_proposal_slots');
        Schema::rename('activity_type_slot', 'slot_activity_type');
        Schema::rename('place_slot', 'slot_place');
        Schema::rename('user_activity_wishes', 'user_activity_wishlist');
        Schema::rename('user_event_wishes', 'user_event_wishlist');
    }
};

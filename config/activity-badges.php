<?php

use App\Models\TagCategory;

/**
 * Single source of truth for activity badge presentation site-wide.
 *
 * chip_order: universal display order (only chips that exist and are allowed for a surface are shown).
 * surfaces: which chip groups appear per UI surface. tag_category_keys null = all taxonomy tags on the activity.
 *
 * Allowed tone names (case-insensitive): primary, secondary, accent, neutral, info, success, warning, error.
 * You may also use an int matching App\Enums\BadgeSemantic.
 *
 * Icon names use the same format as `<x-icon name="...">` (e.g. "o-tag", "o-clock").
 */
return [

    'chip_order' => [
        'meta:minimum_age',
        'activity_type',
        'tags:'.TagCategory::KEY_GAME,
        'tags:'.TagCategory::KEY_GENRE,
        'tags:'.TagCategory::KEY_SETTING,
        'tags:'.TagCategory::KEY_MECHANIC,
        'tags:'.TagCategory::KEY_TOPIC,
        'tags:'.TagCategory::KEY_FORMAT,
        'tags:'.TagCategory::KEY_OTHER,
        'tags:'.TagCategory::KEY_TRIGGER,
        'meta:requires_approval',
        'meta:allows_observers',

    ],

    'surfaces' => [
        'event_slot' => [
            'activity_type' => true,
            'tag_category_keys' => [
                TagCategory::KEY_GAME,
                TagCategory::KEY_GENRE,
                TagCategory::KEY_TOPIC,
            ],
            'requires_approval' => false,
            'allows_observers' => false,
            'minimum_age' => true,
        ],
        'activity_hero' => [
            'activity_type' => false,
            'tag_category_keys' => [
                TagCategory::KEY_GAME,
                TagCategory::KEY_GENRE,
                TagCategory::KEY_SETTING,
                TagCategory::KEY_MECHANIC,
                TagCategory::KEY_TOPIC,
                TagCategory::KEY_FORMAT,
                TagCategory::KEY_OTHER,
                TagCategory::KEY_TRIGGER,
            ],
            'requires_approval' => true,
            'allows_observers' => true,
            'minimum_age' => true,
        ],
        'browse_card' => [
            'activity_type' => true,
            'tag_category_keys' => [
                TagCategory::KEY_GAME,
                TagCategory::KEY_GENRE,
                TagCategory::KEY_TOPIC,
            ],
            'requires_approval' => false,
            'allows_observers' => false,
            'minimum_age' => true,
        ],
        'event_proposal' => [
            'activity_type' => true,
            'tag_category_keys' => [
                TagCategory::KEY_GAME,
                TagCategory::KEY_GENRE,
                TagCategory::KEY_TOPIC,
                TagCategory::KEY_FORMAT,
                TagCategory::KEY_OTHER,
                TagCategory::KEY_TRIGGER,
            ],
            'requires_approval' => true,
            'allows_observers' => true,
            'minimum_age' => true,
        ],
    ],

    'semantic_by_tag_category' => [
        TagCategory::KEY_GAME     => 'neutral',   // was primary
        TagCategory::KEY_GENRE    => 'neutral',   // was primary
        TagCategory::KEY_SETTING  => 'neutral',   // was primary
        TagCategory::KEY_MECHANIC => 'neutral',   // was primary
        TagCategory::KEY_FORMAT   => 'neutral', // was secondary
        TagCategory::KEY_OTHER    => 'neutral', // was secondary
        TagCategory::KEY_TOPIC    => 'neutral', // was secondary
        TagCategory::KEY_TRIGGER  => 'warning',   // keep
    ],

    'semantic_by_kind' => [
        'taxonomy_tag'       => 'neutral',   // was primary
        'activity_type'      => 'info',    // was info → teal is more distinctive
        'minimum_age'        => 'warning',   // keep
        'requires_approval'  => 'neutral',   // was accent → needs more attention
        'allows_observers'   => 'neutral',   // was accent → it's just a property
    ],

    'icon_by_tag_category' => [
        TagCategory::KEY_GAME     => 'o-puzzle-piece',
        TagCategory::KEY_GENRE    => 'o-book-open',
        TagCategory::KEY_SETTING  => 'o-map-pin',
        TagCategory::KEY_MECHANIC => 'o-cog-6-tooth',
        TagCategory::KEY_FORMAT   => 'o-squares-2x2',
        TagCategory::KEY_OTHER    => 'o-tag',
        TagCategory::KEY_TOPIC    => 'o-chat-bubble-left-right',
        TagCategory::KEY_TRIGGER  => 'o-exclamation-triangle',
    ],

    'icon_by_kind' => [
        'taxonomy_tag'      => 'o-tag',
        'activity_type'     => 'o-star',
        'minimum_age'       => 'o-clock',
        'requires_approval' => 'o-shield-check',
        'allows_observers'  => 'o-eye',
    ],
];

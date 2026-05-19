<?php

use App\Models\TagCategory;

return [

    /*
    |--------------------------------------------------------------------------
    | Browse tag suggestion ordering
    |--------------------------------------------------------------------------
    |
    | category_order: display order for tag groups in the browse search dropdown.
    | hidden_category_keys_on_empty_search: category keys omitted until the user types.
    |
    */
    'tag_suggestions' => [
        'category_order' => [
            TagCategory::KEY_GAME,
            TagCategory::KEY_SETTING,
            TagCategory::KEY_GENRE,
            TagCategory::KEY_FORMAT,
            TagCategory::KEY_OTHER,
            TagCategory::KEY_MECHANIC,
            TagCategory::KEY_TOPIC,
            TagCategory::KEY_TRIGGER,
        ],

        'hidden_category_keys_on_empty_search' => [
            TagCategory::KEY_TRIGGER,
        ],
    ],

];

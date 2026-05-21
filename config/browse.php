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
    | max_per_category: maximum tag suggestions shown per category in the dropdown.
    | exclude_category_keys_from_preload: categories omitted from server preload (triggers still searchable when typing).
    | preload_per_category: top N popular tags per category sent on page load.
    | search_limit: maximum tags returned from server search while typing.
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

        'exclude_category_keys_from_preload' => [
            TagCategory::KEY_TRIGGER,
        ],

        'max_per_category' => 7,
        'preload_per_category' => 7,
        'search_limit' => 30,
    ],

];

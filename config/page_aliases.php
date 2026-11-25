<?php

declare(strict_types=1);

return [
    'max_pending_requests_per_user' => (int) env('PAGE_ALIAS_MAX_PENDING_REQUESTS_PER_USER', 3),

    /**
     * Names not registered as Laravel routes but still must not become aliases.
     *
     * @var list<string>
     */
    'reserved' => [
        'admin',
        'build',
        'favicon.ico',
        'livewire',
        'pulse',
        'storage',
        'telescope',
        'up',
        'vendor',
    ],
];

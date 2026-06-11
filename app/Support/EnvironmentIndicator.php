<?php

declare(strict_types=1);

namespace App\Support;

final class EnvironmentIndicator
{
    /**
     * @return array{label: string, color: string}|null
     */
    public static function definition(): ?array
    {
        if (app()->environment('local')) {
            return [
                'label' => 'DEV',
                'color' => '#16a34a',
            ];
        }

        if (app()->environment('staging')) {
            return [
                'label' => 'STAGING',
                'color' => '#d97706',
            ];
        }

        return null;
    }
}

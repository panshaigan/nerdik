<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedbackStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return __('feedback.statuses.'.$this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}

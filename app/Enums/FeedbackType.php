<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedbackType: string
{
    case Question = 'question';
    case Feature = 'feature';
    case Bug = 'bug';
    case Problem = 'problem';
    case Other = 'other';

    public function label(): string
    {
        return __('feedback.types.'.$this->value);
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

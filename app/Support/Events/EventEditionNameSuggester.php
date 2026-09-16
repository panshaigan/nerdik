<?php

declare(strict_types=1);

namespace App\Support\Events;

/**
 * Suggests the next edition name when duplicating an event.
 * Bumps trailing Arabic or Roman numerals; otherwise returns null so callers
 * can fall back to the generic duplicate suffix.
 */
final class EventEditionNameSuggester
{
    private const ROMAN_MAP = [
        'M' => 1000,
        'CM' => 900,
        'D' => 500,
        'CD' => 400,
        'C' => 100,
        'XC' => 90,
        'L' => 50,
        'XL' => 40,
        'X' => 10,
        'IX' => 9,
        'V' => 5,
        'IV' => 4,
        'I' => 1,
    ];

    public function suggest(string $name): ?string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(.*?)(?:\s+)(\d+)$/u', $trimmed, $matches) === 1) {
            $base = rtrim($matches[1]);
            $next = ((int) $matches[2]) + 1;

            return $base === '' ? (string) $next : $base.' '.$next;
        }

        if (preg_match('/^(.*?)(?:\s+)([IVXLCDM]+)$/ui', $trimmed, $matches) === 1) {
            $roman = strtoupper($matches[2]);
            $value = $this->romanToInt($roman);
            if ($value === null) {
                return null;
            }

            $base = rtrim($matches[1]);
            $nextRoman = $this->intToRoman($value + 1);

            return $base === '' ? $nextRoman : $base.' '.$nextRoman;
        }

        return null;
    }

    private function romanToInt(string $roman): ?int
    {
        if ($roman === '' || preg_match('/^[IVXLCDM]+$/', $roman) !== 1) {
            return null;
        }

        $total = 0;
        $i = 0;
        $length = strlen($roman);

        while ($i < $length) {
            $matched = false;
            foreach (self::ROMAN_MAP as $glyph => $value) {
                $glyphLength = strlen($glyph);
                if (substr($roman, $i, $glyphLength) === $glyph) {
                    $total += $value;
                    $i += $glyphLength;
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return null;
            }
        }

        return $total > 0 ? $total : null;
    }

    private function intToRoman(int $number): string
    {
        if ($number < 1) {
            return 'I';
        }

        $result = '';
        foreach (self::ROMAN_MAP as $glyph => $value) {
            while ($number >= $value) {
                $result .= $glyph;
                $number -= $value;
            }
        }

        return $result;
    }
}

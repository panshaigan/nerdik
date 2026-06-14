<?php

declare(strict_types=1);

namespace App\Support\Media;

final class ImageFormatCapabilities
{
    public static function supportsAvif(): bool
    {
        return function_exists('imageavif');
    }

    /**
     * @param  list<string>  $formatNames
     * @return list<string>
     */
    public static function filterSupportedFormatNames(array $formatNames): array
    {
        if (self::supportsAvif()) {
            return $formatNames;
        }

        return array_values(array_filter(
            $formatNames,
            fn (string $name): bool => $name !== 'avif',
        ));
    }

    /**
     * @return list<array{name: string, extension: string}>
     */
    public static function productionConversionFormats(): array
    {
        return self::mapFormatNamesToDefinitions(
            self::filterSupportedFormatNames(['avif', 'webp', 'jpeg']),
        );
    }

    /**
     * @param  list<string>  $formatNames
     * @return list<array{name: string, extension: string}>
     */
    public static function mapFormatNamesToDefinitions(array $formatNames): array
    {
        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'extension' => $name === 'jpeg' ? 'jpg' : $name,
            ],
            $formatNames,
        );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class UiTranslationParityTest extends TestCase
{
    #[Test]
    public function english_and_polish_ui_files_have_identical_key_sets(): void
    {
        $englishKeys = $this->flattenTranslationKeys(include lang_path('en/ui.php'));
        $polishKeys = $this->flattenTranslationKeys(include lang_path('pl/ui.php'));

        sort($englishKeys);
        sort($polishKeys);

        $this->assertSame($englishKeys, $polishKeys);
    }

    #[Test]
    public function english_ui_keys_are_referenced_in_codebase(): void
    {
        $englishKeys = $this->flattenTranslationKeys(include lang_path('en/ui.php'));
        $referencedKeys = $this->referencedUiTranslationKeys();
        $unusedKeys = array_values(array_filter(
            $englishKeys,
            fn (string $key): bool => ! $this->uiKeyIsReferenced($key, $referencedKeys),
        ));
        sort($unusedKeys);

        $this->assertSame(
            [],
            $unusedKeys,
            'Unused ui.php keys with no references in app, views, routes, or tests: '.json_encode($unusedKeys, JSON_PRETTY_PRINT),
        );
    }

    #[Test]
    public function public_blade_views_only_reference_existing_english_ui_keys(): void
    {
        $englishKeys = array_flip($this->flattenTranslationKeys(include lang_path('en/ui.php')));
        $missingKeys = [];

        foreach ($this->publicBladeFiles() as $file) {
            $contents = file_get_contents($file);
            preg_match_all("/__\\(['\"](ui\\.[^'\"]+)['\"]/", $contents, $matches);

            foreach ($matches[1] as $key) {
                if (str_ends_with($key, '.')) {
                    continue;
                }

                $lookupKey = str_starts_with($key, 'ui.') ? substr($key, 3) : $key;

                if (! isset($englishKeys[$lookupKey])) {
                    $missingKeys[$key][] = $this->relativeViewPath($file);
                }
            }
        }

        ksort($missingKeys);

        $this->assertSame(
            [],
            $missingKeys,
            'Missing ui.php keys referenced from public views: '.json_encode($missingKeys, JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return list<string>
     */
    private function flattenTranslationKeys(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenTranslationKeys($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function publicBladeFiles(): array
    {
        $files = [];
        $viewsRoot = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }

            $relativePath = str_replace($viewsRoot.'/', '', $file->getPathname());

            if (str_starts_with($relativePath, 'vendor/')) {
                continue;
            }

            if (str_starts_with($relativePath, 'tags/') && ! str_starts_with($relativePath, 'tags/partials/selector.blade.php')) {
                continue;
            }

            if (str_starts_with($relativePath, 'places/')) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function relativeViewPath(string $absolutePath): string
    {
        return str_replace(resource_path('views').'/', '', $absolutePath);
    }

    /**
     * @return array{static: array<string, true>, dynamic_prefixes: array<string, true>}
     */
    private function referencedUiTranslationKeys(): array
    {
        $static = [];
        $dynamicPrefixes = [];

        foreach ($this->uiTranslationScanRoots() as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! $this->isUiTranslationScannableFile($file->getPathname())) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match_all("/__\\(['\"](ui\\.[^'\"]+)['\"]/", $contents, $matches)) {
                    foreach ($matches[1] as $key) {
                        if (str_ends_with($key, '.')) {
                            continue;
                        }

                        $lookupKey = str_starts_with($key, 'ui.') ? substr($key, 3) : $key;
                        $static[$lookupKey] = true;
                    }
                }

                foreach ($this->dynamicUiTranslationPrefixes() as $needle => $prefix) {
                    if (str_contains($contents, "ui.{$needle}")) {
                        $dynamicPrefixes[$prefix] = true;
                    }
                }

                if (str_contains($contents, "profile.notifications.'.$")) {
                    foreach (['activity_group', 'event_group', 'requests_group', 'scheduled_group'] as $groupKey) {
                        $static["profile.notifications.{$groupKey}"] = true;
                    }
                }
            }
        }

        return [
            'static' => $static,
            'dynamic_prefixes' => $dynamicPrefixes,
        ];
    }

    /**
     * @param  array{static: array<string, true>, dynamic_prefixes: array<string, true>}  $referencedKeys
     */
    private function uiKeyIsReferenced(string $key, array $referencedKeys): bool
    {
        if (isset($referencedKeys['static'][$key])) {
            return true;
        }

        foreach ($referencedKeys['dynamic_prefixes'] as $prefix => $_) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function uiTranslationScanRoots(): array
    {
        return [
            app_path(),
            resource_path('views'),
            base_path('routes'),
            base_path('tests'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dynamicUiTranslationPrefixes(): array
    {
        return [
            'activities.types.' => 'activities.types.',
            'activities.host_title.' => 'activities.host_title.',
            'profile.notifications.keys.' => 'profile.notifications.keys.',
            'welcome.' => 'welcome.',
        ];
    }

    private function isUiTranslationScannableFile(string $path): bool
    {
        if (str_ends_with($path, '.blade.php')) {
            return true;
        }

        return str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FrontendModuleSplittingTest extends TestCase
{
    public function test_heavy_page_modules_are_dynamically_imported_from_the_application_entrypoint(): void
    {
        $source = (string) file_get_contents(resource_path('js/app.js'));

        foreach ([
            './image-cropper',
            './maps-init',
            './tags-selector',
            './activity-tag-picker',
            './datetime-picker',
            './browse-date-range-picker',
            './echo',
            './sentry',
        ] as $module) {
            $this->assertStringContainsString("import('{$module}')", $source);
            $this->assertStringNotContainsString("import '{$module}';", $source);
        }

        $this->assertStringNotContainsString("import './bootstrap';", $source);
        $this->assertStringNotContainsString("from './sentry'", $source);
    }

    public function test_realtime_and_feature_modules_are_guarded_by_page_markers(): void
    {
        $source = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('document.body?.dataset?.userId', $source);
        $this->assertStringContainsString('[data-image-crop-dropzone]', $source);
        $this->assertStringContainsString('[data-browse-date-range]', $source);
        $this->assertStringContainsString('dateRangeLoaderBound', $source);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $source);
        $this->assertStringContainsString('[data-activity-tag-picker]', $source);
        $this->assertStringContainsString('new MutationObserver(queueFeatureBoot)', $source);
    }

    public function test_flatpickr_styles_are_owned_by_the_date_range_chunk(): void
    {
        $entrypoint = (string) file_get_contents(resource_path('js/app.js'));
        $dateRangePicker = (string) file_get_contents(resource_path('js/browse-date-range-picker.js'));

        $this->assertStringNotContainsString('flatpickr.min.css', $entrypoint);
        $this->assertStringContainsString("import 'flatpickr/dist/flatpickr.min.css';", $dateRangePicker);
    }
}

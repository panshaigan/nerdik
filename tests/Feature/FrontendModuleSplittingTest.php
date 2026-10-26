<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('infra')]
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
            './tinymce-field-chrome',
            './echo',
            './sentry',
        ] as $module) {
            $this->assertStringContainsString("import('{$module}')", $source);
            $this->assertStringNotContainsString("import '{$module}';", $source);
        }

        $this->assertStringNotContainsString("import './bootstrap';", $source);
        $this->assertStringNotContainsString("from './sentry'", $source);
        $this->assertStringContainsString("import './browse-search-state';", $source);
        $this->assertStringNotContainsString("import('./browse-search-state')", $source);
        $this->assertStringNotContainsString('requestIdleCallback', $source);
        $this->assertStringContainsString('window.setTimeout(boot, 10000)', $source);
        $this->assertStringContainsString('tagSelectorLoaderBound', $source);
        $this->assertStringContainsString('tagSelectorNeedsImmediateBoot', $source);
    }

    public function test_realtime_and_feature_modules_are_guarded_by_page_markers(): void
    {
        $source = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('document.body?.dataset?.userId', $source);
        $this->assertStringContainsString('#ui-image-crop-modal', $source);
        $this->assertStringContainsString('form[data-activity-form]', $source);
        $this->assertStringContainsString('bootImageCropperFeature', $source);
        $this->assertStringContainsString('[data-browse-date-range]', $source);
        $this->assertStringContainsString('dateRangeLoaderBound', $source);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $source);
        $this->assertStringContainsString('[data-activity-tag-picker]', $source);
        $this->assertStringContainsString('new MutationObserver(queueFeatureBoot)', $source);
        $this->assertStringContainsString('tagSelectorLoaderBound', $source);
        $this->assertStringContainsString('pointerdown', $source);
        $this->assertStringContainsString('pointerenter', $source);
    }

    public function test_flatpickr_styles_are_owned_by_date_picker_chunks_not_entrypoint(): void
    {
        $entrypoint = (string) file_get_contents(resource_path('js/app.js'));
        $dateRangePicker = (string) file_get_contents(resource_path('js/browse-date-range-picker.js'));
        $datetimePicker = (string) file_get_contents(resource_path('js/datetime-picker.js'));

        $this->assertStringNotContainsString('flatpickr.min.css', $entrypoint);
        $this->assertStringContainsString("import 'flatpickr/dist/flatpickr.min.css';", $dateRangePicker);
        $this->assertStringContainsString("import '../css/vendor/flatpickr-theme.css';", $dateRangePicker);
        $this->assertStringContainsString("import 'flatpickr/dist/flatpickr.min.css';", $datetimePicker);
        $this->assertStringContainsString("import '../css/vendor/flatpickr-theme.css';", $datetimePicker);
        $this->assertStringContainsString('time_24hr:', $datetimePicker);
        $this->assertStringContainsString('disableMobile: true', $datetimePicker);
        $this->assertStringContainsString('appendTo: document.body', $datetimePicker);
        $this->assertStringContainsString("dateFormat: 'Y-m-d\\\\TH:i'", $datetimePicker);
        $this->assertStringContainsString('input[data-ui-datetime]', $entrypoint);
    }

    public function test_css_pipeline_splits_app_entry_and_limits_daisyui_and_mary_sources(): void
    {
        $appCss = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("@import './theme/tokens.css';", $appCss);
        $this->assertStringContainsString("@import './ui/browse.css';", $appCss);
        $this->assertStringContainsString('include:', $appCss);
        $this->assertStringNotContainsString('Components/**/*.php', $appCss);
        $this->assertStringContainsString('mary/src/View/Components/Button.php', $appCss);
    }

    public function test_feature_vendor_styles_load_from_js_chunks_not_app_entry(): void
    {
        $entrypoint = (string) file_get_contents(resource_path('js/app.js'));
        $appCss = (string) file_get_contents(resource_path('css/app.css'));
        $tinymceChrome = (string) file_get_contents(resource_path('js/tinymce-field-chrome.js'));
        $imageCropper = (string) file_get_contents(resource_path('js/image-cropper.js'));

        $this->assertStringNotContainsString("import './tinymce-field-chrome';", $entrypoint);
        $this->assertStringContainsString("import('./tinymce-field-chrome')", $entrypoint);
        $this->assertStringContainsString("import '../css/vendor/tinymce.css';", $tinymceChrome);
        $this->assertStringContainsString("import '../css/vendor/cropper-chrome.css';", $imageCropper);
        $this->assertStringNotContainsString('vendor/tinymce.css', $appCss);
        $this->assertStringNotContainsString('vendor/cropper-chrome.css', $appCss);
        $this->assertStringNotContainsString('vendor/flatpickr-theme.css', $appCss);
    }
}

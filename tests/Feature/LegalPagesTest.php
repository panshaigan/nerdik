<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Seo\Seo;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_privacy_page_renders_in_english(): void
    {
        app()->setLocale('en');

        $response = $this->get(route('privacy'));

        $response->assertOk();
        $response->assertSee(legal_replace(__('legal.privacy.title')), false);
        $response->assertSee('<title>'.Seo::pageTitle((string) __('legal.privacy.title')).'</title>', false);
        $response->assertSee('<meta name="description" content="'.e((string) __('ui.seo.privacy_description')).'">', false);
        $response->assertSee('<link rel="canonical" href="'.e(route('privacy')).'">', false);
    }

    public function test_terms_page_renders_in_english(): void
    {
        app()->setLocale('en');

        $response = $this->get(route('terms'));

        $response->assertOk();
        $response->assertSee(legal_replace(__('legal.terms.title')), false);
        $response->assertSee('<title>'.Seo::pageTitle((string) __('legal.terms.title')).'</title>', false);
    }

    public function test_privacy_page_renders_in_polish_when_locale_is_set(): void
    {
        $response = $this->withSession(['locale' => 'pl'])->get(route('privacy'));

        $response->assertOk();
        app()->setLocale('pl');
        $response->assertSee(legal_replace(__('legal.privacy.title')), false);
    }

    public function test_terms_page_renders_in_polish_when_locale_is_set(): void
    {
        $response = $this->withSession(['locale' => 'pl'])->get(route('terms'));

        $response->assertOk();
        app()->setLocale('pl');
        $response->assertSee(legal_replace(__('legal.terms.title')), false);
    }

    public function test_app_layout_footer_links_point_to_legal_routes_and_opens_contact_form(): void
    {
        $response = $this->get(route('privacy'));

        $response->assertOk();
        $response->assertSee('href="'.e(route('privacy')).'"', false);
        $response->assertSee('href="'.e(route('terms')).'"', false);
        $response->assertSee('open-feedback-modal', false);
        $response->assertSee(__('ui.footer.contact'), false);
    }
}

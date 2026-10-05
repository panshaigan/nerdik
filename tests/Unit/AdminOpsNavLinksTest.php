<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AdminOpsNavLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminOpsNavLinksTest extends TestCase
{
    #[Test]
    public function it_omits_the_environment_url_that_matches_the_current_app_host(): void
    {
        $this->setRequestUri('/events/demo?tab=plan');

        Config::set('app.url', 'https://nerdik.app');
        Config::set('app.environment_urls.production', 'https://nerdik.app');
        Config::set('app.environment_urls.staging', 'https://staging.nerdik.app');
        Config::set('app.environment_urls.development', 'http://localhost');
        Config::set('sentry.dashboard_url', null);
        Config::set('mail.support_mailbox_url', null);
        Config::set('umami.dashboard_url', null);
        Config::set('services.google.search_console_url', null);
        Config::set('mail.brevo_dashboard_url', null);
        Config::set('services.adminer.url', null);
        Config::set('services.hosting_manager.url', null);

        $urls = array_column(AdminOpsNavLinks::items(), 'url');

        $this->assertContains('https://staging.nerdik.app/events/demo?tab=plan', $urls);
        $this->assertContains('http://localhost/events/demo?tab=plan', $urls);
        $this->assertNotContains('https://nerdik.app/events/demo?tab=plan', $urls);
        $this->assertNotContains('https://nerdik.app', $urls);
    }

    #[Test]
    public function it_keeps_the_current_path_on_environment_switch_links(): void
    {
        $this->setRequestUri('/organizations/acme/edit');

        Config::set('app.url', 'http://localhost');
        Config::set('app.environment_urls.production', 'https://nerdik.app');
        Config::set('app.environment_urls.staging', 'https://staging.nerdik.app');
        Config::set('app.environment_urls.development', 'http://localhost');
        Config::set('sentry.dashboard_url', null);
        Config::set('mail.support_mailbox_url', null);
        Config::set('umami.dashboard_url', null);
        Config::set('services.google.search_console_url', null);
        Config::set('mail.brevo_dashboard_url', null);
        Config::set('services.adminer.url', null);
        Config::set('services.hosting_manager.url', null);

        $urls = array_column(AdminOpsNavLinks::items(), 'url');

        $this->assertContains('https://nerdik.app/organizations/acme/edit', $urls);
        $this->assertContains('https://staging.nerdik.app/organizations/acme/edit', $urls);
        $this->assertNotContains('http://localhost/organizations/acme/edit', $urls);
    }

    #[Test]
    public function it_groups_links_in_a_logical_order(): void
    {
        Config::set('app.url', 'http://localhost');
        Config::set('app.environment_urls.production', 'https://nerdik.app');
        Config::set('app.environment_urls.staging', 'https://staging.nerdik.app');
        Config::set('app.environment_urls.development', 'http://localhost');
        Config::set('sentry.dashboard_url', 'https://sentry.example/org/project');
        Config::set('mail.support_mailbox_url', 'https://mail.example/inbox');
        Config::set('umami.dashboard_url', 'https://umami.example/website');
        Config::set('services.google.search_console_url', 'https://search.google.example/console');
        Config::set('mail.brevo_dashboard_url', 'https://brevo.example/logs');
        Config::set('services.adminer.url', 'https://adminer.example');
        Config::set('services.hosting_manager.url', 'https://hosting.example/dashboard');

        $groups = AdminOpsNavLinks::groups();

        $this->assertSame([
            null,
            __('ui.nav.admin_group_environments'),
            __('ui.nav.admin_group_monitoring'),
            __('ui.nav.admin_group_email'),
            __('ui.nav.admin_group_infrastructure'),
        ], array_column($groups, 'label'));

        $labels = array_column(AdminOpsNavLinks::items(), 'label');

        $this->assertSame([
            __('ui.nav.admin_panel'),
            __('ui.nav.pulse'),
            __('ui.nav.production'),
            __('ui.nav.staging'),
            __('ui.nav.sentry'),
            __('ui.nav.umami'),
            __('ui.nav.google_search_console'),
            __('ui.nav.pagespeed'),
            __('ui.nav.support'),
            __('ui.nav.brevo'),
            __('ui.nav.adminer'),
            __('ui.nav.hosting_manager'),
        ], $labels);
    }

    #[Test]
    public function it_builds_pagespeed_link_for_production_url_and_current_route(): void
    {
        $this->setRequestUri('/search');

        Config::set('app.url', 'http://localhost');
        Config::set('app.environment_urls.production', 'https://nerdik.app');
        Config::set('app.environment_urls.staging', null);
        Config::set('app.environment_urls.development', null);
        Config::set('sentry.dashboard_url', null);
        Config::set('mail.support_mailbox_url', null);
        Config::set('umami.dashboard_url', null);
        Config::set('services.google.search_console_url', null);
        Config::set('mail.brevo_dashboard_url', null);
        Config::set('services.adminer.url', null);
        Config::set('services.hosting_manager.url', null);

        $urls = array_column(AdminOpsNavLinks::items(), 'url');

        $this->assertContains(
            'https://pagespeed.web.dev/analysis/https-nerdik-app-search?form_factor=mobile',
            $urls,
        );
    }

    #[Test]
    public function it_omits_pagespeed_link_when_production_url_is_unconfigured(): void
    {
        Config::set('app.environment_urls.production', null);
        Config::set('app.environment_urls.staging', null);
        Config::set('app.environment_urls.development', null);
        Config::set('sentry.dashboard_url', null);
        Config::set('mail.support_mailbox_url', null);
        Config::set('umami.dashboard_url', null);
        Config::set('services.google.search_console_url', null);
        Config::set('mail.brevo_dashboard_url', null);
        Config::set('services.adminer.url', null);
        Config::set('services.hosting_manager.url', null);

        $labels = array_column(AdminOpsNavLinks::items(), 'label');

        $this->assertNotContains(__('ui.nav.pagespeed'), $labels);
    }

    #[Test]
    public function it_includes_hosting_manager_url_when_configured(): void
    {
        Config::set('app.url', 'https://nerdik.app');
        Config::set('app.environment_urls.production', null);
        Config::set('app.environment_urls.staging', null);
        Config::set('app.environment_urls.development', null);
        Config::set('sentry.dashboard_url', null);
        Config::set('mail.support_mailbox_url', null);
        Config::set('umami.dashboard_url', null);
        Config::set('services.google.search_console_url', null);
        Config::set('mail.brevo_dashboard_url', null);
        Config::set('services.adminer.url', null);
        Config::set('services.hosting_manager.url', 'https://hosting.example/dashboard');

        $urls = array_column(AdminOpsNavLinks::items(), 'url');

        $this->assertContains('https://hosting.example/dashboard', $urls);
    }

    private function setRequestUri(string $uri): void
    {
        $this->app->instance('request', Request::create($uri, 'GET'));
    }
}

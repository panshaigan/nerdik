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

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AdminOpsNavLinks;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminOpsNavLinksTest extends TestCase
{
    #[Test]
    public function it_omits_the_environment_url_that_matches_the_current_app_host(): void
    {
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

        $urls = array_column(AdminOpsNavLinks::items(), 'url');

        $this->assertContains('https://staging.nerdik.app', $urls);
        $this->assertContains('http://localhost', $urls);
        $this->assertNotContains('https://nerdik.app', $urls);
    }
}

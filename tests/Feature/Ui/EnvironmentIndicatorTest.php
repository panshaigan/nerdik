<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Support\EnvironmentIndicator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EnvironmentIndicatorTest extends TestCase
{
    #[Test]
    public function production_pages_do_not_render_environment_indicator(): void
    {
        $this->app['env'] = 'production';

        $this->get('/')
            ->assertOk()
            ->assertDontSee('data-ui="environment-indicator"', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-ui="environment-indicator"', false);
    }

    #[Test]
    public function local_environment_renders_dev_ribbon_on_welcome_page(): void
    {
        $this->app['env'] = 'local';

        $this->get('/')
            ->assertOk()
            ->assertSee('data-ui="environment-indicator"', false)
            ->assertSee('DEV', false);
    }

    #[Test]
    public function staging_environment_renders_staging_ribbon_on_login_page(): void
    {
        $this->app['env'] = 'staging';

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-ui="environment-indicator"', false)
            ->assertSee('STAGING', false);
    }

    #[Test]
    public function definition_returns_null_for_production_and_testing(): void
    {
        $this->app['env'] = 'production';
        $this->assertNull(EnvironmentIndicator::definition());

        $this->app['env'] = 'testing';
        $this->assertNull(EnvironmentIndicator::definition());
    }

    #[Test]
    public function definition_returns_labels_for_local_and_staging(): void
    {
        $this->app['env'] = 'local';
        $this->assertSame('DEV', EnvironmentIndicator::definition()['label']);

        $this->app['env'] = 'staging';
        $this->assertSame('STAGING', EnvironmentIndicator::definition()['label']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Notifications;

use App\Support\Notifications\RelativeAppUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RelativeAppUrlTest extends TestCase
{
    #[Test]
    public function it_normalizes_paths_without_leading_slash(): void
    {
        $this->assertSame('/search', RelativeAppUrl::normalize('search'));
    }

    #[Test]
    public function it_rejects_empty_and_protocol_relative_urls(): void
    {
        $this->assertNull(RelativeAppUrl::normalize(''));
        $this->assertNull(RelativeAppUrl::normalize('//evil.example'));
        $this->assertNull(RelativeAppUrl::normalize('https://evil.example'));
    }

    #[Test]
    public function it_strips_same_app_absolute_urls_to_path(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->assertSame('/dashboard', RelativeAppUrl::normalize('http://localhost/dashboard'));
        $this->assertNull(RelativeAppUrl::normalize('https://evil.example/dashboard'));
    }
}

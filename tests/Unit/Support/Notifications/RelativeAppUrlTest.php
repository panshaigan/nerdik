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
}

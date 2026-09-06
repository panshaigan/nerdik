<?php

namespace Tests\Unit\Enums;

use App\Enums\AppLocale;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppLocaleTest extends TestCase
{
    #[Test]
    public function other_returns_polish_when_current_is_english(): void
    {
        $this->assertSame(AppLocale::Pl, AppLocale::En->other());
    }

    #[Test]
    public function other_returns_english_when_current_is_polish(): void
    {
        $this->assertSame(AppLocale::En, AppLocale::Pl->other());
    }
}

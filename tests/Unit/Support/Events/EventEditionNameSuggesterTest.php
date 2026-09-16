<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Events;

use App\Support\Events\EventEditionNameSuggester;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventEditionNameSuggesterTest extends TestCase
{
    private EventEditionNameSuggester $suggester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suggester = new EventEditionNameSuggester;
    }

    #[Test]
    #[DataProvider('suggestionProvider')]
    public function it_suggests_next_edition_names(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->suggester->suggest($input));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function suggestionProvider(): array
    {
        return [
            'arabic' => ['Porzucane 2', 'Porzucane 3'],
            'roman' => ['Porzucane II', 'Porzucane III'],
            'roman i to ii' => ['Porzucane I', 'Porzucane II'],
            'no numeral' => ['Con Weekend', null],
            'empty' => ['', null],
        ];
    }
}

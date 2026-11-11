<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Support\PlayAreaLocationSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlayAreaLocationSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_joins_street_and_city(): void
    {
        $city = $this->createCity('Warszawa');

        $this->assertSame(
            'Przemysłowa 7, Warszawa',
            PlayAreaLocationSummary::line('Przemysłowa 7', $city),
        );
    }

    #[Test]
    public function it_returns_street_only_when_city_missing(): void
    {
        $this->assertSame('Przemysłowa 7', PlayAreaLocationSummary::line('Przemysłowa 7', null));
    }

    #[Test]
    public function it_returns_city_only_when_street_missing(): void
    {
        $city = $this->createCity('Warszawa');

        $this->assertSame('Warszawa', PlayAreaLocationSummary::line(null, $city));
    }

    #[Test]
    public function it_returns_null_when_both_missing(): void
    {
        $this->assertNull(PlayAreaLocationSummary::line(null, null));
        $this->assertNull(PlayAreaLocationSummary::line('  ', null));
    }

    #[Test]
    public function it_uses_locale_for_city_name(): void
    {
        $city = $this->createCity('Warsaw', 'Warszawa');

        $this->assertSame(
            'Main St, Warsaw',
            PlayAreaLocationSummary::line('Main St', $city, 'en'),
        );
        $this->assertSame(
            'Main St, Warszawa',
            PlayAreaLocationSummary::line('Main St', $city, 'pl'),
        );
    }

    private function createCity(string $enName, ?string $plName = null): City
    {
        $country = Country::query()->create(['iso_alpha2' => 'PL']);
        $city = City::factory()->create(['country_id' => $country->id]);

        CityTranslation::query()->create([
            'city_id' => $city->id,
            'locale' => 'en',
            'name' => $enName,
        ]);

        if ($plName !== null) {
            CityTranslation::query()->create([
                'city_id' => $city->id,
                'locale' => 'pl',
                'name' => $plName,
            ]);
        }

        return $city->load('translations');
    }
}

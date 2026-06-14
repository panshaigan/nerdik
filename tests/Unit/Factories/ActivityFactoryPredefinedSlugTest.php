<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Models\Activity;
use Database\Factories\ActivityFactory;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class ActivityFactoryPredefinedSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityTypeSeeder::class);
        ActivityFactory::resetPredefinedSequenceForTesting();
    }

    #[Test]
    public function predefined_name_pool_has_unique_slugs(): void
    {
        $names = $this->predefinedNames();

        $slugs = array_map(fn (string $name): string => Str::slug($name), $names);

        $this->assertGreaterThanOrEqual(310, count($names));
        $this->assertSame(count($names), count(array_unique($slugs)));
    }

    #[Test]
    public function it_creates_unique_slugs_for_a_batch_of_predefined_activities(): void
    {
        $activities = Activity::factory(30)->predefined()->create();

        $slugs = $activities->pluck('slug')->all();

        $this->assertSame(30, count(array_unique($slugs)));
    }

    #[Test]
    public function it_suffixes_slugs_when_predefined_names_repeat_across_batches(): void
    {
        $poolSize = count($this->predefinedNames());

        Activity::factory($poolSize)->predefined()->create();

        $repeatBatch = Activity::factory(5)->predefined()->create();

        $allSlugs = Activity::query()->pluck('slug')->all();

        $this->assertSame(count($allSlugs), count(array_unique($allSlugs)));

        $suffixedSlugs = $repeatBatch
            ->pluck('slug')
            ->filter(fn (string $slug): bool => (bool) preg_match('/-\d+$/', $slug));

        $this->assertCount(5, $suffixedSlugs);
    }

    /**
     * @return list<string>
     */
    private function predefinedNames(): array
    {
        $method = (new ReflectionClass(ActivityFactory::class))->getMethod('predefinedNames');
        $method->setAccessible(true);

        /** @var list<string> $names */
        $names = $method->invoke(null);

        return $names;
    }
}

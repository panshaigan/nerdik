<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Catalog;

use App\Actions\Catalog\SyncGameTagSuggestedBringCatalog;
use App\Models\SuggestedBringItem;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SyncGameTagSuggestedBringCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_catalog_lines_for_a_tag_by_english_label_when_id_mismatch(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->for($gameCategory, 'tagCategory')->create();
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Blades in the Dark',
        ]);

        $path = storage_path('framework/testing/game-tag-bring-sample.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, json_encode([
            'entries' => [
                [
                    'tag' => ['id' => 999999, 'label' => 'Blades in the Dark'],
                    'suggested_bring' => [
                        ['en' => '6d6', 'pl' => '6k6'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = app(SyncGameTagSuggestedBringCatalog::class)->importFromJsonFile($path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['skipped']);

        $item = SuggestedBringItem::query()
            ->where('bringable_type', 'tag')
            ->where('bringable_id', $tag->id)
            ->with('translations')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('6d6', $item->translations->firstWhere('locale', 'en')?->label);
        $this->assertSame('6k6', $item->translations->firstWhere('locale', 'pl')?->label);
    }

    #[Test]
    public function it_clears_existing_kit_when_catalog_entry_is_empty(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->for($gameCategory, 'tagCategory')->create();
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Ten Candles',
        ]);

        SuggestedBringItem::factory()
            ->state([
                'bringable_type' => 'tag',
                'bringable_id' => $tag->id,
            ])
            ->withTranslations(['en' => 'Old line'])
            ->create();

        $path = storage_path('framework/testing/game-tag-bring-empty.json');
        file_put_contents($path, json_encode([
            'entries' => [
                [
                    'tag' => ['id' => $tag->id, 'label' => 'Ten Candles'],
                    'suggested_bring' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        app(SyncGameTagSuggestedBringCatalog::class)->importFromJsonFile($path);

        $this->assertSame(0, SuggestedBringItem::query()->where('bringable_id', $tag->id)->count());
    }
}

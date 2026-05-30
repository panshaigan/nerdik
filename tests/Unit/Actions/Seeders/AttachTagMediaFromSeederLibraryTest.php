<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\AttachTagMediaFromSeederLibrary;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AttachTagMediaFromSeederLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $libraryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->libraryDirectory = storage_path('framework/testing/tag-images-'.uniqid());
        File::ensureDirectoryExists($this->libraryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->libraryDirectory);

        parent::tearDown();
    }

    #[Test]
    public function it_attaches_a_top_level_file_to_matching_tag_by_slug(): void
    {
        config(['media.test_profile' => 'full']);

        $tag = $this->createGenreTag('Fantasy', 'fantasy');
        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $target = $this->libraryDirectory.'/fantasy.jpg';
        copy($fixture, $target);

        app(AttachTagMediaFromSeederLibrary::class)($this->libraryDirectory, TagCategory::KEY_GENRE);

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);
        $this->assertStringContainsString('fantasy.jpg', $media->getCustomProperty('seed_source'));
    }

    #[Test]
    public function it_attaches_all_images_from_a_folder_to_the_matching_tag(): void
    {
        config(['media.test_profile' => 'full']);

        $tag = $this->createGenreTag('Space Opera', 'space-opera');
        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $folder = $this->libraryDirectory.'/61_space_opera';
        File::ensureDirectoryExists($folder);
        copy($fixture, $folder.'/61_space_opera_01.jpg');
        copy($fixture, $folder.'/61_space_opera_02.jpg');

        app(AttachTagMediaFromSeederLibrary::class)($this->libraryDirectory, TagCategory::KEY_GENRE);

        $this->assertCount(2, $tag->refresh()->getMedia('images'));
    }

    private function createGenreTag(string $label, string $slug): Tag
    {
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $tag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => $label,
            'slug' => $slug,
        ]);

        return $tag;
    }
}

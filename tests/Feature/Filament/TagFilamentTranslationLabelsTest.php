<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Tags\Pages\EditTag;
use App\Filament\Admin\Resources\Tags\Pages\ListTags;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagFilamentTranslationLabelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tags_table_shows_english_and_polish_translation_labels(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $tag->translations()->createMany([
            ['locale' => 'en', 'label' => 'Death'],
            ['locale' => 'pl', 'label' => 'Śmierć'],
        ]);

        Livewire::actingAs($admin)
            ->test(ListTags::class)
            ->assertTableColumnStateSet('label_en', 'Death', $tag)
            ->assertTableColumnStateSet('label_pl', 'Śmierć', $tag);
    }

    #[Test]
    public function tags_table_english_column_falls_back_to_polish_when_english_is_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $tag->translations()->create([
            'locale' => 'pl',
            'label' => 'Śmierć',
        ]);

        Livewire::actingAs($admin)
            ->test(ListTags::class)
            ->assertTableColumnStateSet('label_en', 'Śmierć', $tag)
            ->assertTableColumnStateSet('label_pl', 'Śmierć', $tag);
    }

    #[Test]
    public function edit_tag_page_title_shows_both_labels_when_they_differ(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $tag->translations()->createMany([
            ['locale' => 'en', 'label' => 'Death'],
            ['locale' => 'pl', 'label' => 'Śmierć'],
        ]);

        Livewire::actingAs($admin)
            ->test(EditTag::class, ['record' => $tag->id])
            ->assertSee('Edit Death / Śmierć');
    }

    #[Test]
    public function edit_tag_page_title_shows_single_label_when_only_one_locale_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $tag->translations()->create([
            'locale' => 'en',
            'label' => 'Death',
        ]);

        Livewire::actingAs($admin)
            ->test(EditTag::class, ['record' => $tag->id])
            ->assertSee('Edit Death')
            ->assertDontSee('Edit Death / Death');
    }

    #[Test]
    public function edit_tag_page_title_falls_back_to_id_when_no_translations_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditTag::class, ['record' => $tag->id])
            ->assertSee('Edit #'.$tag->getKey());
    }
}

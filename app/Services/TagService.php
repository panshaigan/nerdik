<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use Illuminate\Validation\ValidationException;

class TagService
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function createFromValidated(array $validated): Tag
    {
        $labelsByLocale = $this->withFallbackForCurrentLocale($this->normalizedLabelsByLocale($validated));
        $this->assertAtLeastOneLabel($labelsByLocale);

        $tag = Tag::create([
            'tag_category_id' => (int) $validated['tag_category_id'],
        ]);

        foreach (['en', 'pl'] as $locale) {
            if (($labelsByLocale[$locale] ?? '') !== '') {
                TagTranslation::create([
                    'tag_id' => $tag->id,
                    'locale' => $locale,
                    'label' => $labelsByLocale[$locale],
                ]);
            }
        }

        return $tag;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateFromValidated(Tag $tag, array $validated): void
    {
        $tag->update([
            'tag_category_id' => (int) $validated['tag_category_id'],
        ]);

        $labelsByLocale = $this->withFallbackForCurrentLocale($this->normalizedLabelsByLocale($validated));
        $this->assertAtLeastOneLabel($labelsByLocale);

        foreach (['en', 'pl'] as $locale) {
            $value = $labelsByLocale[$locale] ?? null;

            $translation = $tag->translations()->firstOrNew(['locale' => $locale]);

            if ($value) {
                $translation->label = $value;
                $translation->save();
            } elseif ($translation->exists) {
                $translation->delete();
            }
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function categoryOptions(): array
    {
        $locale = app()->getLocale();

        return TagCategory::query()
            ->with('translations')
            ->orderBy('key')
            ->get()
            ->map(fn (TagCategory $category) => ['id' => (int) $category->id, 'name' => $category->name($locale)])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{en: string, pl: string}
     */
    public function normalizedLabelsByLocale(array $validated): array
    {
        return [
            'en' => trim((string) ($validated['label_en'] ?? '')),
            'pl' => trim((string) ($validated['label_pl'] ?? '')),
        ];
    }

    /**
     * @param  array{en: string, pl: string}  $labelsByLocale
     * @return array{en: string, pl: string}
     */
    private function withFallbackForCurrentLocale(array $labelsByLocale): array
    {
        $currentLocale = app()->getLocale();
        if (($labelsByLocale[$currentLocale] ?? '') === '') {
            $fallback = collect($labelsByLocale)->first(fn ($label) => $label !== '');
            if ($fallback !== null) {
                $labelsByLocale[$currentLocale] = $fallback;
            }
        }

        return $labelsByLocale;
    }

    /**
     * @param  array{en: string, pl: string}  $labelsByLocale
     */
    private function assertAtLeastOneLabel(array $labelsByLocale): void
    {
        if (! collect($labelsByLocale)->contains(fn ($label) => $label !== '')) {
            $currentLocale = app()->getLocale();
            throw ValidationException::withMessages([
                'label_'.$currentLocale => __('At least one label is required.'),
            ]);
        }
    }
}

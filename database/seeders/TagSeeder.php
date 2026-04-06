<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // Games (RPGs)
            [
                'category' => 'game',
                'en' => 'Dungeons & Dragons 5e',
                'pl' => 'Dungeons & Dragons 5e',
            ],
            [
                'category' => 'game',
                'en' => 'Warhammer Fantasy Roleplay 4e',
                'pl' => 'Warhammer Fantasy Roleplay 4e',
            ],
            [
                'category' => 'game',
                'en' => 'Forbidden Lands',
                'pl' => 'Zakazane Ziemie',
            ],
            [
                'category' => 'game',
                'en' => 'Call of Cthulhu',
                'pl' => 'Zew Cthulhu',
            ],

            // Triggers (content warnings)
            [
                'category' => 'trigger',
                'en' => 'Violence',
                'pl' => 'Przemoc',
            ],
            [
                'category' => 'trigger',
                'en' => 'Horror',
                'pl' => 'Horror',
            ],
            [
                'category' => 'trigger',
                'en' => 'Gore',
                'pl' => 'Brutalna przemoc',
            ],
        ];

        foreach ($tags as $data) {
            $tag = Tag::query()
                ->whereHas('translations', function ($q) use ($data) {
                    $q->where('locale', 'en')->where('label', $data['en']);
                })
                ->first();

            if (! $tag) {
                $tag = Tag::create([
                    'category' => $data['category'],
                ]);
            }

            foreach (['en', 'pl'] as $locale) {
                if (! isset($data[$locale])) {
                    continue;
                }

                TagTranslation::firstOrCreate(
                    [
                        'tag_id' => $tag->id,
                        'locale' => $locale,
                    ],
                    [
                        'label' => $data[$locale],
                    ]
                );
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'slug' => 'dungeons-and-dragons-5e',
                'en' => 'Dungeons & Dragons 5e',
                'pl' => 'Dungeons & Dragons 5e',
            ],
            [
                'category' => 'game',
                'slug' => 'warhammer-fantasy-roleplay-4e',
                'en' => 'Warhammer Fantasy Roleplay 4e',
                'pl' => 'Warhammer Fantasy Roleplay 4e',
            ],
            [
                'category' => 'game',
                'slug' => 'forbidden-lands',
                'en' => 'Forbidden Lands',
                'pl' => 'Zakazane Ziemie',
            ],
            [
                'category' => 'game',
                'slug' => 'call-of-cthulhu',
                'en' => 'Call of Cthulhu',
                'pl' => 'Zew Cthulhu',
            ],

            // Triggers (content warnings)
            [
                'category' => 'trigger',
                'slug' => 'violence',
                'en' => 'Violence',
                'pl' => 'Przemoc',
            ],
            [
                'category' => 'trigger',
                'slug' => 'horror',
                'en' => 'Horror',
                'pl' => 'Horror',
            ],
            [
                'category' => 'trigger',
                'slug' => 'gore',
                'en' => 'Gore',
                'pl' => 'Brutalna przemoc',
            ],
        ];

        foreach ($tags as $data) {
            $tag = Tag::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'category' => $data['category'],
                ]
            );

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

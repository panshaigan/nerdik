<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\TagAlias;
use App\Models\TagCategory;
use App\Models\TagCategoryTranslation;
use App\Models\TagContext;
use App\Models\TagRelation;
use App\Models\TagTranslation;
use Illuminate\Database\Seeder;

use function ucfirst;

class TagSeeder extends Seeder
{
    const ACTIVITY_TYPE = 'activity_type';

    const ACTIVITY_TYPE_RPG = 'rpg';
    const ACTIVITY_TYPE_WARGAME = 'wargame';
    const ACTIVITY_TYPE_BOARD = 'board';
    const ACTIVITY_TYPE_CARD = 'card';
    const ACTIVITY_TYPE_LARP = 'larp';
    const ACTIVITY_TYPE_DISCUSSION = 'discussion';
    const ACTIVITY_TYPE_LECTURE = 'lecture';
    const ACTIVITY_TYPE_WORKSHOP = 'workshop';
    const ACTIVITY_TYPE_COMPETITION = 'competition';
    const ACTIVITY_TYPE_SHOW = 'show';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedTagCategories();
        $this->seedTopics();
        $this->seedTriggers();
        $this->seedGenre();
        $this->seedSettings();
        $this->seedFormats();
        $this->seedMechanics();
        $this->seedOthers();
        $this->seedGames();
    }

    public function seedOthers()
    {
        $tags = [
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Experienced Players',
                'pl' => 'Dla doświadczonych graczy',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Lore Knowledge Needed',
                'pl' => 'Wymagany Lore',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Custom Scenario',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Official Module',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedMechanics()
    {
        $tags = [
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'd20 System',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => '5E',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Pathfinder',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Year Zero Engine',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Mörk Borg (system)',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Powered by the Apocalypse',
                'aliases' => [
                    ['en' => 'PbtA']
                ],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Forged in the Dark',
            ],

            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Savage Worlds',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'GURPS',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Basic Role-Playing',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Gumshoe',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => '2d20 System',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Cypher System',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'OSR',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Blades in the Dark',
            ],

            // Wargames mechanics
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Alternating Activation',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'd6 Pool',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'IGOUGO',
            ],

            // Board & Card Games mechanics
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Deck Building',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Worker Placement',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Area Control',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Tile Placement',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Trick Taking',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Dice Rolling',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Legacy',
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Cooperative',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedFormats(): void
    {
        $tags = [
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'One shot',
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Campaign',
                'pl' => 'Kampania',
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Open Table',
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Round-robin',
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Tournament Bracket',
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Panel',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedSettings()
    {
        $tags = [
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Forgotten Realms',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Middle-earth',
                'pl' => 'Śródziemie',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Warhammer Fantasy',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Warhammer 40,000',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Cthulhu Mythos',
                'pl' => 'Mitologia Cthulhu',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'World of Darkness',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Star Wars',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Star Trek',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Harry Potter',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Dune',
                'pl' => 'Diuna',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'The Witcher',
                'pl' => 'Wiedźmin',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Elder Scrolls',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Shadowrun',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Cyberpunk RED / 2077',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Aliens',
                'pl' => 'Obcy',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Marvel Universe',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'DC Universe',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Game of Thrones',
                'pl' => 'Gra o Tron',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Fallout',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Mass Effect',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Dragonlance',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Eberron',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Ravenloft',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Planescape',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Battletech',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Halo',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'The Expanse',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Lord of the Rings',
                'pl' => 'Władca Pierścieni',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedGenre()
    {
        $commonGenres = [
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Fantasy',
                'pl' => 'Fantasy',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Science Fiction',
                'pl' => 'Science Fiction',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Horror',
                'pl' => 'Horror',
            ],
        ];

        $ids = $this->executeSeedingTags($commonGenres);

        $fantasyId = $ids[0];
        $sfId = $ids[1];
        $horrorId = $ids[2];

        $tags = [
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Heroic fantasy',
                'relations' => [$fantasyId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'High Fantasy',
                'relations' => [$fantasyId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Sword & Sorcery',
                'relations' => [$fantasyId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Dark Fantasy',
                'relations' => [$fantasyId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Grimdark',
                'relations' => [$fantasyId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Urban Fantasy',
                'relations' => [$fantasyId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Cyberpunk',
                'relations' => [$sfId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Space Opera',
                'relations' => [$sfId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Post-Apocalyptic',
                'relations' => [$sfId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Cosmic Horror',
                'relations' => [$horrorId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Survival Horror',
                'relations' => [$horrorId],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Weird West',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Historical',
                'pl' => 'Historyczne',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Alternate History',
                'pl' => 'Historia alternatywna',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Steampunk',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Dieselpunk',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Noir',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Superhero',
                'pl' => 'Superbohaterskie',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Western',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Pulp',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Swashbuckling',
                'pl' => 'Przygodowa',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Mecha',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Military',
                'pl' => 'Wojenna',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Wuxia',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Mystery',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedTriggers()
    {
        $tags = [
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Claustrophobia',
                'pl' => 'Ciasne przestrzenie',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Darkness',
                'pl' => 'Ciemność',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Demons & Monsters',
                'pl' => 'Demony i potwory',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Extreme Violence',
                'pl' => 'Drastyczna przemoc',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Gore & Blood',
                'pl' => 'Krew i gore',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Body Horror',
                'pl' => 'Body horror',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Spiders & Insects',
                'pl' => 'Pająki i robactwo',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Acrophobia',
                'pl' => 'Wysokość',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'War',
                'pl' => 'Wojna',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Torture',
                'pl' => 'Tortury',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Sexual Violence',
                'pl' => 'Przemoc seksualna',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Physical Violence',
                'pl' => 'Przemoc fizyczna',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Emotional Abuse',
                'pl' => 'Przemoc emocjonalna',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Manipulation & Gaslighting',
                'pl' => 'Manipulacja i gaslighting',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Racism',
                'pl' => 'Rasizm',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Sexism',
                'pl' => 'Seksizm',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Homophobia & Transphobia',
                'pl' => 'Homofobia i transfobia',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Suicide',
                'pl' => 'Samobójstwo',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Self-Harm',
                'pl' => 'Samookaleczenia',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Mental Illness',
                'pl' => 'Choroby psychiczne',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Death of Player Character',
                'pl' => 'Śmierć postaci gracza',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Death of NPC',
                'pl' => 'Śmierć postaci niezależnej',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Sexual Content',
                'pl' => 'Treści seksualne',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Pregnancy & Childbirth',
                'pl' => 'Ciąża i poród',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Animal Cruelty',
                'pl' => 'Przemoc wobec zwierząt',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Child Abuse',
                'pl' => 'Przemoc wobec dzieci',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Addiction',
                'pl' => 'Uzależnienia',
            ],
            [
                'category' => TagCategory::KEY_TRIGGER,
                'en' => 'Eating Disorders',
                'pl' => 'Zaburzenia odżywiania',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedTopics()
    {
        $relatedTags = [[
            'category' => TagCategory::KEY_TOPIC,
            'en' => 'Asia',
            'pl' => 'Azja',
        ]];

        $ids = $this->executeSeedingTags($relatedTags);
        $asiaId = $ids[0];

        $tags = [
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'RPG',
                'pl' => 'Gry fabularne',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Miniature Wargames',
                'pl' => 'Gry bitewne',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Board games',
                'pl' => 'Gry planszowe',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Card games',
                'pl' => 'Gry karciane',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'LARP',
                'pl' => 'LARP',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Video Games',
                'pl' => 'Gry komputerowe',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Retro Gaming',
                'pl' => 'Gry retro',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Cosplay',
                'pl' => 'Cosplay',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Anime & Manga',
                'pl' => 'Anime i Manga',
                'relations' => [$asiaId],
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Comics',
                'pl' => 'Komiksy',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Pop Culture',
                'pl' => 'Popkultura',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Movies & TV Series',
                'pl' => 'Filmy i seriale',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Literature',
                'pl' => 'Literatura',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Worldbuilding',
                'pl' => 'Kreacja świata',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Game Design',
                'pl' => 'Projektowanie gier',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Esports',
                'pl' => 'Esport',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Mythology & Folklore',
                'pl' => 'Mitologia i folklor',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'History & Reenactment',
                'pl' => 'Historia i rekonstrukcja',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Art & Illustration',
                'pl' => 'Sztuka i ilustracja',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Collectibles',
                'pl' => 'Kolekcjonerstwo',
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedGames(): void
    {
        $tags = [
            // Games (RPGs)
            [
                'category' => 'game',
                'en' => 'Dungeons & Dragons 5E',
                'aliases' => [
                    ['en' => 'D&D']
                ],
            ],
            [
                'category' => 'game',
                'en' => 'Warhammer Fantasy Roleplay 4E',
                'aliases' => [
                    ['en', 'WFRP4']
                ],
                'relations' => [],
                'contexts' => [
                    ['context_type' => self::ACTIVITY_TYPE, 'context_id' => self::ACTIVITY_TYPE_RPG],
                ],
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

        ];

        $this->executeSeedingTags($tags);
    }

    public function executeSeedingTags(array $tags): array
    {
        $ids = [];

        foreach ($tags as $data) {
            $category = TagCategory::query()->firstOrCreate(['key' => (string) $data['category']]);
            $category->translations()->firstOrCreate(['locale' => 'en'], ['label' => ucfirst((string) $data['category'])]);
            $category->translations()->firstOrCreate(['locale' => 'pl'], ['label' => ucfirst((string) $data['category'])]);

            $tag = Tag::query()
                ->whereHas('translations', function ($q) use ($data) {
                    $q->where('locale', 'en')->where('label', $data['en']);
                })
                ->first();

            if (! $tag) {
                $tag = Tag::create([
                    'tag_category_id' => $category->id,
                ]);
            } elseif ((int) $tag->tag_category_id !== (int) $category->id) {
                $tag->update(['tag_category_id' => $category->id]);
            }

            if (isset($data['aliases'])) {
                foreach ($data['aliases'] as $locale => $alias) {
                    TagAlias::firstOrCreate([
                        'tag_id' => $tag->id,
                        'locale' => $locale,
                        'aliases' => $alias,
                    ]);
                }
            }

            if (isset($data['relations'])) {
                foreach ($data['relations'] as $relation) {;
                    TagRelation::firstOrCreate([
                        'tag_id' => $tag->id,
                        'related_tag_id' => $relation,
                    ]);
                }
            }

            if (isset($data['contexts'])) {
                foreach ($data['contexts'] as $context) {
                    TagContext::firstOrCreate([
                        'tag_id' => $tag->id,
                        'context_type' => self::ACTIVITY_TYPE,
                        'context_id' => $context,
                    ]);
                }
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
            $ids[] = $tag->id;
        }

        return $ids;
    }

    public function seedTagCategories(): void
    {
        $categories = [
            'game' => ['en' => 'Game', 'pl' => 'Gra'],
            'genre' => ['en' => 'Genre', 'pl' => 'Konwencja'],
            'setting' => ['en' => 'Setting', 'pl' => 'Świat'],
            'mechanic' => ['en' => 'Mechanic', 'pl' => 'Mechanika'],
            'format' => ['en' => 'Format', 'pl' => 'Format'],
            'other' => ['en' => 'Other', 'pl' => 'Inne'],
            'trigger' => ['en' => 'Trigger', 'pl' => 'Trigger'],
            'topic' => ['en' => 'Topic', 'pl' => 'Temat'],
        ];

        foreach ($categories as $category => $data) {
            $tagCategory = TagCategory::query()->firstOrCreate(['key' => $category]);

            foreach ($data as $locale => $label) {
                TagCategoryTranslation::firstOrCreate(
                    [
                        'tag_category_id' => $tagCategory->id,
                        'locale' => $locale,
                        'label' => $label,
                    ],
                );
            }
        }
    }
}

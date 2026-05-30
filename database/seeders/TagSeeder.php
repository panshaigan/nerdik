<?php

namespace Database\Seeders;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Actions\Seeders\AttachTagMediaFromSeederLibrary;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagAlias;
use App\Models\TagCategory;
use App\Models\TagCategoryTranslation;
use App\Models\TagContext;
use App\Models\TagRelation;
use App\Models\TagTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use function ucfirst;

/**
 * Production data tag seeder
 */
class TagSeeder extends Seeder
{
    public array $tagIds = [];

    /** @var array<string, int> */
    private array $activityTypeIdsBySlug = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->activityTypeIdsBySlug = ActivityType::query()
            ->pluck('id', 'slug')
            ->all();

        $this->seedTagCategories();
        $this->seedTopics();
        $this->seedTriggers();
        $this->seedGenre();
        $this->seedSettings();
        $this->seedFormats();
        $this->seedMechanics();
        $this->seedOthers();
        $this->seedGames();

        if (! app()->environment('testing') || config('media.seed_bulk_tag_images_in_tests', false)) {
            $this->seedTagImagesFromLibrary();
            $this->seedDefaultTagImages();
        }

        $this->seedListingImages();
    }

    /**
     * Listing card defaults for activity types and events (until per-event upload exists).
     *
     * @return array{
     *     event_listing_default: list<string>,
     *     activity_types: array<string, list<string>>
     * }
     */
    protected function listingImageConfig(): array
    {
        return [
            'event_listing_default' => ['images/listing/event-default.jpg'],
            'activity_types' => [
                ActivityType::SLUG_RPG => ['images/listing/activity-type-rpg.jpg'],
            ],
        ];
    }

    public function seedListingImages(): void
    {
        $config = $this->listingImageConfig();
        $attach = app(AttachModelMediaFromPublic::class);

        foreach ($config['activity_types'] as $slug => $sources) {
            $activityType = ActivityType::findBySlug($slug);
            if ($activityType === null) {
                continue;
            }

            $attach($activityType, $sources);
        }

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        if ($rpgType === null) {
            return;
        }

        $attach(
            $rpgType,
            $config['event_listing_default'],
            ['listing_role' => 'event_listing_default'],
        );
    }

    /**
     * Attach images from {@see database_path('seeders/tag_images')} subfolders (e.g. Genres).
     * Top-level files map to a tag by `{id}_{name}`; folders attach all images to that tag.
     */
    public function seedTagImagesFromLibrary(): void
    {
        app(AttachTagMediaFromSeederLibrary::class)(
            database_path('seeders/tag_images/Genres'),
            TagCategory::KEY_GENRE,
        );
    }

    /**
     * Attach default images to tags by category. Extend this map or per-tag `images` in seed arrays.
     */
    public function seedDefaultTagImages(): void
    {
        $defaultSources = ['images/tag-game/warhammer.jpg'];

        $categoryKeys = [
            TagCategory::KEY_GAME,
            TagCategory::KEY_SETTING,
        ];

        $attach = app(AttachTagMediaFromPublic::class);

        Tag::query()
            ->whereHas('tagCategory', fn ($query) => $query->whereIn('key', $categoryKeys))
            ->each(fn (Tag $tag) => $attach($tag, $defaultSources));
    }

    public function seedOthers(): void
    {
        $tags = [
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'For Experienced Players',
                'pl' => 'Dla doświadczonych graczy',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'pl' => 'Po angielsku',
                'en' => 'In english',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: green/yellow/red',
                'pl' => 'BHS: green/yellow/red',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: X Card',
                'pl' => 'BHS: Karta X',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: Lines and Veils',
                'pl' => 'BHS: linie i zasłony',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: Brief/debrief',
                'pl' => 'BHS: omówienie przed i po',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Lore Knowledge Needed',
                'pl' => 'Wymagana znajomość świata gry',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Emphasis on Roleplay',
                'pl' => 'Nacisk na odgrywanie ról',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Pre-Made Characters',
                'pl' => 'Gotowe postaci',
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Custom Scenario',
                'pl' => 'Scenariusz autorski',
                'aliases' => [
                    'en' => 'Custom Module',
                    'pl' => 'Autorska przygoda',
                ],
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_WARGAME, ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Official Scenario',
                'pl' => 'Oficjalny scenariusz',
                'aliases' => [
                    'en' => 'Official Module',
                    'pl' => 'Oficjalna przygoda',
                ],
                'contexts' => [ActivityType::SLUG_LARP, ActivityType::SLUG_WARGAME, ActivityType::SLUG_RPG],
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedMechanics(): void
    {
        $tags = [
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => '5E',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Year Zero Engine',
                'aliases' => ['en' => 'YZE'],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Cortex Prime',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Genesys',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Mörk Borg TPL',
                'aliases' => ['en' => 'Mork Borg TPL'],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Powered by the Apocalypse',
                'aliases' => ['en' => 'PbtA'],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Forged in the Dark',
                'contexts' => [ActivityType::SLUG_RPG],
            ],

            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Savage Worlds',
                'aliases' => ['en' => 'SW'],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'GURPS',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Basic Role-Playing',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Gumshoe',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Carved from Brindlewood',
                'aliases' => ['en' => 'CfB'],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => '2d20',
                'aliases' => ['en' => 'Modiphius'],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Cypher System',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'OSR',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'd100',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Dice Pool',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Step Dice',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'd20',
                'contexts' => [ActivityType::SLUG_RPG],
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
                'contexts' => [ActivityType::SLUG_RPG, ActivityType::SLUG_WARGAME, ActivityType::SLUG_LARP, ActivityType::SLUG_BOARD, ActivityType::SLUG_CARD],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Campaign',
                'pl' => 'Kampania',
                'contexts' => [ActivityType::SLUG_RPG, ActivityType::SLUG_WARGAME, ActivityType::SLUG_LARP, ActivityType::SLUG_BOARD, ActivityType::SLUG_CARD],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Open Table',
                'contexts' => [ActivityType::SLUG_RPG, ActivityType::SLUG_WARGAME, ActivityType::SLUG_LARP, ActivityType::SLUG_BOARD, ActivityType::SLUG_CARD],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Sandbox',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Recorder session',
                'pl' => 'Sesja nagrywana',
                'contexts' => [ActivityType::SLUG_RPG, ActivityType::SLUG_WARGAME],
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedSettings(): void
    {
        $tags = [
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Forgotten Realms',
                'relations' => [$this->tagIds['High Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Middle-earth',
                'pl' => 'Śródziemie',
                'relations' => [$this->tagIds['High Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Warhammer Fantasy',
                'relations' => [$this->tagIds['Dark Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Warhammer 40k',
                'relations' => [$this->tagIds['Science Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Cthulhu Mythos',
                'pl' => 'Mitologia Cthulhu',
                'aliases' => ['en' => 'Lovecraftian Mythos'],
                'relations' => [$this->tagIds['Horror']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'World of Darkness',
                'aliases' => ['en' => 'WoD'],
                'relations' => [$this->tagIds['Urban Fantasy'], $this->tagIds['Horror']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Star Wars',
                'relations' => [$this->tagIds['Space Opera']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Harry Potter',
                'relations' => [$this->tagIds['Urban Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Game of Thrones',
                'pl' => 'Gra o Tron',
                'relations' => [$this->tagIds['Low Fantasy']],
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedGenre(): void
    {
        $commonGenres = [
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Fantasy',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Science Fiction',
                'aliases' => ['en' => 'SF'],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Horror',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Superhero',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Alternate History',
                'pl' => 'Historia alternatywna',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Historical',
                'pl' => 'Historyczne',
            ],
        ];

        $this->executeSeedingTags($commonGenres);

        $tags = [
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'High Fantasy',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Low Fantasy',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Dark Fantasy',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Urban Fantasy',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Sword & Sorcery',
                'aliases' => ['en' => 'Heroic Fantasy'],
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Cyberpunk',
                'relations' => [$this->tagIds['Science Fiction']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Space Opera',
                'relations' => [$this->tagIds['Science Fiction']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Steampunk',
                'relations' => [$this->tagIds['Science Fiction']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Post-Apocalyptic',
                'relations' => [$this->tagIds['Science Fiction']],
            ],

            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Western',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Mystery',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Weird Fiction',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Science Fantasy',
                'aliases' => ['en' => 'Grimdark'],
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedTriggers(): void
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
                'en' => 'Demons/Monsters',
                'pl' => 'Demony/potwory',
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
                'en' => 'Spiders/Insects',
                'pl' => 'Pająki/robactwo',
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
                'en' => 'Manipulation/Gaslighting',
                'pl' => 'Manipulacja/gaslighting',
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
                'en' => 'Homophobia/Transphobia',
                'pl' => 'Homofobia/transfobia',
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
                'en' => 'Pregnancy/Childbirth',
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

    public function seedTopics(): void
    {
        $relatedTags = [[
            'category' => TagCategory::KEY_TOPIC,
            'en' => 'Asia',
            'pl' => 'Azja',
        ]];

        $this->executeSeedingTags($relatedTags);

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
                'en' => 'Asia',
                'pl' => 'Azja',
            ],
            [
                'category' => TagCategory::KEY_TOPIC,
                'en' => 'Anime & Manga',
                'pl' => 'Anime i Manga',
                'relations' => [$this->tagIds['Asia']],
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
            [
                'category' => 'game',
                'en' => 'Dungeons & Dragons 5E',
                'aliases' => ['en' => 'D&D'],
                'relations' => [$this->tagIds['5E'], $this->tagIds['Forgotten Realms']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Warhammer Fantasy Roleplay 4E',
                'aliases' => ['en' => 'WFRP 4ed'],
                'relations' => [$this->tagIds['d100'], $this->tagIds['Warhammer Fantasy']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Warhammer Fantasy Roleplay 2E',
                'aliases' => ['en' => 'WFRP 2ed'],
                'relations' => [$this->tagIds['d100'], $this->tagIds['Warhammer Fantasy']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Forbidden Lands',
                'pl' => 'Zakazane Ziemie',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['Year Zero Engine']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Tales from the Loop',
                'pl' => 'Tajemnice Pętli',
                'relations' => [$this->tagIds['Science Fiction'], $this->tagIds['Year Zero Engine']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Blade Runner',
                'relations' => [$this->tagIds['Cyberpunk'], $this->tagIds['Year Zero Engine']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Trophy Dark',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Fantasy'], $this->tagIds['OSR']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Mothership',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Science Fiction'], $this->tagIds['OSR']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Liminal Horror',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['OSR']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Warlock',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['OSR']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Maze Rats',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['OSR']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Call of Cthulhu',
                'pl' => 'Zew Cthulhu',
                'relations' => [$this->tagIds['Basic Role-Playing'], $this->tagIds['Cthulhu Mythos']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Trail of Cthulhu',
                'pl' => 'Na tropie Cthulhu',
                'relations' => [$this->tagIds['Gumshoe'], $this->tagIds['Cthulhu Mythos']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Kids on Bikes',
                'pl' => 'Dzieciaki na rowerach',
                'relations' => [$this->tagIds['Mystery'], $this->tagIds['Gumshoe']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Kids on Brooms',
                'pl' => 'Dzieciaki na miotłach',
                'relations' => [$this->tagIds['Urban Fantasy'], $this->tagIds['Gumshoe']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Dzikie Pola',
                'relations' => [$this->tagIds['Historical'], $this->tagIds['d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Cyberpunk RED',
                'relations' => [$this->tagIds['Cyberpunk'], $this->tagIds['Dice Pool']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Star Trek Adventures',
                'relations' => [$this->tagIds['Science Fiction'], $this->tagIds['2d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Never Going Home',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Dice Pool']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Pathfinder 2E',
                'aliases' => ['en' => 'PF2E'],
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'The One Ring',
                'pl' => 'Jedyny Pierścień',
                'aliases' => ['en' => 'LotR'],
                'relations' => [$this->tagIds['Middle-earth'], $this->tagIds['Dice Pool']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Shadow of the Demon Lord',
                'pl' => 'Cień władcy demonów',
                'aliases' => ['en' => 'PF2E'],
                'relations' => [$this->tagIds['Dark Fantasy'], $this->tagIds['d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Blades in the Dark',
                'pl' => 'Ostrza w mroku',
                'relations' => [$this->tagIds['Steampunk'], $this->tagIds['Forged in the Dark']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Vampire: The Masquerade',
                'pl' => 'Wampir: Maskarada',
                'relations' => [$this->tagIds['Dice Pool'], $this->tagIds['World of Darkness']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Werewolf: The Apocalipse',
                'pl' => 'Wilkołak: Apokalipsa',
                'relations' => [$this->tagIds['Dice Pool'], $this->tagIds['World of Darkness']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Hunter: The Reckoning',
                'pl' => 'Łowca: Porachunek',
                'relations' => [$this->tagIds['Dice Pool'], $this->tagIds['World of Darkness']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Deadlands',
                'relations' => [$this->tagIds['Savage Worlds'], $this->tagIds['Weird Fiction']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Earthdawn',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['Step Dice']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Mörk Borg',
                'relations' => [$this->tagIds['Dark Fantasy'], $this->tagIds['Mörk Borg TPL']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Pirat Borg',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['Historical'], $this->tagIds['Mörk Borg TPL']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Alien',
                'pl' => 'Obcy RPG',
                'relations' => [$this->tagIds['Science Fiction'], $this->tagIds['Horror'], $this->tagIds['Dice Pool']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'The Walking Dead',
                'relations' => [$this->tagIds['Post-Apocalyptic'], $this->tagIds['Year Zero Engine']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Conan (Modiphius)',
                'aliases' => ['en' => 'Conan: Adventures in an Age Undreamed Of'],
                'relations' => [$this->tagIds['Sword & Sorcery'], $this->tagIds['2d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Dune',
                'pl' => 'Diuna',
                'relations' => [$this->tagIds['Science Fiction'], $this->tagIds['2d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Achtung Cthulhu',
                'relations' => [$this->tagIds['2d20'], $this->tagIds['Cthulhu Mythos']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Star Wars (FF)',
                'relations' => [$this->tagIds['Genesys'], $this->tagIds['Star Wars']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Delta Green',
                'relations' => [$this->tagIds['Basic Role-Playing'], $this->tagIds['Cthulhu Mythos']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Monster of the Week',
                'relations' => [$this->tagIds['Urban Fantasy'], $this->tagIds['Powered by the Apocalypse']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => '7th Sea',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['Dice Pool']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Fate Core',
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Dungeon World',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['Powered by the Apocalypse']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Vaesen',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Year Zero Engine']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Brindlewood Bay',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Carved from Brindlewood']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Dragonbane',
                'relations' => [$this->tagIds['Fantasy'], $this->tagIds['d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Fallout',
                'relations' => [$this->tagIds['Post-Apocalyptic'], $this->tagIds['2d20']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Kult: Divinity Lost',
                'pl' => 'Kult: Boskość Utracona',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Powered by the Apocalypse']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Ten Candles',
                'relations' => [$this->tagIds['Horror'], $this->tagIds['Dice Pool']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'City of Mist',
                'relations' => [$this->tagIds['Powered by the Apocalypse']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Fabula Ultima',
                'relations' => [$this->tagIds['Fantasy']],
                'contexts' => [ActivityType::SLUG_RPG],
            ],
            [
                'category' => 'game',
                'en' => 'Pendragon',
                'relations' => [$this->tagIds['Historical'], $this->tagIds['d20']],
                'contexts' => [ActivityType::SLUG_RPG],
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
                        'alias' => $alias,
                    ]);
                }
            }

            if (isset($data['relations'])) {
                foreach ($data['relations'] as $relation) {
                    TagRelation::firstOrCreate([
                        'tag_id' => $tag->id,
                        'related_tag_id' => $relation,
                    ]);
                }
            }

            if (isset($data['contexts'])) {
                foreach ($data['contexts'] as $context) {
                    $contextId = is_string($context)
                        ? ($this->activityTypeIdsBySlug[$context] ?? null)
                        : null;
                    if ($contextId === null) {
                        continue;
                    }
                    TagContext::firstOrCreate([
                        'tag_id' => $tag->id,
                        'context_type' => 'activity_type',
                        'context_id' => $contextId,
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
                        'slug' => Str::slug($data[$locale]),
                    ]
                );
            }

            if (isset($data['images'])) {
                app(AttachTagMediaFromPublic::class)($tag, $data['images']);
            }

            $this->tagIds[$data['en']] = $tag->id;

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

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

use Illuminate\Support\Str;

use function ucfirst;

class TagSeeder extends Seeder
{
    const ACTIVITY_TYPE = 'activity_type';

    const ACTIVITY_TYPE_RPG = 1;
    const ACTIVITY_TYPE_WARGAME = 2;
    const ACTIVITY_TYPE_BOARD = 3;
    const ACTIVITY_TYPE_CARD = 4;
    const ACTIVITY_TYPE_LARP = 5;
    const ACTIVITY_TYPE_DISCUSSION = 6;
    const ACTIVITY_TYPE_LECTURE = 7;
    const ACTIVITY_TYPE_WORKSHOP = 8;
    const ACTIVITY_TYPE_COMPETITION = 9;
    const ACTIVITY_TYPE_SHOW = 10;

    public $tagIds = [];

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
                'en' => 'For Experienced Players',
                'pl' => 'Dla doświadczonych graczy',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'pl' => 'Materiały w języku angielskim',
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: green/yellow/red',
                'pl' => 'BHS: green/yellow/red',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: X Card',
                'pl' => 'BHS: Karta X',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: Lines and Veils',
                'pl' => 'BHS: linie i zasłony',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Safety Toolkit: Brief/debrief',
                'pl' => 'BHS: omówienie przed i po',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Lore Knowledge Needed',
                'pl' => 'Wymagana znajomosć świata gry',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Emphasis on Roleplay',
                'pl' => 'Nacisk na odgrywanie ról',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Pre-Made Characters',
                'pl' => 'Gotowe postaci',
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Custom Scenario',
                'pl' => 'Scenariusz autorski',
                'aliases' => [
                    'en' => 'Custom Module',
                    'pl' => 'Autorska przygoda',
                ],
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_WARGAME, self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_OTHER,
                'en' => 'Official Scenario',
                'pl' => 'Oficjalny scenariusz',
                'aliases' => [
                    'en' => 'Official Module',
                    'pl' => 'Oficjalna przygoda',
                ],
                'contexts' => [self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_WARGAME, self::ACTIVITY_TYPE_RPG],
            ],
        ];

        $this->executeSeedingTags($tags);
    }

    public function seedMechanics()
    {
        $tags = [
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => '5E',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Year Zero Engine',
                'aliases' => ['en' => 'YZE'],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Cortex Prime',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Genesys',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Mörk Borg TPL',
                'aliases' => ['en' => 'Mork Borg TPL'],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Powered by the Apocalypse',
                'aliases' => ['en' => 'PbtA'],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Forged in the Dark',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],

            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Savage Worlds',
                'aliases' => ['en' => 'SW'],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'GURPS',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Basic Role-Playing',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Gumshoe',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Carved from Brindlewood',
                'aliases' => ['en' => 'CfB'],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => '2d20',
                'aliases' => ['en' => 'Modiphius'],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Cypher System',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'OSR',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'd100',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Dice pool',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'Step dice',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_MECHANIC,
                'en' => 'd20',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
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
                'contexts' => [self::ACTIVITY_TYPE_RPG, self::ACTIVITY_TYPE_WARGAME, self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_BOARD, self::ACTIVITY_TYPE_CARD],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Campaign',
                'pl' => 'Kampania',
                'contexts' => [self::ACTIVITY_TYPE_RPG, self::ACTIVITY_TYPE_WARGAME, self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_BOARD, self::ACTIVITY_TYPE_CARD],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Open Table',
                'contexts' => [self::ACTIVITY_TYPE_RPG, self::ACTIVITY_TYPE_WARGAME, self::ACTIVITY_TYPE_LARP, self::ACTIVITY_TYPE_BOARD, self::ACTIVITY_TYPE_CARD],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Sandbox',
                'contexts' => [self::ACTIVITY_TYPE_RPG],
            ],
            [
                'category' => TagCategory::KEY_FORMAT,
                'en' => 'Sesja nagrywana',
                'contexts' => [self::ACTIVITY_TYPE_RPG, self::ACTIVITY_TYPE_WARGAME],
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
                'en' => 'Warhammer 40k',
            ],
            [
                'category' => TagCategory::KEY_SETTING,
                'en' => 'Cthulhu Mythos',
                'pl' => 'Mitologia Cthulhu',
                'aliases' => ['en' => 'Lovecraftian Mythos'],
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
                'en' => 'Alternate History',
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
                'en' => 'Heroic fantasy',
                'relations' => [$this->tagIds['Fantasy']],
            ],
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
                'en' => 'Sword & Sorcery',
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
                'en' => 'Weird Fiction',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Superhero',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Science Fantasy',
                'aliases' => ['en' => 'Grimdark'],
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Hard Science Fiction',
                'aliases' => ['en' => 'Hard SF'],
                'relations' => [$this->tagIds['Science Fiction']],
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
                'en' => 'Cosmic Horror',
                'relations' => [$this->tagIds['Horror']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Psychological Horror',
                'relations' => [$this->tagIds['Horror']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Supernatural Horror',
                'aliases' => ['en' => 'Paranormal Horror'],
                'relations' => [$this->tagIds['Horror']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Survival Horror',
                'relations' => [$this->tagIds['Horror']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Weird West',
                'relations' => [$this->tagIds['Fantasy']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Steampunk',
                'relations' => [$this->tagIds['Science Fiction']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Dieselpunk',
                'relations' => [$this->tagIds['Science Fiction']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Tech Noir',
                'relations' => [$this->tagIds['Science Fiction']],
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Noir',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Western',
            ],
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Wild West',
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
            [
                'category' => TagCategory::KEY_GENRE,
                'en' => 'Post-Apocalyptic',
                'relations' => [$this->tagIds['Science Fiction']],
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

    public function seedTopics()
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
            // Games (RPGs)
            [
                'category' => 'game',
                'en' => 'Dungeons & Dragons 5E',
                'aliases' => ['en' => 'D&D'],
            ],
            [
                'category' => 'game',
                'en' => 'Warhammer Fantasy Roleplay 4E',
                'aliases' => ['en' => 'WFRP4'],
                'relations' => [],
                'contexts' => [self::ACTIVITY_TYPE_RPG],
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
                        'alias' => $alias,
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
                        'slug' => Str::slug($data[$locale]),
                    ]
                );
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

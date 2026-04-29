<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Organization>
 */
final class OrganizationFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Organization::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'acronym' => Str::upper(Str::limit($name, 3, '')),
            'slug' => Str::slug($name),
            'description' => fake()->text,
            'created_by' => User::factory(),
        ];
    }

    public function predefined(): static
    {
        return $this->state(new Sequence(
            ['name' => 'Otwarte Opcje Fantastyczne',      'acronym' => 'OOF',  'slug' => 'otwarte-opcje-fantastyczne'],
            ['name' => 'Cybernetyczna Gildia Nerdów',     'acronym' => 'CGN',  'slug' => 'cybernetyczna-gildia-nerdow'],
            ['name' => 'Zakon Kwantowych Smoków',         'acronym' => 'ZKS',  'slug' => 'zakon-kwantowych-smokow'],
            ['name' => 'Liga Bitowych Magów',             'acronym' => 'LBM',  'slug' => 'liga-bitowych-magow'],
            ['name' => 'Stowarzyszenie Hyperdrive',       'acronym' => 'SH',   'slug' => 'stowarzyszenie-hyperdrive'],
            ['name' => 'Tajne Laboratorium Memów',        'acronym' => 'TLM',  'slug' => 'tajne-laboratorium-memow'],
            ['name' => 'Gildia Kosmicznych Otaku',        'acronym' => 'GKO',  'slug' => 'gildia-kosmicznych-otaku'],
            ['name' => 'Bractwo Kodu i Mitu',             'acronym' => 'BKIM', 'slug' => 'bractwo-kodu-i-mitu'],
            ['name' => 'Akademia Neonowych Proroków',     'acronym' => 'ANP',  'slug' => 'akademia-neonowych-prorokow'],
            ['name' => 'Syndykat Sztucznej Magii',        'acronym' => 'SSM',  'slug' => 'syndykat-sztucznej-magii'],
            ['name' => 'Kolektyw Zer i Jedynkowych Smoków','acronym' => 'KZJS', 'slug' => 'kolektyw-zer-i-jedynkowych-smokow'],
            ['name' => 'Imperium Kafelkowych Lochów',     'acronym' => 'IKL',  'slug' => 'imperium-kafelkowych-lochow'],
            ['name' => 'Towarzystwo Kwantowej Kawy',      'acronym' => 'TKK',  'slug' => 'towarzystwo-kwantowej-kawy'],
            ['name' => 'Rada Władców Dice’ów',            'acronym' => 'RWD',  'slug' => 'rada-wladcow-dice-ow'],
            ['name' => 'Zjednoczone Siły Pixelowe',       'acronym' => 'ZSP',  'slug' => 'zjednoczone-sily-pixelowe'],
            ['name' => 'Arcane Debuggers Guild',          'acronym' => 'ADG',  'slug' => 'arcane-debuggers-guild'],
            ['name' => 'Order of the Eternal Respawn',    'acronym' => 'OER',  'slug' => 'order-of-the-eternal-respawn'],
            ['name' => 'Neon Knights of the Mainframe',   'acronym' => 'NKM',  'slug' => 'neon-knights-of-the-mainframe'],
            ['name' => 'Quantum Dice Collective',         'acronym' => 'QDC',  'slug' => 'quantum-dice-collective'],
            ['name' => 'Shadowrun Syndicate PL',          'acronym' => 'SSP',  'slug' => 'shadowrun-syndicate-pl'],
            ['name' => 'Eldritch Error 404 Society',      'acronym' => 'EE4S', 'slug' => 'eldritch-error-404-society'],
            ['name' => 'Gildia Laserowych Jednorożców',   'acronym' => 'GLJ',  'slug' => 'gildia-laserowych-jednorozcow'],
            ['name' => 'Matrixowe Bractwo Kawy',          'acronym' => 'MBK',  'slug' => 'matrixowe-bractwo-kawy'],
            ['name' => 'Akademia Endgame’owych Bogów',    'acronym' => 'AEB',  'slug' => 'akademia-endgame-owych-bogow'],
            ['name' => 'Związek Kosmicznych Rolkarzy',    'acronym' => 'ZKR',  'slug' => 'zwiazek-kosmicznych-rolkarzy'],
            ['name' => 'Cult of the Overclocked Dragon',  'acronym' => 'COD',  'slug' => 'cult-of-the-overclocked-dragon'],
            ['name' => 'Departament Anomalii Fabularnych', 'acronym' => 'DAF', 'slug' => 'departament-anomalii-fabularnych'],
            ['name' => 'Liga Retro-Futurystycznych Rebeliantów', 'acronym' => 'LRR', 'slug' => 'liga-retro-futurystycznych-rebeliantow'],
            ['name' => 'Enklawa Psionicznych Programistów','acronym' => 'EPP', 'slug' => 'enklawa-psionicznych-programistow'],
            ['name' => 'Wielki Zakon Riftwalkerów',       'acronym' => 'WZR',  'slug' => 'wielki-zakon-riftwalkerow'],
        ));
    }
}

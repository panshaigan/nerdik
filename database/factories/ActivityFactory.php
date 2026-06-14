<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Place;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function fake;
use function min;

/**
 * @extends Factory<Activity>
 */
final class ActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->name;

        return [
            'name' => $name,
            'activity_type_id' => ActivityType::findBySlug('rpg'),
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,

            'min_participants' => fake()->numberBetween(0, 3),
            'max_participants' => fake()->numberBetween(3, 10),
            'minimum_age' => fake()->optional(0.3)->randomElement([
                12,
                16, 16,
                18, 18, 18, 18,
            ]),
            'cancellation_deadline_in_hours' => fake()->optional()->randomElement([
                12,
                18, 18,
                24, 24, 24, 24,
            ]),
            'duration_in_minutes' => fake()->randomElement([
                120,
                150,
                180, 180,
                240, 240, 240, 240,
            ]),
            'allows_observers' => 0,
            'is_host_passive' => 0,
            'requires_approval' => fake()->boolean(0.3),
            'price' => null,
            'slug' => Str::slug($name),
            'description' => fake()->text(2000),
            'created_by' => User::factory(),
        ];
    }

    public function selfHosted(Collection $users): self
    {
        return $this->afterCreating(function (Activity $activity) use ($users) {
            $startsAt = fake()->dateTimeBetween('+1 week', '+6 months')
                ->setTime(fake()->numberBetween(9, 17), 0);

            $startsAt = Carbon::instance($startsAt);

            $endsAt = (clone $startsAt)
                ->addMinutes($activity->duration_in_minutes);

            $activity->update([
                'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'place_id' => Place::inRandomOrder()->first()?->id,
            ]);

            $users = collect($users);

            $activity->users()->attach(
                $users->random(random_int(1, min(3, $activity->max_participants)))->pluck('id')
            );
        });
        //        select activity_waitlist_entries
    }

    public function proposed(): self
    {
        return $this->state(fn (array $attributes) => [
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        ]);
        //        select activity_proposal_slot
    }

    public function scheduled(): self
    {
        return $this->state(fn (array $attributes) => [
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        //        select activity_proposal_slot
        //        select activity_user
        //        select activity_waitlist_entries
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => fake()->dateTime(),
            'cancelled_by' => User::factory(),
            'cancel_reason' => fake()->optional()->text,
        ]);
    }

    private static ?Sequence $predefinedSequence = null;

    /**
     * @return list<string>
     */
    private static function predefinedNames(): array
    {
        return [
            'Noc Świetlików',
            'Szept w Ciemności',
            'Ostatni Smak Miodu',
            'Cienie Nad Błędnym Kręgiem',
            'Pęknięte Lustro',
            'Krew na Śniegu',
            'Głosy z Otchłani',
            'Zamek z Popiołu',
            'Srebrny Sierp',
            'Utracone Gwiazdy',
            'Dom na Skraju Lasu',
            'Pieśń Martwych Drzew',
            'Czerwona Mgła',
            'Korona z Kości',
            'Szklane Pustkowie',
            'Ostatni List od Umarłego',
            'Wrota z Cierni',
            'Echo Zapomnianych Bogów',
            'Czarna Rzeka',
            'Taniec na Grobach',
            'Smok z Pękniętego Jaja',
            'Królestwo bez Króla',
            'Klątwa Złotego Dębu',
            'Wieża z Kości Słoniowej',
            'Dziedzic Burzy',
            'Pazury pod Łóżkiem',
            'Uśmiech w Lustrze',
            'Kościół bez Krzyża',
            'Dziecko z Pustego Grobu',
            'Cisza po Krzyku',
            'Neonowa Krew',
            'Ostatni Upload',
            'Gwiazdy nad Zepsutym Miastem',
            'Kod Apokalipsy',
            'Sztuczne Słońce',
            'Serce z Żelaza',
            'Mgła nad Bagnami',
            'Krzyk Banshee',
            'Zaginiony Krąg',
            'Czarny Tron',
            'Oczy w Zbożu',
            'Labirynt z Ciała',
            'Księżycowy Żniwiarz',
            'Stalowe Anioły',
            'Przebudzenie Lewiatana',
            'Sól i Popiół',
            'Wampir z Pociągu',
            'Córka Burzy',
            'Cmentarz Zapomnianych Imion',
            'Ostatni Lot „Nocnego Jastrzębia”',
            'Szpital na Końcu Świata',
            'Długa Noc w Karczmie „Pod Toporem”',
            'Ręka z Grobu',
            'Miasto Bez Cieni',
            'Krwawe Żniwa',
            'Maski z Ludzkiej Skóry',
            'Cybernetyczna Dusza',
            'Pieśń Stalowych Drzew',
            'Błękitna Zaraza',
            'Królowa Pająków',
            'Złodziej Wspomnień',
            'Czas Złamany',
            'Kościół Potępionych',
            'Ogród Martwych Kwiatów',
            'Szept Maszyn',
            'Pocałunek Wiedźmy',
            'Statek Widmo',
            'Dzieci Mgły',
            'Królestwo z Rdzy',
            'Głębiny',
            'Nóż w Plecach Boga',
            'Hotel „Koniec Sezonu”',
            'Archiwum Zakazanych Snów',
            'Czarna Orchidea',
            'Płomień w Szybie',
            'Węże z Nefrytu',
            'Ostatni Jeździec',
            'Serce z Ciemności',
            'Wirujący Las',
            'Piętno Zdrajcy',
            'Gwiezdny Trup',
            'Krew na Marsie',
            'Dom Pełen Drzwi',
            'Zabójca Czasu',
            'Córka Cienia',
            'Mechaniczny Anioł',
            'Przeklęty Ród',
            'Stacja Orbitalna 13',
            'Szczury z Neonowego Dna',
            'Bóg z Pudełka',
            'Żniwa Dusz',
            'Czerwone Drzewo',
            'Kapitan Martwego Statku',
            'Szkoła dla Potworów',
            'Pamięć z Rdzy',
            'Noc Długich Noży',
            'Wilk w Owczej Skórze',
            'Labirynt z Lustra',
            'Ostatnia Pieśń Smoka',
            'Miasto Umarłych Bogów',
            'Klatka z Czasu',
            'Głęboki Sen',
            'Krew i Rdza',
            'Wrota do Piekła',
            'Białe Pustkowie',
            'Dług u Diabła',
            'Córka Zimy',
            'Sztorm nad Martwym Morzem',
            'Człowiek z Papieru',
            'Archiwum Zapomnianych',
            'Niewidzialny Ogień',
            'Królestwo Lodu',
            'Serce z Neonów',
            'Dźwięk Pękającego Nieba',
            'Pocałunek Śmierci',
            'Wieża z Krwi',
            'Czarny Karawan',
            'Duchy z Linii Wysokiego Napięcia',
            'Kraina Bez Słońca',
            'Zepsuta Pieśń',
            'Dziedzictwo Grzechu',
            'Stare Długa',
            'Most do Nigdzie',
            'Klatka dla Aniołów',
            'Czerwone Niebo',
            'Sól pod Językiem',
            'Ostatni Dzień Lata',
            'Miasto z Popiołu',
            'Głód',
            'Szary Anioł',
            'Pięć Serc',
            'Cień za Szybą',
            'Wąż w Koronie',
            'Hotel „Pod Czarnym Psem”',
            'Księga Umarłych Imion',
            'Mechaniczna Kołysanka',
            'Krew na Rękach Boga',
            'Zimne Światło',
            'Pamiętnik Szaleńca',
            'Cisza Głębin',
            'Płonący Las',
            'Zwierciadło z Pęknięć',
            'Dzień, w którym Umarło Słońce',
            'Srebrne Zęby',
            'Wściekłe Psy',
            'Róża z Cierni',
            'Ostatni Obserwator',
            'Kraina Wiecznej Zimy',
            'Grobowiec z Gwiazd',
            'Czarny Internet',
            'Dzieci z Mgły',
            'Żelazna Korona',
            'Serce Zegara',
            'Przeklęta Taśma',
            'Wiatr z Pustki',
            'Dom na Wzgórzu',
            'Cień Który Śledzi',
            'Krew na Klawiaturze',
            'Noc Żywych Cieni',
            'Zaginiony Konwój',
            'Miasto z Lustra',
            'Pieśń z Głębi',
            'Zardzewiały Anioł',
            'Kości z Nieba',
            'Ostatnia Stacja',
            'Sny z Pękniętego Nieba',
            'Królestwo z Rdzy i Krwi',
            'Ciemność pod Skórą',
            'Białe Piekło',
            'Wir',
            'Długi Sen w Czerwieni',
            'Maska z Twarzy',
            'Nocna Straż',
            'Córka Mgły',
            'Stalowe Niebo',
            'Przeklęty Festiwal',
            'Krew i Srebro',
            'Dom bez Cieni',
            'Ostatni Smok',
            'Ciche Miasto',
            'Zabójca Bogów',
            'Serce z Cierni',
            'Neonowy Grób',
            'Klątwa Rodu',
            'Pociąg do Nigdzie',
            'Oczy w Ścianie',
            'Czarna Wieś',
            'Władca Lalek',
            'Szept Zmarłych',
            'Krew na Ołtarzu',
            'Zaginione Miasto',
            'Mechaniczny Bóg',
            'Noc Długich Cieni',
            'Płonące Niebo',
            'Cień Ojca',
            'Kraina Bez Powrotu',
            'Ostatni Dzień',
            'Szpital dla Umarłych',
            'Krew na Porannym Mrozie',
            'Czarny Las',
            'Dźwięk Pękającego Serca',
            'Władca Much',
            'Srebrny Wilk',
            'Miasto z Popiołu i Snów',
            'Przeklęta Taśma Video',
            'Anioł z Rdzy',
            'Zamek z Czarnego Szkła',
            'Krwawy Księżyc',
            'Dom na Końcu Drogi',
            'Zimna Krew',
            'Ostatni Koncert',
            'Cień w Lustrze',
            'Królestwo z Mgły',
            'Neon i Krew',
            'Pieśń z Pustki',
            'Złoty Grób',
            'Wściekłość',
            'Czarny Anioł',
            'Długi Sen',
            'Miasto Umarłych',
            'Płomień w Ciemności',
            'Kości z Gwiazd',
            'Przeklęty Zamek',
            'Echo po Ostatnim Krzyku',
            'Ostatnia Latarnia',
            'Tron z Kości Węży',
            'Cisza',
            'Noc bez Gwiazd',
            'Zaginiony Brat',
            'Wrota Piekieł',
            'Czerwony Deszcz',
            'Dom Pełen Duchów',
            'Mechaniczna Miłość',
            'Królestwo Cienia',
            'Ostatni Strażnik',
            'Sny z Krwi',
            'Czarny Wiatr',
            'Zwierciadło',
            'Puste Miasto',
            'Krwawa Korona',
            'Cień za Tobą',
            'Żniwa Czerwonego Księżyca',
            'Stare Duchy',
            'Otchłań pod Miastem',
            'Serce z Popiołu i Miedzi',
            'Krew na Orbitalnej Stacji',
            'Dom Bez Klamek',
            'Cień na Drugim Brzegu',
            'Dom na Skraju',
            'Noc Stygmatycznych Noży',
            'Więzienie Zatrzymanego Czasu',
            'Neonowa Apokalipsa',
            'Serce z Rdzy',
            'Ostatni Anioł',
            'Cień Boga',
            'Sen Bez Przebudzenia',
            'Rdza i Ogień',
            'Pęknięte Niebo',
            'Zimowe Serce',
            'Las Płonących Korzeni',
            'Ostatnia Pieśń',
            'Lustro Bez Odbicia',
            'Dzień bez Nazwy',
            'Las Czarnych Szeptów',
            'Krew pod Lodem',
            'Mgła nad Starym Dworem',
            'Śpiewające Kości',
            'Krypta Ostatniej Nadziei',
            'Rytuał w Czerwonej Kaplicy',
            'Pustynia z Czarnym Piaskiem',
            'Kronika Zapomnianych Bohaterów',
            'Świt nad Martwym Jeziorem',
            'Klatka z Bursztynu',
            'Zapach Zepsutej Magii',
            'Krew na Starych Schodach',
            'Miasto Podwójnych Cieni',
            'Ostatnia Wieczerza w Karczmie',
            'Żarłok z Głębokiej Nocy',
            'Pieśń Złamanych Ostrzy',
            'Wieża Bez Okien',
            'Korzenie Pod Miastem',
            'Srebrna Komnata',
            'Czerń za Horyzontem',
            'Dziedziniec Umarłych Królów',
            'Księga Zimnych Ognisk',
            'Nocny Patrol',
            'Mglisty Brzeg',
            'Kamienna Twarz',
            'Ścieżka przez Ruiny',
            'Ostatni Most',
            'Wilk z Czerwonej Nocy',
            'Komnata Tysiąca Luster',
            'Zapomniane Przysięgi',
            'Krew na Starym Mapie',
            'Cień w Katedrze',
            'Płomień pod Wodą',
            'Złote Łzy',
            'Korona z Popiołu',
            'Szept w Komnacie',
            'Dom z Czarnym Dachem',
            'Noc w Muzeum Lalek',
            'Krwawy Świt',
            'Zaginiona Kolumna',
            'Mgła nad Kanałami',
            'Ostatni Herold',
            'Pieśń z Podziemi',
            'Czerwone Skały',
            'Wieża Zapomnianych Dzwonów',
            'Krew na Starym Moście',
            'Śnieg bez Imienia',
            'Krypta Bez Światła',
            'Noc w Starym Teatrze',
            'Złamane Ogniwo',
            'Cień na Placu',
            'Ostatni Wędrowiec',
            'Korona z Mgły',
            'Płomień w Ciemnej Komnacie',
            'Krew na Starych Murach',
            'Zapomniana Twierdza',
            'Noc nad Cmentarzem',
            'Srebrny Cień',
            'Księga Czerwonej Nocy',
            'Wrota z Bursztynu',
            'Mgła nad Polami',
            'Ostatni Świadek',
            'Pieśń z Ciemnej Wieży',
        ];
    }

    public function predefined(): self
    {
        if (! self::$predefinedSequence) {
            self::$predefinedSequence = new Sequence(
                ...array_map(
                    fn (string $name): array => ['name' => $name, 'slug' => null],
                    self::predefinedNames(),
                ),
            );
        }

        return $this->state(self::$predefinedSequence);
    }

    public static function resetPredefinedSequenceForTesting(): void
    {
        self::$predefinedSequence = null;
    }
}

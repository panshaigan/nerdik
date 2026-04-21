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
    *
    * @return array
    */
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
                18, 18, 18, 18
            ]),
            'cancellation_deadline_in_hours' => fake()->optional()->randomElement([
                12,
                18, 18,
                24, 24, 24, 24
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
            'description' => fake()->optional()->text,
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

            $users  = collect($users);

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

    private static Sequence|null $predefinedSequence = null;
    public function predefined(): self
    {
        if (!self::$predefinedSequence) {
            self::$predefinedSequence = new Sequence(
                ['name' => 'Noc Świetlików', 'slug' => 'noc-swietlikow'],
                ['name' => 'Szept w Ciemności', 'slug' => 'szept-w-ciemnosci'],
                ['name' => 'Ostatni Smak Miodu', 'slug' => 'ostatni-smak-miodu'],
                ['name' => 'Cienie Nad Błędnym Kręgiem', 'slug' => 'cienie-nad-blednym-kregiem'],
                ['name' => 'Pęknięte Lustro', 'slug' => 'pekniete-lustro'],
                ['name' => 'Krew na Śniegu', 'slug' => 'krew-na-sniegu'],
                ['name' => 'Głosy z Otchłani', 'slug' => 'glosy-z-otchlani'],
                ['name' => 'Zamek z Popiołu', 'slug' => 'zamek-z-popiolu'],
                ['name' => 'Srebrny Sierp', 'slug' => 'srebrny-sierp'],
                ['name' => 'Utracone Gwiazdy', 'slug' => 'utracone-gwiazdy'],
                ['name' => 'Dom na Skraju Lasu', 'slug' => 'dom-na-skraju-lasu'],
                ['name' => 'Pieśń Martwych Drzew', 'slug' => 'piesn-martwych-drzew'],
                ['name' => 'Czerwona Mgła', 'slug' => 'czerwona-mgla'],
                ['name' => 'Korona z Kości', 'slug' => 'korona-z-kosci'],
                ['name' => 'Szklane Pustkowie', 'slug' => 'szklane-pustkowie'],
                ['name' => 'Ostatni List od Umarłego', 'slug' => 'ostatni-list-od-umarlego'],
                ['name' => 'Wrota z Cierni', 'slug' => 'wrota-z-cierni'],
                ['name' => 'Echo Zapomnianych Bogów', 'slug' => 'echo-zapomnianych-bogow'],
                ['name' => 'Czarna Rzeka', 'slug' => 'czarna-rzeka'],
                ['name' => 'Taniec na Grobach', 'slug' => 'taniec-na-grobach'],
                ['name' => 'Smok z Pękniętego Jaja', 'slug' => 'smok-z-peknietego-jaja'],
                ['name' => 'Królestwo bez Króla', 'slug' => 'krolestwo-bez-krola'],
                ['name' => 'Klątwa Złotego Dębu', 'slug' => 'klatwa-zlotego-debu'],
                ['name' => 'Wieża z Kości Słoniowej', 'slug' => 'wieza-z-kosci-sloniowej'],
                ['name' => 'Dziedzic Burzy', 'slug' => 'dziedzic-burzy'],
                ['name' => 'Pazury pod Łóżkiem', 'slug' => 'pazury-pod-lozkiem'],
                ['name' => 'Uśmiech w Lustrze', 'slug' => 'usmiech-w-lustrze'],
                ['name' => 'Kościół bez Krzyża', 'slug' => 'kosciol-bez-krzyza'],
                ['name' => 'Dziecko z Pustego Grobu', 'slug' => 'dziecko-z-pustego-grobu'],
                ['name' => 'Cisza po Krzyku', 'slug' => 'cisza-po-krzyku'],
                ['name' => 'Neonowa Krew', 'slug' => 'neonowa-krew'],
                ['name' => 'Ostatni Upload', 'slug' => 'ostatni-upload'],
                ['name' => 'Gwiazdy nad Zepsutym Miastem', 'slug' => 'gwiazdy-nad-zepsutym-miastem'],
                ['name' => 'Kod Apokalipsy', 'slug' => 'kod-apokalipsy'],
                ['name' => 'Sztuczne Słońce', 'slug' => 'sztuczne-slonce'],
                ['name' => 'Serce z Żelaza', 'slug' => 'serce-z-zelaza'],
                ['name' => 'Mgła nad Bagnami', 'slug' => 'mgla-nad-bagnami'],
                ['name' => 'Krzyk Banshee', 'slug' => 'krzyk-banshee'],
                ['name' => 'Zaginiony Krąg', 'slug' => 'zaginiony-krag'],
                ['name' => 'Czarny Tron', 'slug' => 'czarny-tron'],
                ['name' => 'Oczy w Zbożu', 'slug' => 'oczy-w-zbozu'],
                ['name' => 'Labirynt z Ciała', 'slug' => 'labirynt-z-ciala'],
                ['name' => 'Księżycowy Żniwiarz', 'slug' => 'ksiezycowy-zniwiarz'],
                ['name' => 'Stalowe Anioły', 'slug' => 'stalowe-anioly'],
                ['name' => 'Przebudzenie Lewiatana', 'slug' => 'przebudzenie-lewiatana'],
                ['name' => 'Sól i Popiół', 'slug' => 'sol-i-popiol'],
                ['name' => 'Wampir z Pociągu', 'slug' => 'wampir-z-pociagu'],
                ['name' => 'Córka Burzy', 'slug' => 'corka-burzy'],
                ['name' => 'Cmentarz Zapomnianych Imion', 'slug' => 'cmentarz-zapomnianych-imion'],
                ['name' => 'Ostatni Lot „Nocnego Jastrzębia”', 'slug' => 'ostatni-lot-nocnego-jastrzebia'],
                ['name' => 'Szpital na Końcu Świata', 'slug' => 'szpital-na-koncu-swiata'],
                ['name' => 'Długa Noc w Karczmie „Pod Toporem”', 'slug' => 'dluga-noc-w-karczmie-pod-toporem'],
                ['name' => 'Ręka z Grobu', 'slug' => 'reka-z-grobu'],
                ['name' => 'Miasto Bez Cieni', 'slug' => 'miasto-bez-cieni'],
                ['name' => 'Krwawe Żniwa', 'slug' => 'krwawe-zniwa'],
                ['name' => 'Maski z Ludzkiej Skóry', 'slug' => 'maski-z-ludzkiej-skory'],
                ['name' => 'Cybernetyczna Dusza', 'slug' => 'cybernetyczna-dusza'],
                ['name' => 'Pieśń Stalowych Drzew', 'slug' => 'piesn-stalowych-drzew'],
                ['name' => 'Błękitna Zaraza', 'slug' => 'blekitna-zaraza'],
                ['name' => 'Królowa Pająków', 'slug' => 'krolowa-pajakow'],
                ['name' => 'Złodziej Wspomnień', 'slug' => 'zlodziej-wspomnien'],
                ['name' => 'Czas Złamany', 'slug' => 'czas-zlamany'],
                ['name' => 'Kościół Potępionych', 'slug' => 'kosciol-potepionych'],
                ['name' => 'Ogród Martwych Kwiatów', 'slug' => 'ogrod-martwych-kwiatow'],
                ['name' => 'Szept Maszyn', 'slug' => 'szept-maszyn'],
                ['name' => 'Pocałunek Wiedźmy', 'slug' => 'pocalunek-wiedzmy'],
                ['name' => 'Statek Widmo', 'slug' => 'statek-widmo'],
                ['name' => 'Dzieci Mgły', 'slug' => 'dzieci-mgly'],
                ['name' => 'Królestwo z Rdzy', 'slug' => 'krolestwo-z-rdzy'],
                ['name' => 'Głębiny', 'slug' => 'glebiny'],
                ['name' => 'Nóż w Plecach Boga', 'slug' => 'noz-w-plecach-boga'],
                ['name' => 'Hotel „Koniec Sezonu”', 'slug' => 'hotel-koniec-sezonu'],
                ['name' => 'Archiwum Zakazanych Snów', 'slug' => 'archiwum-zakazanych-snow'],
                ['name' => 'Czarna Orchidea', 'slug' => 'czarna-orchidea'],
                ['name' => 'Płomień w Szybie', 'slug' => 'plomien-w-szybie'],
                ['name' => 'Węże z Nefrytu', 'slug' => 'weze-z-nefrytu'],
                ['name' => 'Ostatni Jeździec', 'slug' => 'ostatni-jezdziec'],
                ['name' => 'Serce z Ciemności', 'slug' => 'serce-z-ciemnosci'],
                ['name' => 'Wirujący Las', 'slug' => 'wirujacy-las'],
                ['name' => 'Piętno Zdrajcy', 'slug' => 'pietno-zdrajcy'],
                ['name' => 'Gwiezdny Trup', 'slug' => 'gwiezdny-trup'],
                ['name' => 'Krew na Marsie', 'slug' => 'krew-na-marsie'],
                ['name' => 'Dom Pełen Drzwi', 'slug' => 'dom-pelen-drzwi'],
                ['name' => 'Zabójca Czasu', 'slug' => 'zabojca-czasu'],
                ['name' => 'Córka Cienia', 'slug' => 'corka-cienia'],
                ['name' => 'Mechaniczny Anioł', 'slug' => 'mechaniczny-aniol'],
                ['name' => 'Przeklęty Ród', 'slug' => 'przeklety-rod'],
                ['name' => 'Stacja Orbitalna 13', 'slug' => 'stacja-orbitalna-13'],
                ['name' => 'Szczury z Neonowego Dna', 'slug' => 'szczury-z-neonowego-dna'],
                ['name' => 'Bóg z Pudełka', 'slug' => 'bog-z-pudelka'],
                ['name' => 'Żniwa Dusz', 'slug' => 'zniwa-dusz'],
                ['name' => 'Czerwone Drzewo', 'slug' => 'czerwone-drzewo'],
                ['name' => 'Kapitan Martwego Statku', 'slug' => 'kapitan-martwego-statku'],
                ['name' => 'Szkoła dla Potworów', 'slug' => 'szkola-dla-potworow'],
                ['name' => 'Pamięć z Rdzy', 'slug' => 'pamiec-z-rdzy'],
                ['name' => 'Noc Długich Noży', 'slug' => 'noc-dlugich-nozy'],
                ['name' => 'Wilk w Owczej Skórze', 'slug' => 'wilk-w-owczej-skorze'],
                ['name' => 'Labirynt z Lustra', 'slug' => 'labirynt-z-lustra'],
                ['name' => 'Ostatnia Pieśń Smoka', 'slug' => 'ostatnia-piesn-smoka'],
                ['name' => 'Miasto Umarłych Bogów', 'slug' => 'miasto-umarlych-bogow'],
                ['name' => 'Klatka z Czasu', 'slug' => 'klatka-z-czasu'],
                ['name' => 'Głęboki Sen', 'slug' => 'gleboki-sen'],
                ['name' => 'Krew i Rdza', 'slug' => 'krew-i-rdza'],
                ['name' => 'Wrota do Piekła', 'slug' => 'wrota-do-piekla'],
                ['name' => 'Białe Pustkowie', 'slug' => 'biale-pustkowie'],
                ['name' => 'Dług u Diabła', 'slug' => 'dlug-u-diabla'],
                ['name' => 'Córka Zimy', 'slug' => 'corka-zimy'],
                ['name' => 'Sztorm nad Martwym Morzem', 'slug' => 'sztorm-nad-martwym-morzem'],
                ['name' => 'Człowiek z Papieru', 'slug' => 'czlowiek-z-papieru'],
                ['name' => 'Archiwum Zapomnianych', 'slug' => 'archiwum-zapomnianych'],
                ['name' => 'Niewidzialny Ogień', 'slug' => 'niewidzialny-ogien'],
                ['name' => 'Królestwo Lodu', 'slug' => 'krolestwo-lodu'],
                ['name' => 'Serce z Neonów', 'slug' => 'serce-z-neonow'],
                ['name' => 'Dźwięk Pękającego Nieba', 'slug' => 'dzwiek-pekajacego-nieba'],
                ['name' => 'Pocałunek Śmierci', 'slug' => 'pocalunek-smierci'],
                ['name' => 'Wieża z Krwi', 'slug' => 'wieza-z-krwi'],
                ['name' => 'Czarny Karawan', 'slug' => 'czarny-karawan'],
                ['name' => 'Duchy z Linii Wysokiego Napięcia', 'slug' => 'duchy-z-linii-wysokiego-napiec'],
                ['name' => 'Kraina Bez Słońca', 'slug' => 'kraina-bez-slonca'],
                ['name' => 'Zepsuta Pieśń', 'slug' => 'zepsuta-piesn'],
                ['name' => 'Dziedzictwo Grzechu', 'slug' => 'dziedzictwo-grzechu'],
                ['name' => 'Stare Długa', 'slug' => 'stare-dluga'],
                ['name' => 'Most do Nigdzie', 'slug' => 'most-do-nigdzie'],
                ['name' => 'Klatka dla Aniołów', 'slug' => 'klatka-dla-aniolow'],
                ['name' => 'Czerwone Niebo', 'slug' => 'czerwone-niebo'],
                ['name' => 'Sól pod Językiem', 'slug' => 'sol-pod-jezykiem'],
                ['name' => 'Ostatni Dzień Lata', 'slug' => 'ostatni-dzien-lata'],
                ['name' => 'Miasto z Popiołu', 'slug' => 'miasto-z-popiolu'],
                ['name' => 'Głód', 'slug' => 'glod'],
                ['name' => 'Szary Anioł', 'slug' => 'szary-aniol'],
                ['name' => 'Pięć Serc', 'slug' => 'piec-serc'],
                ['name' => 'Cień za Szybą', 'slug' => 'cien-za-szyba'],
                ['name' => 'Wąż w Koronie', 'slug' => 'waz-w-koronie'],
                ['name' => 'Hotel „Pod Czarnym Psem”', 'slug' => 'hotel-pod-czarnym-psem'],
                ['name' => 'Księga Umarłych Imion', 'slug' => 'ksiega-umarlych-imion'],
                ['name' => 'Mechaniczna Kołysanka', 'slug' => 'mechaniczna-kolysanka'],
                ['name' => 'Krew na Rękach Boga', 'slug' => 'krew-na-rekach-boga'],
                ['name' => 'Zimne Światło', 'slug' => 'zimne-swiatlo'],
                ['name' => 'Pamiętnik Szaleńca', 'slug' => 'pamietnik-szalenca'],
                ['name' => 'Cisza Głębin', 'slug' => 'cisza-glebin'],
                ['name' => 'Płonący Las', 'slug' => 'plonacy-las'],
                ['name' => 'Zwierciadło z Pęknięć', 'slug' => 'zwierciadlo-z-pekniec'],
                ['name' => 'Dzień, w którym Umarło Słońce', 'slug' => 'dzien-w-ktorym-umarlo-slonce'],
                ['name' => 'Srebrne Zęby', 'slug' => 'srebrne-zeby'],
                ['name' => 'Wściekłe Psy', 'slug' => 'wsciekle-psy'],
                ['name' => 'Róża z Cierni', 'slug' => 'roza-z-cierni'],
                ['name' => 'Ostatni Obserwator', 'slug' => 'ostatni-obserwator'],
                ['name' => 'Kraina Wiecznej Zimy', 'slug' => 'kraina-wiecznej-zimy'],
                ['name' => 'Grobowiec z Gwiazd', 'slug' => 'grobowiec-z-gwiazd'],
                ['name' => 'Czarny Internet', 'slug' => 'czarny-internet'],
                ['name' => 'Dzieci z Mgły', 'slug' => 'dzieci-z-mgly'],
                ['name' => 'Żelazna Korona', 'slug' => 'zelazna-korona'],
                ['name' => 'Serce Zegara', 'slug' => 'serce-zegara'],
                ['name' => 'Przeklęta Taśma', 'slug' => 'przekleta-tasma'],
                ['name' => 'Wiatr z Pustki', 'slug' => 'wiatr-z-pustki'],
                ['name' => 'Dom na Wzgórzu', 'slug' => 'dom-na-wzgorzu'],
                ['name' => 'Cień Który Śledzi', 'slug' => 'cien-ktory-sledzi'],
                ['name' => 'Krew na Klawiaturze', 'slug' => 'krew-na-klawiaturze'],
                ['name' => 'Noc Żywych Cieni', 'slug' => 'noc-zywych-cieni'],
                ['name' => 'Zaginiony Konwój', 'slug' => 'zaginiony-konwoj'],
                ['name' => 'Miasto z Lustra', 'slug' => 'miasto-z-lustra'],
                ['name' => 'Pieśń z Głębi', 'slug' => 'piesn-z-glebi'],
                ['name' => 'Zardzewiały Anioł', 'slug' => 'zardzewialy-aniol'],
                ['name' => 'Kości z Nieba', 'slug' => 'kosci-z-nieba'],
                ['name' => 'Ostatnia Stacja', 'slug' => 'ostatnia-stacja'],
                ['name' => 'Sny z Pękniętego Nieba', 'slug' => 'sny-z-peknietego-nieba'],
                ['name' => 'Królestwo z Rdzy i Krwi', 'slug' => 'krolestwo-z-rdzy-i-krwi'],
                ['name' => 'Ciemność pod Skórą', 'slug' => 'ciemnosci-pod-skora'],
                ['name' => 'Białe Piekło', 'slug' => 'biale-pieklo'],
                ['name' => 'Wir', 'slug' => 'wir'],
                ['name' => 'Długi Sen w Czerwieni', 'slug' => 'dlugi-sen-w-czerwieni'],
                ['name' => 'Maska z Twarzy', 'slug' => 'maska-z-twarzy'],
                ['name' => 'Nocna Straż', 'slug' => 'nocna-straz'],
                ['name' => 'Córka Mgły', 'slug' => 'corka-mgly'],
                ['name' => 'Stalowe Niebo', 'slug' => 'stalowe-niebo'],
                ['name' => 'Przeklęty Festiwal', 'slug' => 'przeklety-festiwal'],
                ['name' => 'Krew i Srebro', 'slug' => 'krew-i-srebro'],
                ['name' => 'Dom bez Cieni', 'slug' => 'dom-bez-cieni'],
                ['name' => 'Ostatni Smok', 'slug' => 'ostatni-smok'],
                ['name' => 'Ciche Miasto', 'slug' => 'ciche-miasto'],
                ['name' => 'Zabójca Bogów', 'slug' => 'zabojca-bogow'],
                ['name' => 'Serce z Cierni', 'slug' => 'serce-z-cierni'],
                ['name' => 'Neonowy Grób', 'slug' => 'neonowy-grob'],
                ['name' => 'Klątwa Rodu', 'slug' => 'klatwa-rodu'],
                ['name' => 'Pociąg do Nigdzie', 'slug' => 'pociag-do-nigdzie'],
                ['name' => 'Oczy w Ścianie', 'slug' => 'oczy-w-scianie'],
                ['name' => 'Czarna Wieś', 'slug' => 'czarna-wies'],
                ['name' => 'Władca Lalek', 'slug' => 'wladca-lalek'],
                ['name' => 'Szept Zmarłych', 'slug' => 'szept-zmarlych'],
                ['name' => 'Krew na Ołtarzu', 'slug' => 'krew-na-oltarzu'],
                ['name' => 'Zaginione Miasto', 'slug' => 'zaginione-miasto'],
                ['name' => 'Mechaniczny Bóg', 'slug' => 'mechaniczny-bog'],
                ['name' => 'Noc Długich Cieni', 'slug' => 'noc-dlugich-cieni'],
                ['name' => 'Płonące Niebo', 'slug' => 'plonace-niebo'],
                ['name' => 'Cień Ojca', 'slug' => 'cien-ojca'],
                ['name' => 'Kraina Bez Powrotu', 'slug' => 'kraina-bez-powrotu'],
                ['name' => 'Ostatni Dzień', 'slug' => 'ostatni-dzien'],
                ['name' => 'Szpital dla Umarłych', 'slug' => 'szpital-dla-umarlych'],
                ['name' => 'Klatka z Czasu', 'slug' => 'klatka-z-czasu'],
                ['name' => 'Czarny Las', 'slug' => 'czarny-las'],
                ['name' => 'Dźwięk Pękającego Serca', 'slug' => 'dzwiek-pekajacego-serca'],
                ['name' => 'Władca Much', 'slug' => 'wladca-much'],
                ['name' => 'Srebrny Wilk', 'slug' => 'srebrny-wilk'],
                ['name' => 'Miasto z Popiołu i Snów', 'slug' => 'miasto-z-popiolu-i-snow'],
                ['name' => 'Przeklęta Taśma Video', 'slug' => 'przekleta-tasma-video'],
                ['name' => 'Anioł z Rdzy', 'slug' => 'aniol-z-rdzy'],
                ['name' => 'Głęboki Sen', 'slug' => 'gleboki-sen'],
                ['name' => 'Krwawy Księżyc', 'slug' => 'krwawy-ksiezyc'],
                ['name' => 'Dom na Końcu Drogi', 'slug' => 'dom-na-koncu-drogi'],
                ['name' => 'Zimna Krew', 'slug' => 'zimna-krew'],
                ['name' => 'Ostatni Koncert', 'slug' => 'ostatni-koncert'],
                ['name' => 'Cień w Lustrze', 'slug' => 'cien-w-lustrze'],
                ['name' => 'Królestwo z Mgły', 'slug' => 'krolestwo-z-mgly'],
                ['name' => 'Neon i Krew', 'slug' => 'neon-i-krew'],
                ['name' => 'Pieśń z Pustki', 'slug' => 'piesn-z-pustki'],
                ['name' => 'Złoty Grób', 'slug' => 'zloty-grob'],
                ['name' => 'Wściekłość', 'slug' => 'wscieklosc'],
                ['name' => 'Czarny Anioł', 'slug' => 'czarny-aniol'],
                ['name' => 'Długi Sen', 'slug' => 'dlugi-sen'],
                ['name' => 'Miasto Umarłych', 'slug' => 'miasto-umarlych'],
                ['name' => 'Płomień w Ciemności', 'slug' => 'plomien-w-ciemnosci'],
                ['name' => 'Kości z Gwiazd', 'slug' => 'kosci-z-gwiazd'],
                ['name' => 'Przeklęty Zamek', 'slug' => 'przeklety-zamek'],
                ['name' => 'Serce z Ciemności', 'slug' => 'serce-z-ciemnosci'],
                ['name' => 'Ostatnia Latarnia', 'slug' => 'ostatnia-latarnia'],
                ['name' => 'Krew na Śniegu', 'slug' => 'krew-na-sniegu'],
                ['name' => 'Cisza', 'slug' => 'cisza'],
                ['name' => 'Noc bez Gwiazd', 'slug' => 'noc-bez-gwiazd'],
                ['name' => 'Zaginiony Brat', 'slug' => 'zaginiony-brat'],
                ['name' => 'Wrota Piekieł', 'slug' => 'wrota-piekiel'],
                ['name' => 'Czerwony Deszcz', 'slug' => 'czerwony-deszcz'],
                ['name' => 'Dom Pełen Duchów', 'slug' => 'dom-pelen-duchow'],
                ['name' => 'Mechaniczna Miłość', 'slug' => 'mechaniczna-milosc'],
                ['name' => 'Królestwo Cienia', 'slug' => 'krolestwo-cienia'],
                ['name' => 'Ostatni Strażnik', 'slug' => 'ostatni-straznik'],
                ['name' => 'Sny z Krwi', 'slug' => 'sny-z-krwi'],
                ['name' => 'Czarny Wiatr', 'slug' => 'czarny-wiatr'],
                ['name' => 'Zwierciadło', 'slug' => 'zwierciadlo'],
                ['name' => 'Puste Miasto', 'slug' => 'puste-miasto'],
                ['name' => 'Krwawa Korona', 'slug' => 'krwawa-korona'],
                ['name' => 'Cień za Tobą', 'slug' => 'cien-za-toba'],
                ['name' => 'Noc Długich Noży', 'slug' => 'noc-dlugich-nozy'],
                ['name' => 'Stare Duchy', 'slug' => 'stare-duchy'],
                ['name' => 'Zimna Krew', 'slug' => 'zimna-krew'],
                ['name' => 'Ostatni Dzień', 'slug' => 'ostatni-dzien'],
                ['name' => 'Głębiny', 'slug' => 'glebiny'],
                ['name' => 'Czarny Tron', 'slug' => 'czarny-tron'],
                ['name' => 'Płonący Las', 'slug' => 'plonacy-las'],
                ['name' => 'Dom na Skraju', 'slug' => 'dom-na-skraju'],
                ['name' => 'Krew i Rdza', 'slug' => 'krew-i-rdza'],
                ['name' => 'Cisza po Krzyku', 'slug' => 'cisza-po-krzyku'],
                ['name' => 'Neonowa Apokalipsa', 'slug' => 'neonowa-apokalipsa'],
                ['name' => 'Serce z Rdzy', 'slug' => 'serce-z-rdzy'],
                ['name' => 'Ostatni Anioł', 'slug' => 'ostatni-aniol'],
                ['name' => 'Cień Boga', 'slug' => 'cien-boga'],
                ['name' => 'Krwawe Żniwa', 'slug' => 'krwawe-zniwa'],
                ['name' => 'Miasto z Lustra', 'slug' => 'miasto-z-lustra'],
                ['name' => 'Pęknięte Niebo', 'slug' => 'pekniete-niebo'],
                ['name' => 'Zimowe Serce', 'slug' => 'zimowe-serce'],
                ['name' => 'Czarny Las', 'slug' => 'czarny-las'],
                ['name' => 'Ostatnia Pieśń', 'slug' => 'ostatnia-piesn'],
                ['name' => 'Dom Pełen Drzwi', 'slug' => 'dom-pelen-drzwi'],
                ['name' => 'Krew na Marsie', 'slug' => 'krew-na-marsie'],
                ['name' => 'Córka Cienia', 'slug' => 'corka-cienia'],
                ['name' => 'Zamek z Popiołu', 'slug' => 'zamek-z-popiolu'],
            );
        }

        return $this->state(self::$predefinedSequence);
    }
}

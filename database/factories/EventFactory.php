<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function fake;
use function random_int;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Event::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->name;

        $startsAt = fake()->dateTimeBetween('+1 week', '+6 months')
            ->setTime(fake()->numberBetween(9, 17), 0);

        $startsAt = Carbon::instance($startsAt);

        $durationDays = fake()->numberBetween(0, 2);

        $endsAt = (clone $startsAt)
            ->addDays($durationDays)
            ->setTime(fake()->numberBetween(18, 23), fake()->numberBetween(0, 59));

        return [
            'name' => $name,
            'organization_id' => Organization::factory(),
            'is_public' => fake()->boolean(),
            'slug' => Str::slug($name),
            'description' => fake()->text(2000),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => User::factory(),
        ];
    }

    public function public(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => 1,
        ]);
    }

    public function private(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => 0,
        ]);
    }

    public function withSameCreatorAsOrganization(): self
    {
        return $this->afterCreating(function (Event $event) {
            if ($event->organization?->created_by) {
                $event->update([
                    'created_by' => $event->organization->created_by,
                ]);
            }
        });
    }

    public function withSlots(int $number, Collection $activityTypes): self
    {
        return $this->has(
            Slot::factory($number)
                ->consistentWithEventAndPlace()
                ->sequence(function (Sequence $sequence, $slot, $event) {
                    static $counters = [];

                    $eventId = $event->id;
                    $counters[$eventId] = ($counters[$eventId] ?? 0) + 1;

                    return [
                        'name' => 'Stół '.$counters[$eventId],
                    ];
                })
                ->withActivityTypesAttached($activityTypes)
        );
    }

    public function withVenues(Collection $venues): self
    {
        return $this->afterCreating(function (Event $event) use ($venues) {
            $event->places()->attach(
                $venues->random(random_int(1, 2))
            );
        });
    }

    public function withRandomRooms(): self
    {
        return $this->afterCreating(function (Event $event) {
            /** @var Collection $availableRooms */
            $availableRooms = Place::whereIn('parent_id', $event->places()->pluck('places.id'))
                ->get();

            if ($availableRooms->isEmpty()) {
                return;
            }

            foreach ($event->slots()->get() as $slot) {
                if (fake()->boolean(65)) {
                    $slot->update([
                        'place_id' => $availableRooms->random()->id,
                    ]);
                } else {
                    $slot->update([
                        'place_id' => $availableRooms->random()->parent_id,
                    ]);
                }
            }
        });
    }

    public function predefined(): self
    {
        return $this->state(new Sequence(
            ['name' => 'Nocne Granie',           'slug' => 'nocne-granie'],
            ['name' => 'One More Game',         'slug' => 'one-more-game'],
            ['name' => 'Epicka Sesja',          'slug' => 'epicka-sesja'],
            ['name' => 'Kryształowe Kości',     'slug' => 'krystalowe-kosci'],
            ['name' => 'ConQuest',              'slug' => 'conquest'],
            ['name' => 'Mroczne Lochy',         'slug' => 'mroczne-lochy'],
            ['name' => 'Roluj i Pal',           'slug' => 'roluj-i-pal'],
            ['name' => 'Dragon’s Den',          'slug' => 'dragons-den'],
            ['name' => 'Wielki Zlot RPG',       'slug' => 'wielki-zlot-rpg'],
            ['name' => 'Shadowrun Poland',      'slug' => 'shadowrun-poland'],
            ['name' => 'Kampania Wieczorna',    'slug' => 'kampania-wieczorna'],
            ['name' => 'Gralandia',             'slug' => 'gralandia'],
            ['name' => 'Orcus Con',             'slug' => 'orcus-con'],
            ['name' => 'Szczury z Kanałów',     'slug' => 'szczury-z-kanalow'],
            ['name' => 'Mythic Quest',          'slug' => 'mythic-quest'],
            ['name' => 'Baldur’s Gate Gathering', 'slug' => 'baldurs-gate-gathering'],
            ['name' => 'Piątek z RPG',          'slug' => 'piatek-z-rpg'],
            ['name' => 'Tower of Games',        'slug' => 'tower-of-games'],
            ['name' => 'Legendarne Kości',      'slug' => 'legendarne-kosci'],
            ['name' => 'Niezależny Zlot Fantastyki', 'slug' => 'niezalezny-zlot-fantastyki'],
        ));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\SuggestedBringItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManageActivityFormSuggestedBringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_extras_and_provisions_on_save(): void
    {
        $host = User::factory()->create();
        $rpg = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $inherited = SuggestedBringItem::factory()
            ->state([
                'bringable_type' => 'activity_type',
                'bringable_id' => $rpg->id,
            ])
            ->withTranslations(['en' => 'Pencil'])
            ->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $rpg->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $this->actingAs($host);

        Livewire::test(ManageActivityForm::class, ['activity' => $activity])
            ->set('provided_bring_item_ids', [(int) $inherited->id])
            ->set('suggested_bring_extras', [
                ['label' => 'Name tag', 'host_provides' => true],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('activities.show', $activity));

        $activity->refresh();
        $this->assertTrue($activity->providedSuggestedBringItems()->whereKey($inherited->id)->exists());
        $this->assertSame(
            ['Name tag'],
            $activity->suggestedBringExtras()
                ->with('translations')
                ->get()
                ->map(fn ($item) => $item->displayLabel())
                ->all(),
        );
        $this->assertTrue((bool) $activity->suggestedBringExtras()->first()?->host_provides);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FamiliarityLevel;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityFamiliarityService;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityFamiliarityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActivityFamiliarityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ActivityFamiliarityService::class);
    }

    #[Test]
    public function format_snapshot_for_display_returns_empty_for_null_or_empty(): void
    {
        $this->assertSame([], $this->service->formatSnapshotForDisplay(null));
        $this->assertSame([], $this->service->formatSnapshotForDisplay([]));
    }

    #[Test]
    public function format_snapshot_for_display_includes_activity_type_and_tags(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $type = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($type);

        $tag = Tag::factory()->create();
        $tag->translations()->create([
            'locale' => 'en',
            'label' => 'Familiar Tag',
        ]);

        $lines = $this->service->formatSnapshotForDisplay([
            'activity_type_id' => $type->id,
            'activity_type_level' => FamiliarityLevel::Experienced->value,
            'tags' => [
                ['tag_id' => $tag->id, 'level' => FamiliarityLevel::Beginner->value],
                ['tag_id' => 0, 'level' => FamiliarityLevel::None->value],
                'not-an-array',
            ],
        ]);

        $this->assertCount(2, $lines);
        $this->assertSame(FamiliarityLevel::Experienced->value, $lines[0]['level']);
        $this->assertNotNull($lines[0]['level_label']);
        $this->assertSame('Familiar Tag', $lines[1]['label']);
        $this->assertSame(FamiliarityLevel::Beginner->value, $lines[1]['level']);
    }

    #[Test]
    public function format_snapshot_summary_joins_labels(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $type = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($type);

        $summary = $this->service->formatSnapshotSummary([
            'activity_type_id' => $type->id,
            'activity_type_level' => FamiliarityLevel::Experienced->value,
            'tags' => [],
        ]);

        $this->assertNotSame('', $summary);
        $this->assertStringContainsString(FamiliarityLevel::Experienced->label(), $summary);
        $this->assertSame('', $this->service->formatSnapshotSummary(null));
    }

    #[Test]
    public function answer_validation_rules_include_subjects_for_activity(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $type = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $activity = Activity::factory()->create([
            'activity_type_id' => $type?->id,
        ]);
        $tag = Tag::factory()->create();
        $activity->tags()->attach($tag->id);

        $rules = $this->service->answerValidationRules($activity->fresh()->load('tags', 'activityType'));

        $this->assertNotEmpty($rules);
        foreach ($rules as $key => $ruleList) {
            $this->assertStringStartsWith('familiarity_answers.', $key);
            $this->assertContains('nullable', $ruleList);
            $this->assertContains('string', $ruleList);
        }
    }

    #[Test]
    public function prefill_levels_for_user_returns_array_keyed_by_subject(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $type = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $activity = Activity::factory()->create([
            'activity_type_id' => $type?->id,
        ]);

        $prefill = $this->service->prefillLevelsForUser($user, $activity->fresh()->load('tags', 'activityType'));

        $this->assertIsArray($prefill);
    }
}

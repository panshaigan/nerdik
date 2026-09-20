<?php

namespace App\Services;

use App\Enums\FamiliarityLevel;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityWaitlistEntry;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Models\UserFamiliarity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityFamiliarityService
{
    private const FAMILIARITY_TAG_KEYS = [
        TagCategory::KEY_GAME,
        TagCategory::KEY_MECHANIC,
        TagCategory::KEY_SETTING,
    ];

    /** @var array<int, array<string, mixed>|null> */
    private array $stagedSnapshotsByUserId = [];

    /** @var array<int, ActivityType|null> */
    private array $activityTypeCache = [];

    /** @var array<int, Tag|null> */
    private array $tagCache = [];

    /**
     * @return list<array{
     *     key: string,
     *     kind: 'activity_type'|'tag',
     *     subject_type: class-string,
     *     subject_id: int,
     *     label: string,
     *     category_key: string|null,
     *     level: string|null
     * }>
     */
    public function subjectsForActivity(Activity $activity, ?User $user = null): array
    {
        $activity->loadMissing(['activityType', 'tags.tagCategory', 'tags.translations']);

        $prefills = $user !== null
            ? $this->prefillLevelsForUser($user, $activity)
            : [];

        $subjects = [];

        if ($activity->activityType !== null) {
            $type = $activity->activityType;
            $subjectType = $type->getMorphClass();
            $key = $this->subjectKey($subjectType, (int) $type->id);
            $subjects[] = [
                'key' => $key,
                'kind' => 'activity_type',
                'subject_type' => $subjectType,
                'subject_id' => (int) $type->id,
                'label' => __('ui.activities.types.'.$type->slug),
                'category_key' => null,
                'level' => $prefills[$key] ?? null,
            ];
        }

        $tags = $activity->tags
            ->filter(function (Tag $tag): bool {
                $category = $tag->category;

                return is_string($category) && in_array($category, self::FAMILIARITY_TAG_KEYS, true);
            })
            ->sortBy(function (Tag $tag): string {
                $category = (string) $tag->category;
                $order = array_search($category, self::FAMILIARITY_TAG_KEYS, true);

                return sprintf('%02d-%s', $order === false ? 99 : $order, mb_strtolower($tag->displayLabel()));
            })
            ->values();

        foreach ($tags as $tag) {
            $subjectType = $tag->getMorphClass();
            $key = $this->subjectKey($subjectType, (int) $tag->id);
            $subjects[] = [
                'key' => $key,
                'kind' => 'tag',
                'subject_type' => $subjectType,
                'subject_id' => (int) $tag->id,
                'label' => $tag->displayLabel(),
                'category_key' => is_string($tag->category) ? $tag->category : null,
                'level' => $prefills[$key] ?? null,
            ];
        }

        return $subjects;
    }

    public function activityHasFamiliaritySubjects(Activity $activity): bool
    {
        return $this->subjectsForActivity($activity) !== [];
    }

    /**
     * @param  array<string, string|null>  $answers  subject key => level value or null/empty
     * @return array<string, mixed>|null
     */
    public function applyAnswers(User $user, Activity $activity, array $answers): ?array
    {
        $subjects = $this->subjectsForActivity($activity);
        $normalized = $this->normalizeAnswers($subjects, $answers);

        foreach ($subjects as $subject) {
            $level = $normalized[$subject['key']] ?? null;
            if ($level === null) {
                continue;
            }

            UserFamiliarity::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'subject_type' => $subject['subject_type'],
                    'subject_id' => $subject['subject_id'],
                ],
                ['level' => $level->value],
            );
        }

        return $this->buildSnapshot($subjects, $normalized);
    }

    /**
     * @param  list<array{key: string, kind: string, subject_type: class-string, subject_id: int, label: string, category_key: string|null, level: string|null}>  $subjects
     * @param  array<string, string|null>  $answers
     * @return array<string, FamiliarityLevel|null>
     */
    public function normalizeAnswers(array $subjects, array $answers): array
    {
        $allowedKeys = array_column($subjects, 'key');
        $normalized = [];

        foreach ($allowedKeys as $key) {
            $raw = $answers[$key] ?? null;
            if ($raw === null || $raw === '') {
                $normalized[$key] = null;

                continue;
            }

            $level = FamiliarityLevel::tryFromMixed($raw);
            if ($level === null) {
                throw ValidationException::withMessages([
                    "familiarity_answers.{$key}" => [__('ui.familiarity.invalid_level')],
                ]);
            }

            $normalized[$key] = $level;
        }

        return $normalized;
    }

    /**
     * @param  list<array{key: string, kind: string, subject_type: class-string, subject_id: int, label: string, category_key: string|null, level: string|null}>  $subjects
     * @param  array<string, FamiliarityLevel|null>  $normalized
     * @return array<string, mixed>|null
     */
    public function buildSnapshot(array $subjects, array $normalized): ?array
    {
        if ($subjects === []) {
            return null;
        }

        $activityTypeId = null;
        $activityTypeLevel = null;
        $tags = [];

        foreach ($subjects as $subject) {
            $level = $normalized[$subject['key']] ?? null;
            $levelValue = $level?->value;

            if ($subject['kind'] === 'activity_type') {
                $activityTypeId = $subject['subject_id'];
                $activityTypeLevel = $levelValue;

                continue;
            }

            $tags[] = [
                'tag_id' => $subject['subject_id'],
                'level' => $levelValue,
            ];
        }

        return [
            'activity_type_id' => $activityTypeId,
            'activity_type_level' => $activityTypeLevel,
            'tags' => $tags,
        ];
    }

    public function stageSnapshot(int $userId, ?array $snapshot): void
    {
        $this->stagedSnapshotsByUserId[$userId] = $snapshot;
    }

    public function consumeStagedSnapshot(int $userId): ?array
    {
        if (! array_key_exists($userId, $this->stagedSnapshotsByUserId)) {
            return null;
        }

        $snapshot = $this->stagedSnapshotsByUserId[$userId];
        unset($this->stagedSnapshotsByUserId[$userId]);

        return $snapshot;
    }

    /**
     * @return array<string, string> subject key => level value
     */
    public function prefillLevelsForUser(User $user, Activity $activity): array
    {
        $subjects = [];
        if ($activity->activity_type_id !== null) {
            $subjects[] = [(new ActivityType)->getMorphClass(), (int) $activity->activity_type_id];
        }

        $activity->loadMissing('tags');
        foreach ($activity->tags as $tag) {
            $category = $tag->category;
            if (! is_string($category) || ! in_array($category, self::FAMILIARITY_TAG_KEYS, true)) {
                continue;
            }
            $subjects[] = [$tag->getMorphClass(), (int) $tag->id];
        }

        if ($subjects === []) {
            return [];
        }

        $rows = UserFamiliarity::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as [$type, $id]) {
                    $query->orWhere(function ($inner) use ($type, $id): void {
                        $inner->where('subject_type', $type)->where('subject_id', $id);
                    });
                }
            })
            ->get();

        $prefills = [];
        foreach ($rows as $row) {
            $level = $row->level;
            if (! $level instanceof FamiliarityLevel) {
                continue;
            }
            $prefills[$this->subjectKey($row->subject_type, (int) $row->subject_id)] = $level->value;
        }

        return $prefills;
    }

    public function subjectKey(string $subjectType, int $subjectId): string
    {
        return $subjectType.'|'.$subjectId;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array{label: string, level: string|null, level_label: string|null}>
     */
    public function formatSnapshotForDisplay(?array $snapshot): array
    {
        if ($snapshot === null || $snapshot === []) {
            return [];
        }

        $lines = [];

        $activityTypeId = isset($snapshot['activity_type_id']) ? (int) $snapshot['activity_type_id'] : null;
        if ($activityTypeId !== null && $activityTypeId > 0) {
            $type = $this->resolveActivityType($activityTypeId);
            $level = FamiliarityLevel::tryFromMixed($snapshot['activity_type_level'] ?? null);
            $lines[] = [
                'label' => $type !== null
                    ? __('ui.activities.types.'.$type->slug)
                    : __('ui.familiarity.activity_type'),
                'level' => $level?->value,
                'level_label' => $level?->label(),
            ];
        }

        $tagRows = $snapshot['tags'] ?? [];
        if (! is_array($tagRows) || $tagRows === []) {
            return $lines;
        }

        $tagIds = collect($tagRows)
            ->map(fn (mixed $row): int => (int) (is_array($row) ? ($row['tag_id'] ?? 0) : 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->warmTagCache($tagIds);

        foreach ($tagRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tagId = (int) ($row['tag_id'] ?? 0);
            if ($tagId <= 0) {
                continue;
            }
            $tag = $this->tagCache[$tagId] ?? null;
            $level = FamiliarityLevel::tryFromMixed($row['level'] ?? null);
            $lines[] = [
                'label' => $tag?->displayLabel() ?? __('ui.familiarity.unknown_subject'),
                'level' => $level?->value,
                'level_label' => $level?->label(),
            ];
        }

        return $lines;
    }

    private function resolveActivityType(int $activityTypeId): ?ActivityType
    {
        if (! array_key_exists($activityTypeId, $this->activityTypeCache)) {
            $this->activityTypeCache[$activityTypeId] = ActivityType::query()->find($activityTypeId);
        }

        return $this->activityTypeCache[$activityTypeId];
    }

    /**
     * @param  list<int>  $tagIds
     */
    private function warmTagCache(array $tagIds): void
    {
        $missing = array_values(array_filter(
            $tagIds,
            fn (int $id): bool => ! array_key_exists($id, $this->tagCache)
        ));

        if ($missing === []) {
            return;
        }

        $found = Tag::query()->with(['translations', 'tagCategory'])->whereIn('id', $missing)->get()->keyBy('id');
        foreach ($missing as $id) {
            $this->tagCache[$id] = $found->get($id);
        }
    }

    public function formatSnapshotSummary(?array $snapshot): string
    {
        $lines = $this->formatSnapshotForDisplay($snapshot);
        if ($lines === []) {
            return '';
        }

        return collect($lines)
            ->map(function (array $line): string {
                $levelLabel = $line['level_label'] ?? '—';

                return $line['label'].': '.$levelLabel;
            })
            ->implode(' · ');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function familiarityFromWaitlistEntry(ActivityWaitlistEntry $entry): ?array
    {
        $value = $entry->familiarity;

        return is_array($value) ? $value : null;
    }

    /**
     * Validation rules for Livewire familiarity_answers.* fields.
     *
     * @return array<string, list<mixed>>
     */
    public function answerValidationRules(Activity $activity): array
    {
        $rules = [];
        foreach ($this->subjectsForActivity($activity) as $subject) {
            $rules['familiarity_answers.'.$subject['key']] = [
                'nullable',
                'string',
                Rule::enum(FamiliarityLevel::class),
            ];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>|null  $familiarity
     */
    public function participantCreatePayload(int $userId, ?array $familiarity): array
    {
        $payload = ['user_id' => $userId];
        if ($familiarity !== null) {
            $payload['familiarity'] = $familiarity;
        }

        return $payload;
    }
}

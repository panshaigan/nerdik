@php
    $datetimeMinuteStepSeconds = max(1, (int) config('ui-datetime.minute_step', 5)) * 60;
@endphp

<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
            <p class="mb-4 text-sm text-base-content/80">
                {{ format_in_user_tz($event->starts_at, 'Y-m-d H:i') }} – {{ format_in_user_tz($event->ends_at, 'Y-m-d H:i') }}
            </p>

            <form wire:submit.prevent="save" class="space-y-4">
                <div>
                    <x-select
                        id="activity_id"
                        wire:model="activity_id"
                        :label="__('ui.proposals.your_activity')"
                        error-field="activity_id"
                        required
                        :options="$myActivities->map(fn ($a) => [
                            'id' => $a->id,
                            'name' => $a->name.' ('.($a->activityType?->slug ? __('ui.activities.types.'.$a->activityType->slug) : __('ui.common.none')).')',
                        ])->values()->all()"
                        :placeholder="__('ui.proposals.choose_activity')"
                        placeholder-value=""
                    />
                    @if ($myActivities->isEmpty())
                        <p class="mt-1 text-sm text-warning">
                            {{ __('ui.proposals.no_activities_yet') }}
                            <a href="{{ route('activities.create') }}" class="link link-primary">{{ __('ui.proposals.create_one') }}</a>
                        </p>
                    @else
                        <p class="mt-1 text-sm text-base-content/60">
                            <a href="{{ route('activities.create') }}" class="link link-primary">{{ __('ui.proposals.create_new_activity') }}</a>
                        </p>
                    @endif
                </div>

                <div>
                    <x-proposals.preferred-slot-checklist
                        :slots="$event->slots"
                        wire-model="slot_ids"
                        error-field="slot_ids"
                    />
                </div>

                <div>
                    <x-input
                        :label="__('ui.proposals.preferred_start_time_optional')"
                        wire:model="preferred_start_time"
                        type="datetime-local"
                        :step="$datetimeMinuteStepSeconds"
                        error-field="preferred_start_time"
                    />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-button :link="route('events.show', $event)" class="btn-outline">{{ __('ui.common.cancel') }}</x-button>
                    <x-button class="btn-primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ __('ui.proposals.submit_proposal') }}</span>
                        <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>

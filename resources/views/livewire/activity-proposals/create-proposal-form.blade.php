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
                            'name' => $a->name.' ('.ucfirst($a->type->value).')',
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
                    <p class="fieldset-legend font-medium text-base-content">{{ __('ui.proposals.preferred_slots_optional') }}</p>
                    <p class="mb-2 text-sm text-base-content/60">{{ __('ui.proposals.preferred_slots_help') }}</p>
                    <div class="space-y-2">
                        @foreach ($event->slots as $slot)
                            @php
                                $slotLabel = $slot->name;
                                if ($slot->starts_at) {
                                    $slotLabel .= ' · '.format_in_user_tz($slot->starts_at, 'H:i');
                                }
                                if ($slot->activity_id) {
                                    $slotLabel .= ' ('.__('ui.proposals.taken').')';
                                }
                            @endphp
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300/80 bg-base-200/30 px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm mt-0.5"
                                    wire:model="slot_ids"
                                    value="{{ $slot->id }}"
                                    @disabled((bool) $slot->activity_id)
                                >
                                <span class="text-sm">{{ $slotLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-field-error :messages="$errors->get('slot_ids')" class="mt-2" />
                </div>

                <div>
                    <x-input
                        :label="__('ui.proposals.preferred_start_time_optional')"
                        wire:model="preferred_start_time"
                        type="datetime-local"
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

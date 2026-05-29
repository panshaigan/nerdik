@if ($previewEvent)
    <x-modal
        wire:model="eventPreviewModalOpen"
        :title="$previewEvent->name"
        box-class="max-w-4xl ui-modal-surface"
        class="backdrop-blur"
        separator
    >
        <div
            wire:key="listing-event-preview-{{ $previewEvent->id }}"
            class="space-y-5"
            data-ui="listing-event-preview-modal"
        >

            <div class="space-y-3">
                @if ($previewEvent->isCancelled())
                    <span class="badge badge-warning">{{ __('ui.events.cancelled_badge') }}</span>
                @endif

                <dl class="space-y-2.5 text-sm">
                    @if ($previewEventTimeSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('Date') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-base-content/75">
                                <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-primary/70" />
                                <span class="min-w-0 leading-snug">
                                    <span class="font-medium text-base-content/60">{{ __('Date') }}:</span>
                                    {{ $previewEventTimeSummary }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    @if ($previewEventLocationSummary !== '')
                        <div class="flex gap-2">
                            <dt class="sr-only">{{ __('ui.browse.location_label') }}</dt>
                            <dd class="flex min-w-0 flex-1 gap-2 text-base-content/75">
                                <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-primary/70" />
                                <span class="min-w-0 leading-snug">
                                    <span class="font-medium text-base-content/60">{{ __('ui.browse.location_label') }}:</span>
                                    {{ $previewEventLocationSummary }}
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-base-300 bg-base-300/70 p-4">
                @if (filled(rich_text_excerpt($previewEvent->description)))
                    <div class="rich-text-content ui-rich-text-mobile-clamp text-base-content/90">
                        {!! rich_text($previewEvent->description) !!}
                    </div>
                @else
                    <p class="text-sm text-base-content/60">{{ __('ui.events.show_no_description') }}</p>
                @endif
            </div>

            @if ($previewEventBadgeItems !== [])
                <x-ui.activity-badge-group
                    :items="$previewEventBadgeItems"
                    data-ui="listing-event-preview-badge-group"
                />
            @endif

            <div class="modal-action flex flex-wrap items-center justify-end gap-2 border-t border-base-300 pt-4" data-ui="listing-event-preview-actions">
                <x-button
                    :link="route('events.show', $previewEvent)"
                    class="btn-outline"
                    wire:navigate
                >
                    {{ __('ui.events.show_details') }}
                </x-button>
            </div>
        </div>
    </x-modal>
@endif

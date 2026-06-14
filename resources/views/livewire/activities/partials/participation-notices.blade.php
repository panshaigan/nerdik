@php
    $participationNotices = collect();

    if (filled($signupBlockedMessage ?? null) && ! $isParticipant && ! $onWaitlist && ! $canJoin) {
        $participationNotices->push([
            'message' => $signupBlockedMessage,
            'dataUi' => "{$noticeDataUiPrefix}-signup-blocked",
        ]);
    }

    if (filled($stateBlockedMessage ?? null)) {
        $participationNotices->push([
            'message' => $stateBlockedMessage,
            'dataUi' => "{$noticeDataUiPrefix}-state-blocked",
        ]);
    }

    if (($activeWindowRemainingForActivity ?? null) !== null) {
        $participationNotices->push([
            'message' => __('ui.events.enrollment_window_activity_spots_remaining', [
                'remaining' => $activeWindowRemainingForActivity,
                'max' => $activeWindowPerActivityMax,
            ]),
            'dataUi' => "{$noticeDataUiPrefix}-window-activity-cap",
        ]);
    }

    if (($activeWindowUserRemaining ?? null) !== null) {
        $participationNotices->push([
            'message' => __('ui.events.enrollment_window_user_spots_remaining', [
                'remaining' => $activeWindowUserRemaining,
            ]),
            'dataUi' => "{$noticeDataUiPrefix}-window-user-cap",
        ]);
    }
@endphp

@if ($participationNotices->isNotEmpty())
    <div
        class="alert alert-neutral rounded-md"
        data-ui="{{ $noticesContainerDataUi }}"
    >
        <x-mary-icon name="o-home" class="self-center" />

        <div>
            <div @class(['font-bold' => $participationNotices->count() > 1])>{{ __('ui.common.attention') }}</div>

            @if ($participationNotices->count() === 1)
                <p class="text-xs" data-ui="{{ $participationNotices->first()['dataUi'] }}">
                    {{ $participationNotices->first()['message'] }}
                </p>
            @else
                <ul class="mt-1 list-disc space-y-1 pl-4 text-xs">
                    @foreach ($participationNotices as $notice)
                        <li data-ui="{{ $notice['dataUi'] }}">{{ $notice['message'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif

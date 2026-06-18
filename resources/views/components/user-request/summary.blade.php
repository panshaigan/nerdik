@props([
    'request',
    'messageClamp' => null,
])

@php
    use App\Enums\UserRequestType;

    $isOrganizationRequest = in_array(
        $request->type,
        [UserRequestType::OrganizationInvite, UserRequestType::OrganizationJoinRequest],
        true,
    );
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0 space-y-3 text-sm']) }}>
    <p class="font-medium">{{ $request->type->label() }}</p>

    <div>
        <p class="text-base-content/60">{{ __('ui.user_requests.from_label') }}:</p>
        <div class="mt-1">
            @if ($request->requester)
                <x-user-badge :user="$request->requester" size="sm" class="min-w-0" />
            @else
                <span class="text-base-content/70">{{ __('ui.common.unknown_user') }}</span>
            @endif
        </div>
    </div>

    <div>
        @if ($isOrganizationRequest)
            <p class="text-base-content/60">{{ __('ui.user_requests.organization_label') }}:</p>
            <div class="mt-1">
                <x-user-request.subject :request="$request" />
            </div>
        @else
            <x-user-request.subject :request="$request" />
        @endif
    </div>

    @if ($request->message)
        <p @class([
            'text-base-content/60 italic',
            'line-clamp-2' => $messageClamp === 2,
        ])>
            {{ $request->message }}
        </p>
    @endif

    @if ($request->expires_at)
        <p class="text-xs text-base-content/50">
            {{ __('ui.user_requests.expires_at', ['time' => $request->expires_at->diffForHumans()]) }}
        </p>
    @endif
</div>

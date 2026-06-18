@props([
    'request',
])

@php
    use App\Enums\UserRequestType;
    use App\Models\Activity;
    use App\Models\Organization;
    use App\Services\UserRequests\UserRequestSubjectLabelResolver;

    $subject = $request->subject;
    $labelResolver = app(UserRequestSubjectLabelResolver::class);
@endphp

@if ($request->type === UserRequestType::ActivityInvite && $subject instanceof Activity)
    <a
        href="{{ route('activities.show', $subject) }}"
        wire:navigate
        class="link link-hover truncate font-medium"
    >
        {{ $subject->name }}
    </a>
@elseif (
    in_array($request->type, [UserRequestType::OrganizationInvite, UserRequestType::OrganizationJoinRequest], true)
    && $subject instanceof Organization
)
    <x-user-badge :organization="$subject" size="sm" class="min-w-0" />
@else
    <span class="truncate text-base-content/70">{{ $labelResolver->resolve($request) }}</span>
@endif

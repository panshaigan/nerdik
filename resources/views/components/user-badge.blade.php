@props([
    'user' => null,
    'name' => null,
    'avatarPath' => null,
    'size' => 'md',
    'nameClass' => '',
    'subline' => null,
])

@php
    $resolvedName = trim((string) ($name ?? $user?->nickname ?? $user?->name ?? $user?->email ?? __('ui.common.unknown_user')));
    $resolvedAvatarPath = $avatarPath ?? $user?->avatar_path;
    $avatarUrl = null;
    if (is_string($resolvedAvatarPath) && $resolvedAvatarPath !== '') {
        $avatarUrl = str_starts_with($resolvedAvatarPath, 'http://') || str_starts_with($resolvedAvatarPath, 'https://')
            ? $resolvedAvatarPath
            : \Illuminate\Support\Facades\Storage::disk('public')->url($resolvedAvatarPath);
    }

    $initials = collect(preg_split('/\s+/u', $resolvedName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($resolvedName, 0, 1));
    }

    $avatarSizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-11 w-11 text-base',
        default => 'h-9 w-9 text-sm',
    };
@endphp

<div {{ $attributes->class('flex items-center gap-2 min-w-0') }}>
    <div class="avatar">
        <div class="{{ $avatarSizeClass }} shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $resolvedName }}" class="h-full w-full object-cover" loading="lazy" />
            @else
                <span class="flex h-full w-full items-center justify-center font-semibold">{{ $initials }}</span>
            @endif
        </div>
    </div>
    <div class="min-w-0">
        <p class="{{ $nameClass !== '' ? $nameClass : 'truncate text-sm font-semibold text-base-content' }}">{{ $resolvedName }}</p>
        @if ($subline)
            <p class="truncate text-xs text-base-content/65">{{ $subline }}</p>
        @endif
    </div>
</div>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $activity->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ session('status') }}</p>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Type') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($activity->type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Host') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $activity->host?->nickname ?? $activity->host?->email ?? '—' }}</dd>
                    </div>
                    @if ($activity->creator)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Created by') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $activity->creator->nickname ?? $activity->creator->email }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Participants') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $activity->participants()->count() }}
                            @if ($activity->max_participants !== null)
                                / {{ $activity->max_participants }}
                            @endif
                        </dd>
                    </div>
                    @if ($activity->duration_minutes)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Duration') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $activity->duration_minutes }} min</dd>
                        </div>
                    @endif
                </dl>
                @if ($activity->tags->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-sm font-medium text-gray-500 mb-2">{{ __('Tags') }}</p>
                        @include('tags.partials.inline', ['tags' => $activity->tags, 'class' => ''])
                    </div>
                @endif

                @auth
                    <div class="mt-6 flex flex-wrap gap-3 items-center">
                        @if ($inWishlist)
                            <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-amber-600 hover:text-amber-800">★ {{ __('Remove from wishlist') }}</button>
                            </form>
                        @else
                            <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-sm text-gray-600 hover:text-amber-600">☆ {{ __('Add to wishlist') }}</button>
                            </form>
                        @endif
                        @if ($isParticipant)
                            <form action="{{ route('activities.leave', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-error">{{ __('Leave activity') }}</x-button>
                            </form>
                        @elseif ($onWaitlist)
                            <form action="{{ route('activities.leave-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-neutral">{{ __('Leave waitlist') }}</x-button>
                            </form>
                        @elseif ($canJoin && ! $isFull)
                            <form action="{{ route('activities.join', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-primary">{{ __('Join activity') }}</x-button>
                            </form>
                        @endif
                        @if ($canJoin && $isFull)
                            <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-warning">{{ __('Join waitlist') }}</x-button>
                            </form>
                        @elseif ($canJoin && ! $isFull)
                            <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">
                                    {{ __('Or join waitlist') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endauth
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Participants') }}</h3>
                <ul class="divide-y divide-gray-200">
                    @forelse ($activity->participants as $p)
                        <li class="py-2 flex items-center justify-between">
                            <span class="text-sm">
                                {{ $p->user->nickname ?? $p->user->email }}
                                @if ($p->is_host) <span class="text-gray-500">({{ __('Host') }})</span> @endif
                                @if ($p->is_absent) <span class="text-red-600">({{ __('Absent') }})</span> @endif
                            </span>
                            @if ($isHost && ! $p->is_host && ! $p->is_absent)
                                <form action="{{ route('activity-participants.mark-absent', $p) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800">
                                        {{ __('Mark absent') }}
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">{{ __('No participants yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            @if ($activity->waitlist->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Waitlist') }}</h3>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($activity->waitlist as $entry)
                            <li class="py-2 text-sm">
                                #{{ $entry->position }} {{ $entry->user->nickname ?? $entry->user->email }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap gap-3 items-center">
                <a href="{{ route('activities.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    {{ __('Back to activities') }}
                </a>
                <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-sm text-gray-500 hover:text-gray-700" :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy link') }}'">
                    <span x-show="!copied">{{ __('Share') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Link copied!') }}</span>
                </button>
                @auth
                    @if ($activity->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                        <a href="{{ route('activities.edit', $activity) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                            {{ __('Edit activity') }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</x-app-layout>

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

                @auth
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($isParticipant)
                            <form action="{{ route('activities.leave', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-primary-button type="submit" class="!bg-red-600 hover:!bg-red-500">
                                    {{ __('Leave activity') }}
                                </x-primary-button>
                            </form>
                        @elseif ($onWaitlist)
                            <form action="{{ route('activities.leave-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-primary-button type="submit" class="!bg-gray-600 hover:!bg-gray-500">
                                    {{ __('Leave waitlist') }}
                                </x-primary-button>
                            </form>
                        @elseif ($canJoin && ! $isFull)
                            <form action="{{ route('activities.join', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-primary-button type="submit">{{ __('Join activity') }}</x-primary-button>
                            </form>
                        @endif
                        @if ($canJoin && $isFull)
                            <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-primary-button type="submit" class="!bg-amber-600 hover:!bg-amber-500">
                                    {{ __('Join waitlist') }}
                                </x-primary-button>
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

            <div class="flex gap-3">
                <a href="{{ route('activities.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    {{ __('Back to activities') }}
                </a>
                @auth
                    @if ($activity->host_user_id === auth()->id() || $activity->participants()->where('user_id', auth()->id())->exists())
                        <a href="{{ route('activities.edit', $activity) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                            {{ __('Edit activity') }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</x-app-layout>

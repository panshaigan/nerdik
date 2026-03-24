<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $instance->name ?? $instance->event->name }} · {{ $instance->event->name }}
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
                        <dt class="text-sm font-medium text-gray-500">{{ __('Event') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $instance->event->name }}</dd>
                    </div>
                    @if ($instance->event->creator)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Event owner') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $instance->event->creator->nickname ?? $instance->event->creator->email }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Start') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_in_user_tz($instance->starts_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('End') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_in_user_tz($instance->ends_at) }}</dd>
                    </div>
                </dl>
                @if ($instance->event->tags->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-sm font-medium text-gray-500 mb-2">{{ __('Event tags') }}</p>
                        @include('tags.partials.inline', ['tags' => $instance->event->tags, 'class' => ''])
                    </div>
                @endif
                @if ($instance->desc)
                    <p class="mt-4 text-sm text-gray-600">{{ $instance->desc }}</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-3 items-center">
                    <a href="{{ route('event-instances.edit', $instance) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                    @auth
                        <a href="{{ route('event-instances.propose', $instance) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                            {{ __('Propose an activity') }}
                        </a>
                    @endauth
                    <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-sm text-gray-500 hover:text-gray-700" :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy link') }}'">
                        <span x-show="!copied">{{ __('Share') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Link copied!') }}</span>
                    </button>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">{{ __('Slots') }}</h3>
                <ul class="divide-y divide-gray-200">
                    @forelse ($instance->slots as $slot)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <span class="font-medium">{{ $slot->name }}</span>
                                @if ($slot->starts_at)
                                    <span class="text-gray-500 text-sm"> · {{ format_in_user_tz($slot->starts_at, 'H:i') }}</span>
                                @endif
                                @if ($slot->place)
                                    <span class="text-gray-500 text-sm"> · {{ $slot->place->name }}</span>
                                @endif
                                @if ($slot->activity)
                                    <span class="text-sm text-indigo-600">
                                        → <a href="{{ route('activities.show', $slot->activity) }}" class="hover:underline">{{ $slot->activity->name }}</a>
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">— {{ __('free') }}</span>
                                @endif
                            </div>
                            @auth
                                <a href="{{ route('slots.edit', $slot) }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Edit slot') }}</a>
                            @endauth
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">{{ __('No slots yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            @if ($isOwner && $pendingProposals->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg p-6 border-l-4 border-amber-400">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('Pending proposals') }}</h3>
                    <p class="text-sm text-gray-600 mb-3">{{ __('As the event organizer, choose a slot for each activity below and click Accept, or Reject.') }}</p>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($pendingProposals as $proposal)
                            <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-medium">{{ $proposal->activity->name }}</span>
                                    <span class="text-gray-500 text-sm"> · {{ __('by') }} {{ $proposal->creator->nickname ?? $proposal->creator->email }}</span>
                                </div>
                                @php $freeSlots = $instance->slots->where('activity_id', null); @endphp
                                <div class="flex flex-wrap gap-2 items-center">
                                    @if ($freeSlots->isNotEmpty())
                                        <form action="{{ route('activity-proposals.accept', $proposal) }}" method="POST" class="inline flex items-center gap-1">
                                            @csrf
                                            <select name="slot_id" required class="rounded border-gray-300 text-sm py-1">
                                                <option value="">{{ __('Choose slot') }}</option>
                                                @foreach ($freeSlots as $s)
                                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="text-sm text-green-600 hover:text-green-800">{{ __('Accept') }}</button>
                                        </form>
                                    @else
                                        <span class="text-sm text-gray-400">{{ __('No free slots') }}</span>
                                    @endif
                                    <form action="{{ route('activity-proposals.reject', $proposal) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">{{ __('Reject') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex gap-3">
                <a href="{{ route('event-instances.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to event instances') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>

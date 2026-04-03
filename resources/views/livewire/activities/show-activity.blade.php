<div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow-sm">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium opacity-70">{{ __('Type') }}</dt>
                        <dd class="mt-1 text-sm text-base-content">{{ ucfirst($activity->type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium opacity-70">{{ __('Host') }}</dt>
                        <dd class="mt-1 text-sm text-base-content">{{ $activity->host?->nickname ?? $activity->host?->email ?? '—' }}</dd>
                    </div>
                    @if ($activity->creator)
                        <div>
                            <dt class="text-sm font-medium opacity-70">{{ __('Created by') }}</dt>
                            <dd class="mt-1 text-sm text-base-content">{{ $activity->creator->nickname ?? $activity->creator->email }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium opacity-70">{{ __('Participants') }}</dt>
                        <dd class="mt-1 text-sm text-base-content">
                            {{ $activity->participants()->count() }}
                            @if ($activity->max_participants !== null)
                                / {{ $activity->max_participants }}
                            @endif
                        </dd>
                    </div>
                    @if ($activity->duration_minutes)
                        <div>
                            <dt class="text-sm font-medium opacity-70">{{ __('Duration') }}</dt>
                            <dd class="mt-1 text-sm text-base-content">{{ $activity->duration_minutes }} min</dd>
                        </div>
                    @endif
                </dl>
                @if (filled(rich_text_excerpt($activity->desc)))
                    <div class="rich-text-content mt-6 border-t border-base-300 pt-4 text-sm text-base-content/90">
                        {!! rich_text($activity->desc) !!}
                    </div>
                @endif
                @if ($activity->tags->isNotEmpty())
                    <div class="mt-4">
                        <p class="mb-2 text-sm font-medium opacity-70">{{ __('Tags') }}</p>
                        @include('tags.partials.inline', ['tags' => $activity->tags, 'class' => ''])
                    </div>
                @endif

                @auth
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        @if ($inWishlist)
                            <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" class="btn-ghost btn-sm text-warning">★ {{ __('Remove from wishlist') }}</x-button>
                            </form>
                        @else
                            <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-ghost btn-sm">☆ {{ __('Add to wishlist') }}</x-button>
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
                                <x-button type="submit" class="btn-link btn-sm">{{ __('Or join waitlist') }}</x-button>
                            </form>
                        @endif
                    </div>
                @endauth
            </div>

            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow-sm">
                <h3 class="mb-2 text-lg font-medium text-base-content">{{ __('Participants') }}</h3>
                <ul class="divide-y divide-base-300">
                    @forelse ($activity->participants as $p)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-sm">
                                {{ $p->user->nickname ?? $p->user->email }}
                                @if ($p->is_host) <span class="opacity-70">({{ __('Host') }})</span> @endif
                                @if ($p->is_absent) <span class="text-error">({{ __('Absent') }})</span> @endif
                            </span>
                            @if ($isHost && ! $p->is_host && ! $p->is_absent)
                                <form action="{{ route('activity-participants.mark-absent', $p) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-ghost btn-xs text-error">{{ __('Mark absent') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm opacity-70">{{ __('No participants yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            @if ($activity->waitlist->isNotEmpty())
                <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow-sm">
                    <h3 class="mb-2 text-lg font-medium text-base-content">{{ __('Waitlist') }}</h3>
                    <ul class="divide-y divide-base-300">
                        @foreach ($activity->waitlist as $entry)
                            <li class="py-2 text-sm">
                                #{{ $entry->position }} {{ $entry->user->nickname ?? $entry->user->email }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('activities.index') }}" class="link link-hover text-sm opacity-80">
                    {{ __('Back to activities') }}
                </a>
                <button
                    type="button"
                    x-data="{ copied: false }"
                    x-on:click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="link link-hover text-sm opacity-70"
                    :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy link') }}'"
                >
                    <span x-show="!copied">{{ __('Share') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Link copied!') }}</span>
                </button>
                @auth
                    @if ($activity->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                        <a href="{{ route('activities.edit', $activity) }}" class="link link-primary text-sm">
                            {{ __('Edit activity') }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>

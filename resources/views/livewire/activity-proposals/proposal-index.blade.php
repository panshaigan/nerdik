<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.proposals.activity') }}</th>
                            <th>{{ __('ui.proposals.event') }}</th>
                            <th>{{ __('ui.proposals.proposer') }}</th>
                            <th>{{ __('ui.proposals.status') }}</th>
                            <th class="w-0"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($proposals as $proposal)
                            <tr>
                                <td class="font-medium opacity-90">{{ $proposal->activity->name }}</td>
                                <td class="opacity-80">
                                    {{ $proposal->event->name }}
                                    –
                                    {{ format_date_in_user_tz($proposal->event->starts_at) }}
                                </td>
                                <td class="opacity-80">{{ $proposal->creator->nickname ?? $proposal->creator->email }}</td>
                                <td class="opacity-80">{{ ucfirst($proposal->status->value) }}</td>
                                <td class="text-end">
                                    @if ($proposal->acceptedSlot)
                                        <span class="text-sm text-success">
                                            {{ __('ui.proposals.accepted_in_slot') }}: {{ $proposal->acceptedSlot->name }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center opacity-70">
                                    {{ __('ui.proposals.no_proposals_yet') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

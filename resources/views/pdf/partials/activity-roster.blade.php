@php
    /** @var array{name: string, host: string|null, gameNames: list<string>, participants: list<array{name: string, is_absent: bool}>} $roster */
@endphp
<section class="activity-roster">
    <h2 class="activity-title">{{ $roster['name'] }}</h2>

    @if ($roster['host'] !== null && $roster['host'] !== '')
        <p class="meta"><strong>{{ __('ui.pdf.roster.host') }}:</strong> {{ $roster['host'] }}</p>
    @endif

    @if ($roster['gameNames'] !== [])
        <p class="meta"><strong>{{ __('ui.pdf.roster.games') }}:</strong> {{ implode(', ', $roster['gameNames']) }}</p>
    @endif

    <table class="participants">
        <thead>
            <tr>
                <th class="col-check">{{ __('ui.pdf.roster.present') }}</th>
                <th class="col-name">{{ __('ui.pdf.roster.participant') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($roster['participants'] as $participant)
                <tr>
                    <td class="col-check">
                        <span class="checkbox"></span>
                    </td>
                    <td class="col-name">
                        {{ $participant['name'] }}
                        @if ($participant['is_absent'])
                            <span class="absent-note">({{ __('ui.pdf.roster.absent_note') }})</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="empty">{{ __('ui.pdf.roster.no_participants') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

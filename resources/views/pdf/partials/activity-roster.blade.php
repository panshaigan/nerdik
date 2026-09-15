@php
    /** @var array{name: string, host: string|null, gameNames: list<string>, when: string|null, where: string|null, participants: list<array{name: string, is_absent: bool}>} $roster */
    $showHeading = $showHeading ?? true;
    $gameLabel = count($roster['gameNames']) === 1
        ? __('ui.pdf.roster.game')
        : __('ui.pdf.roster.games');
@endphp
<section class="activity-roster">
    @if ($showHeading)
        <h2 class="activity-title">{{ $roster['name'] }}</h2>
    @endif

    @if ($roster['host'] !== null && $roster['host'] !== '')
        <p class="meta"><strong>{{ __('ui.pdf.roster.host') }}:</strong> {{ $roster['host'] }}</p>
    @endif

    @if ($roster['gameNames'] !== [])
        <p class="meta"><strong>{{ $gameLabel }}:</strong> {{ implode(', ', $roster['gameNames']) }}</p>
    @endif

    @if (($roster['when'] ?? null) !== null && $roster['when'] !== '')
        <p class="meta"><strong>{{ __('ui.pdf.roster.when') }}:</strong> {{ $roster['when'] }}</p>
    @endif

    @if (($roster['where'] ?? null) !== null && $roster['where'] !== '')
        <p class="meta"><strong>{{ __('ui.pdf.roster.where') }}:</strong> {{ $roster['where'] }}</p>
    @endif

    <table class="participants">
        <thead>
            <tr>
                <th class="col-name">{{ __('ui.pdf.roster.participant') }}</th>
                <th class="col-check">{{ __('ui.pdf.roster.present') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($roster['participants'] as $participant)
                <tr>
                    <td class="col-name">
                        {{ $participant['name'] }}
                        @if ($participant['is_absent'])
                            <span class="absent-note">({{ __('ui.pdf.roster.absent_note') }})</span>
                        @endif
                    </td>
                    <td class="col-check">
                        <span class="checkbox"></span>
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

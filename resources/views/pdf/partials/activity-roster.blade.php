@php
    /** @var array{name: string, host: string|null, gameNames: list<string>, when: string|null, where: string|null, participants: list<array{name: string, is_absent: bool}>} $roster */
    $showHeading = $showHeading ?? true;
    $maxParticipantColumns = max(1, (int) ($maxParticipantColumns ?? 1));
    $gameLabel = count($roster['gameNames']) === 1
        ? __('ui.pdf.roster.game')
        : __('ui.pdf.roster.games');
    $scheduleBits = array_values(array_filter(
        [
            ($roster['where'] ?? null) !== null && $roster['where'] !== '' ? $roster['where'] : null,
            ($roster['when'] ?? null) !== null && $roster['when'] !== '' ? $roster['when'] : null,
        ],
        fn (?string $bit): bool => $bit !== null,
    ));

    $participants = $roster['participants'];
    $participantCount = count($participants);
    $participantColumnCount = 1;
    if ($maxParticipantColumns >= 3 && $participantCount > 24) {
        $participantColumnCount = 3;
    } elseif ($maxParticipantColumns >= 2 && $participantCount > 12) {
        $participantColumnCount = 2;
    }
    $participantColumns = $participantColumnCount > 1
        ? array_chunk($participants, (int) ceil($participantCount / $participantColumnCount))
        : [$participants];
@endphp
<section class="activity-roster">
    @if ($showHeading)
        <h2 class="activity-title">
            {{ $roster['name'] }}@if ($scheduleBits !== []) <span class="header-schedule">· {{ implode(' · ', $scheduleBits) }}</span>@endif
        </h2>
    @endif

    @if ($roster['host'] !== null && $roster['host'] !== '')
        <p class="meta"><strong>{{ __('ui.pdf.roster.host') }}:</strong> {{ $roster['host'] }}</p>
    @endif

    @if ($roster['gameNames'] !== [])
        <p class="meta"><strong>{{ $gameLabel }}:</strong> {{ implode(', ', $roster['gameNames']) }}</p>
    @endif

    @if ($participantColumnCount > 1)
        <table class="participants-columns">
            <tr>
                @foreach ($participantColumns as $columnParticipants)
                    <td class="participants-column">
                        @include('pdf.partials.activity-roster-participants-table', [
                            'participants' => $columnParticipants,
                            'showHeader' => true,
                        ])
                    </td>
                @endforeach
                @for ($i = count($participantColumns); $i < $participantColumnCount; $i++)
                    <td class="participants-column"></td>
                @endfor
            </tr>
        </table>
    @else
        @include('pdf.partials.activity-roster-participants-table', [
            'participants' => $participants,
            'showHeader' => true,
        ])
    @endif
</section>

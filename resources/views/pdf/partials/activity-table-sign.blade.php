@php
    /** @var array{name: string, slotName: string|null, gameNames: list<string>} $roster */
    $sessionName = trim((string) ($roster['name'] ?? ''));
    $sessionName = $sessionName !== '' ? $sessionName : null;

    $slotName = ($roster['slotName'] ?? null) !== null && $roster['slotName'] !== ''
        ? $roster['slotName']
        : null;
    $gameLabel = $roster['gameNames'] !== []
        ? implode(', ', $roster['gameNames'])
        : null;

    // Prefer the activity name when slot/game labels duplicate it (or each other).
    if ($sessionName !== null) {
        if ($slotName !== null && strcasecmp($slotName, $sessionName) === 0) {
            $slotName = null;
        }
        if ($gameLabel !== null && strcasecmp($gameLabel, $sessionName) === 0) {
            $gameLabel = null;
        }
    }
    if ($slotName !== null && $gameLabel !== null && strcasecmp($slotName, $gameLabel) === 0) {
        $gameLabel = null;
    }
@endphp
<table class="activity-table-sign">
    <tr>
        <td>
            @if ($slotName !== null)
                <p class="sign-slot">{{ $slotName }}</p>
            @endif
            @if ($sessionName !== null)
                <p class="sign-session">{{ $sessionName }}</p>
            @endif
            @if ($gameLabel !== null)
                <p class="sign-game">{{ $gameLabel }}</p>
            @endif
            <p class="sign-brand">
                @include('pdf.partials.brand-logo', ['path' => $brandLogos['sign'], 'height' => 48])
            </p>
        </td>
    </tr>
</table>

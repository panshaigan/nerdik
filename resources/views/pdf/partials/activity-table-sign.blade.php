@php
    /** @var array{name: string, slotName: string|null, gameNames: list<string>} $roster */
    $slotName = ($roster['slotName'] ?? null) !== null && $roster['slotName'] !== ''
        ? $roster['slotName']
        : null;
    $sessionName = trim((string) ($roster['name'] ?? ''));
    $sessionName = $sessionName !== '' ? $sessionName : null;
    $gameLabel = $roster['gameNames'] !== []
        ? implode(', ', $roster['gameNames'])
        : null;
@endphp
<style>
    table.activity-table-sign {
        page-break-before: always;
        width: 100%;
        height: 180mm;
        border-collapse: collapse;
        font-family: DejaVu Sans, sans-serif;
        color: #111;
    }
    table.activity-table-sign td {
        vertical-align: middle;
        text-align: center;
        padding: 24px 36px;
    }
    .activity-table-sign .sign-slot {
        font-size: 50pt;
        font-weight: bold;
        line-height: 1.2;
        margin: 0 0 32px;
    }
    .activity-table-sign .sign-session {
        font-size: 72pt;
        font-weight: bold;
        line-height: 1.15;
        margin: 0 0 32px;
    }
    .activity-table-sign .sign-game {
        font-size: 54pt;
        font-weight: bold;
        line-height: 1.2;
        margin: 0;
    }
</style>
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
        </td>
    </tr>
</table>

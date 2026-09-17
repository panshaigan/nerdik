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
    .activity-table-sign {
        page-break-before: always;
        width: 100%;
        height: 100%;
        text-align: center;
        font-family: DejaVu Sans, sans-serif;
        color: #111;
    }
    .activity-table-sign-inner {
        padding: 48px 36px;
    }
    .activity-table-sign .sign-slot {
        font-size: 44pt;
        font-weight: bold;
        line-height: 1.2;
        margin: 0 0 28px;
    }
    .activity-table-sign .sign-session {
        font-size: 64pt;
        font-weight: bold;
        line-height: 1.15;
        margin: 0 0 28px;
    }
    .activity-table-sign .sign-game {
        font-size: 48pt;
        font-weight: bold;
        line-height: 1.2;
        margin: 0;
    }
</style>
<section class="activity-table-sign">
    <div class="activity-table-sign-inner">
        @if ($slotName !== null)
            <p class="sign-slot">{{ $slotName }}</p>
        @endif
        @if ($sessionName !== null)
            <p class="sign-session">{{ $sessionName }}</p>
        @endif
        @if ($gameLabel !== null)
            <p class="sign-game">{{ $gameLabel }}</p>
        @endif
    </div>
</section>

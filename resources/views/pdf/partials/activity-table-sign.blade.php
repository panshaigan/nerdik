@php
    /** @var array{name: string, slotName: string|null, gameNames: list<string>} $roster */
    /** @var array{header: string, sign: string, footer: string} $brandLogos */

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

    // DomPDF cannot split oversized table cells and inserts blank pages. Keep type
    // scaled, then insert a spacer block so the label is optically centered.
    // (padding-top alone is ignored/collapsed by DomPDF in this context.)
    $sessionLen = $sessionName !== null ? mb_strlen($sessionName) : 0;
    $gameLen = $gameLabel !== null ? mb_strlen($gameLabel) : 0;
    $slotLen = $slotName !== null ? mb_strlen($slotName) : 0;
    $dense = ($slotName !== null ? 1 : 0) + ($sessionName !== null ? 1 : 0) + ($gameLabel !== null ? 1 : 0) >= 3
        || $sessionLen > 28
        || $gameLen > 32
        || $slotLen > 24;

    $slotSizePt = $dense ? 34.0 : 42.0;
    $sessionSizePt = $sessionLen > 36 ? 36.0 : ($dense ? 42.0 : 54.0);
    $gameSizePt = $gameLen > 36 ? 28.0 : ($dense ? 32.0 : 40.0);
    $gapPt = $dense ? 18.0 : 24.0;
    $lineHeight = 1.15;
    // Landscape A4 content width after @page side margins (~28px) and sign padding.
    $charsPerLine = static fn (float $sizePt): int => max(12, (int) floor(720 / max($sizePt * 0.55, 1)));

    $estimatedHeight = 40.0; // brand logo
    if ($slotName !== null) {
        $slotLines = max(1, (int) ceil($slotLen / $charsPerLine($slotSizePt)));
        $estimatedHeight += ($slotSizePt * $lineHeight * $slotLines) + $gapPt;
    }
    if ($sessionName !== null) {
        $sessionLines = max(1, (int) ceil($sessionLen / $charsPerLine($sessionSizePt)));
        $estimatedHeight += ($sessionSizePt * $lineHeight * $sessionLines) + $gapPt;
    }
    if ($gameLabel !== null) {
        $gameLines = max(1, (int) ceil($gameLen / $charsPerLine($gameSizePt)));
        $estimatedHeight += ($gameSizePt * $lineHeight * $gameLines) + $gapPt;
    }

    // Usable landscape height after @page margins (~24px each) with a safety margin.
    // DomPDF wraps a bit less aggressively than the estimator, so weight the estimate
    // down slightly to keep short signs from sitting high on the page.
    $usableHeightPt = 510.0;
    $spacerPt = max(12.0, ($usableHeightPt - ($estimatedHeight * 0.88)) / 2.0);
@endphp
{{-- Styles live with the sign so activity + event PDFs stay in sync. --}}
<style>
    .activity-table-sign {
        width: 100%;
        text-align: center;
        padding: 0 28px;
        page-break-inside: avoid;
        font-family: DejaVu Sans, sans-serif;
        color: #111;
    }
    .activity-table-sign .sign-spacer {
        height: {{ round($spacerPt, 1) }}pt;
        line-height: {{ round($spacerPt, 1) }}pt;
        font-size: 1pt;
    }
    .activity-table-sign .sign-slot,
    .activity-table-sign .sign-session,
    .activity-table-sign .sign-game {
        font-weight: bold;
        line-height: {{ $lineHeight }};
        margin: 0 0 {{ $gapPt }}pt;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    .activity-table-sign .sign-slot {
        font-size: {{ $slotSizePt }}pt;
    }
    .activity-table-sign .sign-session {
        font-size: {{ $sessionSizePt }}pt;
    }
    .activity-table-sign .sign-game {
        font-size: {{ $gameSizePt }}pt;
    }
    .activity-table-sign .sign-brand {
        margin: 0;
    }
</style>
<div class="activity-table-sign">
    <div class="sign-spacer">&nbsp;</div>
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
        @include('pdf.partials.brand-logo', ['path' => $brandLogos['sign'], 'height' => 40])
    </p>
</div>

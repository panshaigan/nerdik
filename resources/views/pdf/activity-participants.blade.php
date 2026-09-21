<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $roster['documentTitle'] }}</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            line-height: 1.4;
        }
        table.doc-header {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px;
        }
        table.doc-header td {
            vertical-align: middle;
            padding: 0;
        }
        .doc-header-title {
            width: auto;
        }
        .doc-header-logo {
            width: 48px;
            text-align: right;
            white-space: nowrap;
        }
        h1 {
            font-size: 18px;
            margin: 0;
        }
        .header-schedule {
            font-weight: normal;
            color: #333;
        }
        h2.activity-title {
            font-size: 15px;
            margin: 0 0 8px;
        }
        .meta {
            margin: 0 0 4px;
        }
        table.participants {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        table.participants th,
        table.participants td {
            border-bottom: 1px solid #ccc;
            padding: 6px 4px;
            text-align: left;
            vertical-align: middle;
        }
        table.participants th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #444;
        }
        .col-check {
            width: 56px;
            text-align: right !important;
        }
        .checkbox {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1.5px solid #222;
            vertical-align: middle;
        }
        .absent-note {
            color: #666;
            font-size: 11px;
        }
        .empty {
            color: #666;
            font-style: italic;
        }
        .table-sign-page {
            page-break-before: always;
        }
        table.activity-table-sign {
            width: 100%;
            border-collapse: collapse;
            font-family: DejaVu Sans, sans-serif;
            color: #111;
        }
        table.activity-table-sign td {
            vertical-align: middle;
            text-align: center;
            padding: 48px 36px;
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
            margin: 0 0 24px;
        }
        .activity-table-sign .sign-brand {
            margin: 0;
        }
    </style>
</head>
<body>
    @php
        $scheduleBits = array_values(array_filter(
            [
                ($roster['where'] ?? null) !== null && $roster['where'] !== '' ? $roster['where'] : null,
                ($roster['when'] ?? null) !== null && $roster['when'] !== '' ? $roster['when'] : null,
            ],
            fn (?string $bit): bool => $bit !== null,
        ));
    @endphp
    @include('pdf.partials.document-header', [
        'title' => $roster['documentTitle'],
        'scheduleBits' => $scheduleBits,
        'brandLogos' => $brandLogos,
    ])
    @include('pdf.partials.activity-roster', ['roster' => $roster, 'showHeading' => false])
    <div class="table-sign-page">
        @include('pdf.partials.activity-table-sign', [
            'roster' => $roster,
            'brandLogos' => $brandLogos,
        ])
    </div>
</body>
</html>

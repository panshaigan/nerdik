<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $roster['documentTitle'] }}</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            line-height: 1.35;
        }
        table.doc-header {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 14px;
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
            font-size: 17px;
            margin: 0;
        }
        .header-schedule {
            font-weight: normal;
            color: #333;
            font-size: 12px;
        }
        h2.activity-title {
            font-size: 13px;
            margin: 0 0 6px;
        }
        h2.activity-title .header-schedule {
            font-size: 11px;
        }
        .meta {
            margin: 0 0 3px;
        }
        table.activities-grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.activities-grid > tbody > tr > td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 6px 14px 0;
        }
        table.activities-grid > tbody > tr > td:last-child {
            padding-right: 0;
        }
        .activity-roster {
            page-break-inside: avoid;
            border: 1px solid #ddd;
            padding: 8px;
        }
        table.participants {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.participants th,
        table.participants td {
            border-bottom: 1px solid #ccc;
            padding: 4px 3px;
            text-align: left;
            vertical-align: middle;
        }
        table.participants th {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #444;
        }
        .col-check {
            width: 48px;
            text-align: right !important;
        }
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.5px solid #222;
            vertical-align: middle;
        }
        .absent-note {
            color: #666;
            font-size: 10px;
        }
        .empty {
            color: #666;
            font-style: italic;
        }
        .no-activities {
            color: #666;
            font-style: italic;
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

    @if ($roster['activities'] === [])
        <p class="no-activities">{{ __('ui.pdf.roster.no_activities') }}</p>
    @else
        <table class="activities-grid">
            @foreach (array_chunk($roster['activities'], 3) as $row)
                <tr>
                    @foreach ($row as $activityRoster)
                        <td>
                            @include('pdf.partials.activity-roster', [
                                'roster' => $activityRoster,
                                'showHeading' => true,
                                'brandLogos' => $brandLogos,
                            ])
                        </td>
                    @endforeach
                    @for ($i = count($row); $i < 3; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>

        @foreach ($roster['activities'] as $activityRoster)
            @include('pdf.partials.activity-table-sign', [
                'roster' => $activityRoster,
                'brandLogos' => $brandLogos,
            ])
        @endforeach
    @endif
</body>
</html>

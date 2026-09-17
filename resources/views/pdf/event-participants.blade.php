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
        h1 {
            font-size: 17px;
            margin: 0 0 6px;
        }
        .event-meta {
            margin: 0 0 14px;
            color: #333;
            font-size: 12px;
        }
        .event-meta p {
            margin: 0 0 3px;
        }
        h2.activity-title {
            font-size: 13px;
            margin: 0 0 6px;
        }
        .meta {
            margin: 0 0 3px;
        }
        table.activities-grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.activities-grid > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px 16px 0;
        }
        table.activities-grid > tbody > tr > td:last-child {
            padding-right: 0;
            padding-left: 8px;
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
    <h1>{{ $roster['documentTitle'] }}</h1>

    @if (($roster['where'] ?? null) || ($roster['when'] ?? null))
        <div class="event-meta">
            @if (($roster['where'] ?? null) !== null && $roster['where'] !== '')
                <p><strong>{{ __('ui.pdf.roster.where') }}:</strong> {{ $roster['where'] }}</p>
            @endif
            @if (($roster['when'] ?? null) !== null && $roster['when'] !== '')
                <p><strong>{{ __('ui.pdf.roster.when') }}:</strong> {{ $roster['when'] }}</p>
            @endif
        </div>
    @endif

    @if ($roster['activities'] === [])
        <p class="no-activities">{{ __('ui.pdf.roster.no_activities') }}</p>
    @else
        <table class="activities-grid">
            @foreach (array_chunk($roster['activities'], 2) as $pair)
                <tr>
                    @foreach ($pair as $activityRoster)
                        <td>
                            @include('pdf.partials.activity-roster', ['roster' => $activityRoster, 'showHeading' => true])
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>

        @foreach ($roster['activities'] as $activityRoster)
            @include('pdf.partials.activity-table-sign', ['roster' => $activityRoster])
        @endforeach
    @endif
</body>
</html>

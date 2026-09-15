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
        h1 {
            font-size: 18px;
            margin: 0 0 16px;
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
            text-align: right;
        }
        .col-check .checkbox {
            margin-left: auto;
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
    </style>
</head>
<body>
    <h1>{{ $roster['documentTitle'] }}</h1>
    @include('pdf.partials.activity-roster', ['roster' => $roster, 'showHeading' => false])
</body>
</html>

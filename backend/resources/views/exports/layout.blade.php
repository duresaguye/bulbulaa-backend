<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['title'] }} — {{ $document['subtitle'] }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,600|noto-sans-ethiopic:400,600&display=swap">
    <style>
        @page { size: A4; margin: 16mm 14mm 18mm; }
        :root { --ink: #17211b; --muted: #56635a; --rule: #c3ccc4; --soft: #f3f5f2; --accent: #2c6340; --gold: #c5953b; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: #e9ece8; font: 10.5pt/1.5 'IBM Plex Sans', 'Noto Sans Ethiopic', system-ui, sans-serif; font-variant-numeric: tabular-nums; }
        .sheet { max-width: 210mm; margin: 24px auto; background: #fff; padding: 16mm 14mm 18mm; box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
        .toolbar { position: sticky; top: 0; z-index: 1; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; padding: .75rem; background: var(--ink); }
        .toolbar a, .toolbar button { border: 1px solid #ffffff55; border-radius: 6px; background: transparent; color: #fff; padding: .5rem 1rem; font: inherit; font-size: 10pt; text-decoration: none; cursor: pointer; }
        .toolbar button { border-color: var(--accent); background: var(--accent); font-weight: 600; }
        .letterhead { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid var(--accent); padding-bottom: 10px; }
        .letterhead svg { width: 34px; height: 34px; color: var(--accent); flex: none; }
        .letterhead__town { font-size: 13pt; font-weight: 600; line-height: 1.2; }
        .letterhead__unit { color: var(--muted); font-size: 9pt; }
        .letterhead__printed { margin-inline-start: auto; color: var(--muted); font-size: 8.5pt; text-align: end; }
        h1 { margin: 14px 0 0; font-size: 17pt; line-height: 1.25; }
        .subtitle { margin: 2px 0 14px; color: var(--muted); font-size: 11pt; }
        h2 { margin: 18px 0 6px; font-size: 11.5pt; break-after: avoid; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(42mm, 1fr)); gap: 6px 16px; margin: 0; border: 1px solid var(--rule); border-radius: 6px; padding: 8px 10px; }
        .meta dt { color: var(--muted); font-size: 8.5pt; }
        .meta dd { margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        th, td { border-bottom: 1px solid var(--rule); padding: 5px 6px; text-align: start; vertical-align: top; }
        thead th { border-bottom: 1.5px solid var(--ink); color: var(--muted); font-size: 8.5pt; font-weight: 600; }
        tr { break-inside: avoid; }
        .num { text-align: end; white-space: nowrap; }
        .unit, .note { display: block; color: var(--muted); font-size: 8.5pt; }
        .nested td { background: var(--soft); font-size: 8.5pt; }
        .narrative dt { margin-top: 8px; color: var(--muted); font-size: 8.5pt; font-weight: 600; }
        .narrative dd { margin: 0; white-space: pre-line; }
        .empty { color: var(--muted); }
        .signatures { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 28px; break-inside: avoid; }
        .signatures div { border-top: 1px solid var(--ink); padding-top: 4px; color: var(--muted); font-size: 8.5pt; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; max-width: none; padding: 0; box-shadow: none; }
        }
        @media (max-width: 40rem) {
            .sheet { margin: 0; padding: 16px; }
            .letterhead { flex-wrap: wrap; }
            .letterhead__printed { margin-inline-start: 0; text-align: start; }
        }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="{{ __('Actions') }}">
        <button type="button" onclick="window.print()">{{ __('Print or save as PDF') }}</button>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/admin') }}">{{ __('Back') }}</a>
    </nav>
    <main class="sheet">
        <header class="letterhead">
            <svg viewBox="0 0 32 32" aria-hidden="true">
                <rect x="1" y="1" width="30" height="30" rx="7" fill="currentColor" />
                <path d="M6 24.5c3.2-1.6 6.5-2.4 10-2.4s6.8.8 10 2.4" fill="none" stroke="#c5953b" stroke-width="1.8" stroke-linecap="round" />
                <path d="M10 20.5v-6l6-4 6 4v6M14 20.5v-3.5h4v3.5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div>
                <div class="letterhead__town">{{ __(':town administration', ['town' => config('app.town_name')]) }}</div>
                <div class="letterhead__unit">{{ __('Planning and reporting') }}</div>
            </div>
            <div class="letterhead__printed">{{ \App\Support\ReportDocument::printedOn() }}</div>
        </header>

        <h1>{{ $document['title'] }}</h1>
        <p class="subtitle">{{ $document['subtitle'] }}</p>

        @yield('content')
    </main>
    @if (request()->boolean('print'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>

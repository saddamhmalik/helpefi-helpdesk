<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p.meta { color: #64748b; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fafc; }
        h2 { font-size: 13px; margin: 20px 0 8px; }
        h2:first-of-type { margin-top: 0; }
    </style>
</head>
<body>
    <h1>{{ $reportName }}</h1>
    <p class="meta">{{ $typeLabel }} · Generated {{ $generatedAt }}</p>
    @if(!empty($truncated))
        <p class="meta">Showing the first 500 rows. Export CSV for the full dataset.</p>
    @endif

    @if(($format ?? 'table') === 'sections')
        @foreach($sections as $section)
            <h2>{{ $section['title'] }}</h2>
            <table>
                <thead>
                    <tr>
                        @foreach($section['headers'] as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['headers']) }}">No data for this report.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @else
        <table>
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) }}">No data for this report.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>

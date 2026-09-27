<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan scan {{ $scan->employee->name }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #18181b; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #d4d4d8; padding-bottom: 3px; }
        .muted { color: #71717a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        th { background: #f4f4f5; font-weight: bold; }
        .summary td:first-child { width: 45%; color: #52525b; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-size: 9px; }
        .badge.gap { background: #e4e4e7; color: #3f3f46; }
        .evidence { page-break-inside: avoid; margin-bottom: 10px; }
        .evidence img { width: 240px; border: 1px solid #d4d4d8; }
        .note { margin-top: 16px; font-size: 9px; color: #71717a; }
    </style>
</head>
<body>
    <h1>Laporan Scan Screenshot</h1>
    <div class="muted">{{ $scan->employee->name }} · dibuat {{ now()->format('d-m-Y H:i') }}</div>

    <h2>Ringkasan</h2>
    <table class="summary">
        @foreach ($summary as [$label, $value])
            <tr><td>{{ $label }}</td><td><strong>{{ $value }}</strong></td></tr>
        @endforeach
    </table>

    <h2>Temuan ({{ count($findings) }})</h2>
    @if (count($findings) === 0)
        <p class="muted">Tidak ada temuan.</p>
    @else
        <table>
            <thead>
                <tr><th style="width: 42px">Waktu</th><th style="width: 110px">Jenis</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
                @foreach ($findings as $item)
                    <tr>
                        <td>{{ $item['row'][0] }}</td>
                        <td><span class="badge {{ $item['finding']->type === \App\Enums\FindingType::TimeGap ? 'gap' : '' }}">{{ $item['row'][1] }}</span></td>
                        <td>{{ $item['row'][2] }}<br><span class="muted">{{ $item['row'][3] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($evidence) > 0)
        <h2>Bukti visual ({{ count($evidence) }} temuan pertama)</h2>
        @foreach ($evidence as $item)
            <div class="evidence">
                <strong>{{ $item['time'] }} · {{ $item['label'] }}</strong> — {{ $item['description'] }}
                <table>
                    <tr>
                        <td style="border: none; width: 50%">
                            @if ($item['related'])
                                <div class="muted">Pembanding</div>
                                <img src="{{ $item['related'] }}" alt="">
                            @endif
                        </td>
                        <td style="border: none">
                            <div class="muted">Screenshot</div>
                            <img src="{{ $item['current'] }}" alt="">
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endif

    <p class="note">
        Hasil analisis otomatis ini adalah indikasi, bukan kesimpulan. Periksa setiap temuan bersama buktinya
        sebelum mengambil keputusan.
    </p>
</body>
</html>

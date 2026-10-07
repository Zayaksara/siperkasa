<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kerusakan Aset</title>
    <style>
        /* dompdf has no CSS variables: hex values mirror UINSSC tokens (primary #216320, deep #1a4f19, foreground #07110b, muted #565c59, border #d5dad7). */
        @page { margin: 28px 30px 40px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #07110b; }
        table { width: 100%; border-collapse: collapse; }
        .kop { margin-bottom: 8px; border-bottom: 2px solid #216320; }
        .kop td { padding-bottom: 6px; vertical-align: middle; }
        .univ { font-size: 10px; font-weight: bold; color: #1a4f19; }
        .title { margin-top: 2px; font-size: 15px; font-weight: bold; color: #216320; }
        .meta { margin: 6px 0; color: #565c59; }
        .summary { table-layout: fixed; }
        .summary td { padding: 4px 8px; border: 1px solid #d5dad7; text-align: center; }
        .summary .n { font-size: 13px; font-weight: bold; }
        .data { margin-top: 10px; table-layout: fixed; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data th { padding: 5px; background: #216320; color: #ffffff; text-align: left; }
        .data td { padding: 4px 5px; border-bottom: 1px solid #e6e8e7; vertical-align: top; word-wrap: break-word; }
        .empty { padding: 16px; text-align: center; color: #565c59; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            @if ($logo)
                <td style="width: 56px"><img src="{{ $logo }}" alt="Logo UINSSC" width="40"></td>
            @endif
            <td>
                <div class="univ">UNIVERSITAS ISLAM NEGERI SIBER SYEKH NURJATI CIREBON</div>
                <div class="title">Laporan Kerusakan Aset - SiPerkasa</div>
            </td>
        </tr>
    </table>

    <div class="meta">Dicetak: {{ $printedAt }} | Filter: {{ $filterText }}</div>

    @if (count($summary))
        <table class="summary">
            <tr>
                @foreach ($summary as $label => $total)
                    <td><div class="n">{{ $total }}</div>{{ $label }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data">
        <colgroup>
            <col style="width: 11%"><col style="width: 9%"><col style="width: 27%"><col style="width: 19%">
            <col style="width: 10%"><col style="width: 13%"><col style="width: 11%">
        </colgroup>
        <thead>
            <tr>
                <th>Tiket</th><th>Tanggal</th><th>Judul</th><th>Lokasi</th><th>Status</th><th>PIC</th><th>SLA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['ticket'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['title'] }}</td>
                    <td>{{ $row['location'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['pic'] }}</td>
                    <td>{{ $row['sla'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

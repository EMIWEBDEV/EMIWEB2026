<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rekap Sampel - {{ $namaAnalisa }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
        }
        .header-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
            border: none;
        }
        .logo {
            width: 100%;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            line-height: 50px;
            margin-bottom: 2px;
        }
        .department-name {
            font-size: 14px;
            font-weight: bold;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-top: 14px;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
        }
        .main-table th, .main-table td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
        }
        .main-table thead th {
            background-color: #D9EAD3;
            font-weight: bold;
            white-space: nowrap;
        }
        .main-table tbody td {
            text-align: center;
        }
        .main-table tbody td.numeric,
        .main-table tfoot td.numeric {
            text-align: center;
        }
        .rata-rata-label {
            font-weight: bold;
            text-align: right;
        }
        .rata-rata-value {
            font-weight: bold;
            text-align: center;
        }
        footer {
            position: fixed;
            bottom: -30px;
            left: 0px;
            right: 0px;
            height: 40px;
            text-align: center;
            font-size: 8px;
            color: #555;
        }
        .pagenum:before {
            content: counter(page);
        }
    </style>
</head>
<body>
    <header>
        <table class="header-table">
            <tr>
                <td style="width: 10%;">
                    <img src="{{ $logoPath }}" alt="Logo" class="logo">
                </td>
                <td style="width: 80%;" class="header-text">
                    <div class="company-name">PT. EVO MANUFACTURING INDONESIA</div>
                    <div class="department-name">FTN DEPARTMENT - SUMATERA SELATAN</div>
                </td>
                <td style="width: 10%;"></td>
            </tr>
        </table>
        <div class="report-title">{{ $namaAnalisa }}</div>
        <hr style="margin-bottom: 20px;">
    </header>

    <footer>
        Dokumen ini dibuat secara otomatis oleh EMI LAB | Halaman <span class="pagenum"></span>
    </footer>

    <main>
        <table class="main-table">
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ strtoupper($heading) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($collection as $item)
                    <tr>
                        @foreach ($item as $key => $value)
                            <td class="{{ is_numeric($value) && !in_array($key, ['no', 'no_batch', 'nama_sampel', 'no_po', 'no_split_po', 'tanggal']) ? 'numeric' : '' }}">
                                {{ $value }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headings) }}">Data tidak ditemukan.</td>
                    </tr>
                @endforelse
            </tbody>

            @if ($apakahPerhitungan && !empty($rataRata))
                <tfoot>
                    <tr>
                        @php
                            $colspan = count($headings) - $rumusCount;
                        @endphp
                        <td colspan="{{ $colspan }}" class="rata-rata-label">Rata-Rata Total</td>
                        @foreach ($rataRata as $avg)
                            <td class="rata-rata-value numeric">{{ $avg }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </main>
</body>
</html>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rekap Sampel</title>
    <style>
        /* Pengaturan Dasar Dokumen */
        body {
            font-family: "Helvetica", "Arial", sans-serif;
            font-size: 9px;
        }

        /* Pengaturan Halaman untuk PDF */
        @page {
            /* Ruang di atas (110px) untuk header & bawah (50px) untuk footer. */
            margin: 110px 30px 30px 30px;
        }

        /* HEADER: Berulang di setiap halaman */
        header {
            position: fixed;
            top: -95px;
            left: 0px;
            right: 0px;
            height: 90px;
        }

        /* FOOTER: Berulang di setiap halaman */
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

        /* Kode CSS untuk menampilkan nomor halaman */
        .pagenum:before {
            content: counter(page);
        }

        /* == STYLING LAINNYA == */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .logo { width: 100px; height: auto; }
        .company-name { font-size: 18px; font-weight: bold; }
        .department-name { font-size: 14px; font-weight: bold; }
        .report-title { font-size: 16px; font-weight: bold; text-align: center; margin-top: 5px; }
        .report-block {
            border: 1px solid #333;
            padding: 15px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .info-table { margin-bottom: 15px; }
        .info-table td { padding: 4px 8px; border: none; vertical-align: top; text-align: left; }
        .info-table td:first-child { font-weight: bold; width: 25%; }
        .main-table { margin-bottom: 0; }
        .main-table th, .main-table td {
            border: 1px solid #333;
            padding: 5px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .main-table thead th {
            background-color: #f5e7b0;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <header>
        <table style="width:100%; border:none;">
            <tr>
                <td style="width: 15%; text-align: left;">
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                </td>
                <td style="width: 70%; text-align:center; vertical-align: top;">
                    <div class="company-name">PT. EVO MANUFACTURING INDONESIA</div>
                    <div class="department-name">FTN DEPARTMENT - SUMATERA SELATAN</div>
                    <div class="report-title">PARTICLE SIZE REPORT</div>
                </td>
                <td style="width: 15%;"></td>
            </tr>
        </table>
        <hr style="margin-top: 5px;">
    </header>

    <footer>
        Dokumen ini dibuat secara otomatis oleh EMI LAB | Halaman <span class="pagenum"></span>
    </footer>

    <main>
        @foreach ($reports as $report)
        <div class="report-block">
            <table class="info-table">
                <tr>
                    <td>Nama Sampel</td>
                    <td>: {{ $report['info']['nama_sampel'] }}</td>
                </tr>
                <tr>
                    <td>Tanggal Produksi</td>
                    <td>: {{ $report['info']['tanggal_produksi_1'] }}</td>
                </tr>
                @if(!empty($report['info']['tanggal_produksi_2']))
                <tr>
                    <td>Tanggal Produksi 2</td>
                    <td>: {{ $report['info']['tanggal_produksi_2'] }}</td>
                </tr>
                @endif
                <tr>
                    <td>Produk</td>
                    <td>: {{ $report['info']['produk'] }}</td>
                </tr>
            </table>

            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Particle Size</th>
                        <th style="width: 60%;">Percentage (%)</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach ($report['values'] as $particleSize => $percentage)
                        <tr>
                            <td>{{ $particleSize }}</td>
                            <td>{{ $percentage }}</td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
        @endforeach
    </main>
</body>
</html>
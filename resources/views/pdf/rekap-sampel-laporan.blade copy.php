<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            /* Ukuran font disesuaikan agar lebih pas */
            font-size: 9px; 
        }

        header {
            margin-bottom: 20px;
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
            font-size: 16px;
            font-weight: bold;
            line-height: 50px;
            margin-bottom: 2px;
        }

        .department-name {
            font-size: 12px;
            font-weight: bold;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-top: 14px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            /* Menambahkan table-layout: fixed untuk kontrol lebar kolom yang lebih baik */
            table-layout: fixed; 
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px; /* Sedikit mengurangi padding */
            text-align: center;
            vertical-align: middle;
            /* Mengizinkan teks untuk turun baris jika terlalu panjang */
            word-wrap: break-word; 
        }

        thead th {
            background-color: #f5e7b0;
            font-weight: bold;
        }

        .palette-header th {
            background-color: #ddbe59;
            color: #000;
            font-weight: bold;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
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

        @media print {
            body {
                margin: 0;
            }
            .table-responsive {
                overflow: visible;
            }
        }
    </style>
</head>
<body>
    <header>
        <table class="header-table">
            <tr>
                <td style="width: 10%;">
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                </td>
                <td style="width: 80%;" class="header-text">
                    <div class="company-name">PT. EVO MANUFACTURING INDONESIA</div>
                    <div class="department-name">FTN DEPARTMENT - SUMATERA SELATAN</div>
                </td>
                <td style="width: 10%;"></td>
            </tr>
        </table>
        <div class="report-title">LABORATORY ANALYSIS REPORT</div>
        <hr>
    </header>

     <footer>
        Dokumen ini dibuat secara otomatis oleh EMI LAB | Halaman <span class="pagenum"></span>
    </footer>
    
    <main>
        <div class="table-responsive">
            @php
                // Blok PHP untuk kalkulasi rata-rata
                $jumlahKolom = count($headers); // Gunakan $headers
                $totalNilai = array_fill(0, $jumlahKolom, 0);
                $jumlahDataValid = array_fill(0, $jumlahKolom, 0);

                foreach ($collection as $row) {
                    foreach ($row['Analisa'] as $index => $analisa) {
                        if (is_numeric($analisa['nilai'])) {
                            $totalNilai[$index] += (float) $analisa['nilai'];
                            $jumlahDataValid[$index]++;
                        }
                    }
                }

                $rataRata = [];
                for ($i = 0; $i < $jumlahKolom; $i++) {
                    // MODIFIKASI 2: Ambil kode analisa dari variabel $headers yang baru
                    $kodeAnalisa = $headers[$i]['kode'];

                    // Jika kodenya 'MBLG-STR' atau jika tidak ada data numerik, tampilkan '-'
                    if ($kodeAnalisa === 'MBLG-STR' || $jumlahDataValid[$i] === 0) {
                        $rataRata[$i] = '-';
                    } else {
                        // Jika tidak, hitung dan format rata-ratanya
                        $rataRata[$i] = number_format($totalNilai[$i] / $jumlahDataValid[$i], 2, '.', '');
                    }
                }
            @endphp

            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 4%;">No</th>
                        <th rowspan="2" style="width: 30%;">Nama Sampel</th>
                        <th rowspan="2" style="width: 12%;">Tanggal Produksi</th>
                        <th rowspan="2" style="width: 12%;">Tanggal Uji Sampel</th>
                        <th colspan="{{ count($headers) }}">Result</th> </tr>
                    <tr class="palette-header">
                        {{-- MODIFIKASI 1: Akses properti 'nama' untuk menampilkan header --}}
                        @foreach ($headers as $header)
                            <th>{{ $header['nama'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($collection as $row)
                    <tr>
                        <td>{{ $row['No'] }}</td>
                        <td style="text-align: left;">{{ $row['Nama_Sampel'] }}</td>
                        <td>{{ $row['Tanggal_Produksi'] }}</td>
                        <td>{{ $row['Tanggal'] }}</td>
                        @foreach ($row['Analisa'] as $analisa)
                            <td>
                                {{-- Logika ini untuk mengubah 0.00 menjadi '-' khusus MBLG-STR jika diperlukan --}}
                                {{-- Namun, controller sudah mengirim 'Lolos Uji'/'Tidak Lolos', jadi ini tidak akan tereksekusi --}}
                                {{-- Jika nilai MBLG-STR bisa jadi 0.00, baris ini berguna --}}
                                @if (is_numeric($analisa['nilai']) && (float)$analisa['nilai'] == 0 && in_array($analisa['nama'], array_column(array_filter($headers, fn($h) => $h['kode'] === 'MBLG-STR'), 'nama')))
                                    -
                                @else
                                    {{ $analisa['nilai'] }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                    <tr style="font-weight: bold;">
                        <td colspan="4" style="text-align: center;">Rata-rata</td>
                        @foreach ($rataRata as $rata)
                            <td>{{ $rata }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>
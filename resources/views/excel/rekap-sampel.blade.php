<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Sampel</title>
    <style>
        
        /* CSS untuk meniru tampilan Excel */
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
            margin-bottom: 24px;
        }

    /* Responsive table wrapper */
    .table-responsive {
      width: 100%;
      overflow-x: auto;
    }

    /* Tabel utama */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    th,
    td {
      border: 1px solid #333;
      padding: 6px 8px;
      text-align: center;
      vertical-align: middle;
      white-space: nowrap;
    }

    thead th {
      background-color: #f5e7b0;
      font-weight: bold;
    }

    .palette-header th {
        background-color: #ddbe59; /* Hijau muda ala Excel */
        color: #000;               /* Warna teks tetap hitam */
        font-weight: bold;
    }


    tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    /* Warna baris header utama */
    .table-primary {
      background-color: #cfe2f3;
    }

    /* Border gaya Excel */
    th,
    td {
      border: 1px solid #000000;
    }

    /* Kolom khusus */
    .numeric {
      text-align: right;
    }

    .rata-rata-label {
      text-align: right;
      font-weight: bold;
    }

    .rata-rata-value {
      text-align: center;
      font-weight: bold;
    }
        .rata-rata-label {
            font-weight: bold;
            text-align: right;
        }
        .rata-rata-value {
            font-weight: bold;
            text-align: center;
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
  <table>
    <tr>
        <td colspan="{{ 3 + count($headers) }}" style="text-align: center; font-weight: bold; font-size: 18px;">
            PT. EVO MANUFACTURING INDONESIA
        </td>
    </tr>
    <tr>
        <td colspan="{{ 3 + count($headers) }}" style="text-align: center; font-weight: bold; font-size: 14px;">
            FTN DEPARTMENT - SUMATERA SELATAN
        </td>
    </tr>
    <tr>
        <td colspan="{{ 3 + count($headers) }}" style="text-align: center; font-weight: bold; font-size: 16px;">
            LABORATORY ANALYSIS REPORT
        </td>
    </tr>
</table>

<br>

@php
    $jumlahKolom = count($collection[0]['Analisa']);
    $totalNilai = array_fill(0, $jumlahKolom, 0);
    $jumlahBaris = count($collection);
    foreach ($collection as $row) {
        foreach ($row['Analisa'] as $index => $analisa) {
            $totalNilai[$index] += (float) $analisa['nilai'];
        }
    }

    $rataRata = array_map(function ($total) use ($jumlahBaris) {
        return number_format($total / $jumlahBaris, 2);
    }, $totalNilai);
@endphp

<table>
    <thead>
        <tr>
            <th rowspan="2">No</th>
            <th rowspan="2">Nama Sampel</th>
            <th rowspan="2">Tanggal</th>
            <th colspan="{{ count($headers) }}">Result</th>
        </tr>
        <tr>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($collection as $row)
            <tr>
                <td>{{ $row['No'] }}</td>
                <td>{{ $row['Nama_Sampel'] }}</td>
                <td>{{ $row['Tanggal'] }}</td>
                @foreach ($row['Analisa'] as $analisa)
                    <td>{{ $analisa['nilai'] }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="text-align: center;">Rata-rata</td>
            @foreach ($rataRata as $rata)
                <td>{{ $rata }}</td>
            @endforeach
        </tr>
    </tbody>
</table>


</body>
</html>

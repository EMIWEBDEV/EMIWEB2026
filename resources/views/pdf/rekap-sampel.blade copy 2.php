<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Sampel</title>
  <style>
    /* Tampilan umum */
    * {
      box-sizing: border-box;
    }
    body {
      font-family: 'Helvetica', 'Arial', sans-serif;
      font-size: 11px;
      margin: 20px;
      color: #000;
      background-color: #fff;
    }

    h4 {
      text-align: center;
      font-size: 16px;
      margin-bottom: 20px;
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
      background-color: #d9ead3;
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
  <h4>Laporan Hasil Analisa Sampel</h4>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th rowspan="2">No</th>
          <th rowspan="2">Nama Sampel</th>
          <th rowspan="2">Tanggal</th>
          <th colspan="{{ count($collection[0]['Analisa']) }}">Result</th>
        </tr>
        <tr>
         @foreach ($headers as $namaKolom)
                    <th>{{ $namaKolom }}</th>
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
      </tbody>
    </table>
  </div>
</body>
</html>

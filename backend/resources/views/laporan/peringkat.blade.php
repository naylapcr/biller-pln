<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Peringkat biller {{ $periode->kode }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #0F1B2A; }
        .kepala { width: 100%; border-bottom: 2px solid #0A5C8F; padding-bottom: 8px; margin-bottom: 12px; }
        .kepala td { vertical-align: middle; }
        .judul { font-size: 15px; font-weight: bold; margin: 0; }
        .sub { color: #546474; margin: 2px 0 0; }
        .ringkas { width: 100%; margin-bottom: 12px; border-collapse: separate; border-spacing: 6px 0; }
        .ringkas td { border: 1px solid #DCE3EA; padding: 6px 8px; width: 25%; }
        .ringkas .label { color: #546474; font-size: 8px; }
        .ringkas .angka { font-size: 13px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #F3F6F9; color: #546474; font-weight: normal; text-align: left; padding: 5px 5px; border-bottom: 1px solid #DCE3EA; }
        table.data td { padding: 4px 5px; border-bottom: 1px solid #EDF1F5; }
        .kanan { text-align: right; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .kritis { color: #A61F17; }
        .evaluasi { color: #6B5000; }
        .kaki { margin-top: 12px; color: #546474; font-size: 8px; }
    </style>
</head>
<body>
    <table class="kepala">
        <tr>
            <td style="width: 90px;"><img src="{{ public_path('images/logo-pln.png') }}" style="height: 30px;" alt="PLN"></td>
            <td>
                <p class="judul">Laporan peringkat biller · {{ $periode->nama() }}</p>
                <p class="sub">
                    ULP Kota Barat · BLTH {{ $periode->kode }} · {{ $periode->terbuka() ? 'Skor sementara (periode masih terbuka)' : 'Skor final' }}
                </p>
            </td>
        </tr>
    </table>

    @php($fmt = fn ($n, $d = 0) => $n === null ? '–' : number_format((float) $n, $d, ',', '.'))
    @php($pct = fn ($n) => $n === null ? '–' : number_format((float) $n * 100, 2, ',', '.').'%')
    <table class="ringkas">
        <tr>
            <td><div class="label">Realisasi terbaca pascabayar</div><div class="angka">{{ $ringkasan['realisasi']['persen'] !== null ? $fmt($ringkasan['realisasi']['persen'], 2).'%' : '–' }}</div></td>
            <td><div class="label">Rata-rata skor {{ $ringkasan['rata_skor']['jumlah_biller'] }} biller</div><div class="angka">{{ $fmt($ringkasan['rata_skor']['nilai'], 1) }}</div></td>
            <td><div class="label">Kecepatan · eviden</div><div class="angka">{{ $fmt($ringkasan['kecepatan_eviden']['plg_per_jam'], 1) }} plg/jam · {{ $fmt($ringkasan['kecepatan_eviden']['eviden_persen'], 1) }}%</div></td>
            <td><div class="label">Rasio kesalahan BU · AP2T</div><div class="angka">{{ $pct($ringkasan['mutu']['rasio_bu']) }} · {{ $pct($ringkasan['mutu']['rasio_ap2t']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>Login ACMT</th>
                <th class="kanan">Pascabayar</th>
                <th class="kanan">Prabayar</th>
                <th class="kanan">Bonus J</th>
                <th class="kanan">Rasio BU</th>
                <th class="kanan">Rasio AP2T</th>
                <th class="kanan">Skor akhir</th>
                <th>Kategori</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $b)
                <tr>
                    <td class="mono">{{ $b['peringkat'] }}</td>
                    <td>{{ $b['nama'] }}</td>
                    <td class="mono">{{ $b['login_acmt'] }}</td>
                    <td class="kanan mono">{{ $fmt($b['pascabayar'], 1) }}</td>
                    <td class="kanan mono">{{ $fmt($b['prabayar'], 1) }}</td>
                    <td class="kanan mono">{{ $b['bonus_hari_j'] ? '+'.$fmt($b['bonus_hari_j'], 1) : '0' }}</td>
                    <td class="kanan mono">{{ $pct($b['rasio_bu']) }}</td>
                    <td class="kanan mono">{{ $pct($b['rasio_ap2t']) }}</td>
                    <td class="kanan mono"><strong>{{ $fmt($b['final_score'], 1) }}</strong></td>
                    <td class="{{ $b['kategori'] === 'Kritis' ? 'kritis' : ($b['kategori'] === 'Perlu Evaluasi' ? 'evaluasi' : '') }}">{{ $b['kategori'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="kaki">
        Skor akhir = rata-rata nilai modul (skala 0–100) + bonus Hari J, sehingga bisa melewati 100.
        Nilai pascabayar = (40% realisasi + 30% eviden + 30% kecepatan) × (1 − rasio Baca Ulang) × (1 − rasio AP2T).
        Dicetak {{ $dicetak->locale('id')->translatedFormat('j F Y H:i') }} WIB oleh {{ $oleh }}.
    </p>
</body>
</html>

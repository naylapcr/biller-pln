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
        .evaluasi { color: #A61F17; }
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
    <table class="ringkas">
        <tr>
            <td><div class="label">Total WO</div><div class="angka">{{ $fmt($ringkasan['total_wo']) }}</div></td>
            <td><div class="label">Realisasi</div><div class="angka">{{ $ringkasan['realisasi_persen'] !== null ? $fmt($ringkasan['realisasi_persen'], 2).'%' : '–' }}</div></td>
            <td><div class="label">Rasio kecepatan (terbaca per jam kalender)</div><div class="angka">{{ $fmt($ringkasan['rasio_kecepatan'], 2) }}</div></td>
            <td><div class="label">Total denda (poin)</div><div class="angka">{{ $fmt($ringkasan['total_denda'], 1) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Biller</th>
                <th>Login / kode</th>
                <th class="kanan">Pasca terbaca</th>
                <th class="kanan">Kasus BU</th>
                <th class="kanan">Poin pasca</th>
                <th class="kanan">Poin prabayar</th>
                <th class="kanan">Bonus J</th>
                <th class="kanan">Denda</th>
                <th class="kanan">Skor akhir</th>
                <th>Kategori</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $b)
                <tr>
                    <td class="mono">{{ $b['peringkat'] }}</td>
                    <td>{{ $b['nama'] }}</td>
                    <td class="mono">{{ $b['login_acmt'] }} · {{ $b['kode_petugas'] }}</td>
                    <td class="kanan mono">{{ $fmt($b['pascabayar']['terbaca'] ?? null) }}</td>
                    <td class="kanan mono">{{ $fmt($b['pascabayar']['kasus_bu'] ?? null) }}</td>
                    <td class="kanan mono">{{ $fmt($b['pascabayar']['poin'] ?? null, 1) }}</td>
                    <td class="kanan mono">{{ $fmt($b['prabayar']['poin'] ?? null, 1) }}</td>
                    <td class="kanan mono">{{ $fmt($b['poin_hari_j'], 1) }}</td>
                    <td class="kanan mono">{{ $fmt($b['total_denda'], 1) }}</td>
                    <td class="kanan mono"><strong>{{ $fmt($b['final_score'], 1) }}</strong></td>
                    <td class="{{ $b['kategori'] === 'perlu_evaluasi' ? 'evaluasi' : '' }}">{{ $b['kategori'] === 'perlu_evaluasi' ? 'Perlu evaluasi' : 'Stabil' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="kaki">
        Skor akhir = Σ poin modul (StanValid × w_stan + Eviden × w_eviden) + bonus Hari J − denda AP2T − denda Baca Ulang.
        Dicetak {{ $dicetak->locale('id')->translatedFormat('j F Y H:i') }} WIB oleh {{ $oleh }}.
    </p>
</body>
</html>

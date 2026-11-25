<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?php echo e($invoice->nomor); ?></title>
    <style>
        @page { margin: 20px 24px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #0F1B2A; }
        .kepala { width: 100%; border-bottom: 2px solid #0A5C8F; padding-bottom: 8px; }
        .kepala td { vertical-align: middle; }
        .judul { font-size: 14px; font-weight: bold; margin: 0; }
        .sub { color: #546474; margin: 2px 0 0; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .blok { width: 100%; margin-top: 12px; border-collapse: collapse; }
        .blok td { padding: 3px 0; vertical-align: top; }
        .label { color: #546474; width: 38%; }
        .seksi { margin-top: 14px; font-size: 10.5px; font-weight: bold; border-bottom: 1px solid #DCE3EA; padding-bottom: 3px; }
        .rincian { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .rincian td { padding: 4px 0; border-bottom: 1px solid #EDF1F5; }
        .kanan { text-align: right; }
        .total td { border-top: 2px solid #0F1B2A; border-bottom: none; font-size: 12px; font-weight: bold; padding-top: 6px; }
        .stand { width: 100%; margin-top: 6px; border-collapse: separate; border-spacing: 6px 0; }
        .stand td { border: 1px solid #DCE3EA; border-radius: 6px; padding: 6px 8px; width: 33%; }
        .stand .angka { font-size: 13px; font-weight: bold; }
        .status { display: inline-block; border-radius: 8px; padding: 2px 8px; font-size: 9px; }
        .lunas { background: #E5F4EC; color: #13703F; }
        .menunggak { background: #FCEAEA; color: #A61F17; }
        .belum { background: #FFF6CC; color: #6B5000; }
        .kaki { margin-top: 16px; color: #546474; font-size: 8.5px; }
    </style>
</head>
<body>
    <?php
        $rp = fn ($n) => 'Rp'.number_format((float) $n, 0, ',', '.');
        $angka = fn ($n, $d = 1) => number_format((float) $n, $d, ',', '.');
        $c = $invoice->pencatatanMeter;
        $p = $invoice->pelanggan;
        $statusKelas = match ($invoice->status->value) { 'lunas' => 'lunas', 'menunggak' => 'menunggak', default => 'belum' };
    ?>

    <table class="kepala">
        <tr>
            <td style="width: 80px;"><img src="<?php echo e(public_path('images/logo-pln.png')); ?>" style="height: 26px;" alt="PLN"></td>
            <td>
                <p class="judul">Rekening listrik · <?php echo e($invoice->periode->nama()); ?></p>
                <p class="sub"><?php echo e($unit ?? 'PLN'); ?> · <span class="mono"><?php echo e($invoice->nomor); ?></span></p>
            </td>
            <td class="kanan"><span class="status <?php echo e($statusKelas); ?>"><?php echo e($invoice->status->label()); ?></span></td>
        </tr>
    </table>

    <table class="blok">
        <tr><td class="label">Nama pelanggan</td><td><strong><?php echo e($p->nama); ?></strong></td></tr>
        <tr><td class="label">ID pelanggan</td><td class="mono"><?php echo e($p->idpel); ?></td></tr>
        <tr><td class="label">Alamat</td><td><?php echo e($p->alamat); ?>, <?php echo e($p->kelurahan); ?></td></tr>
        <tr><td class="label">Tarif / daya</td><td><?php echo e($p->golongan_tarif); ?> / <?php echo e($angka($p->daya_va, 0)); ?> VA</td></tr>
        <tr><td class="label">Nomor meter</td><td class="mono"><?php echo e($p->nomor_meter); ?></td></tr>
    </table>

    <div class="seksi">Pemakaian</div>
    <table class="stand">
        <tr>
            <td><div class="sub">Stand lalu</div><div class="angka mono"><?php echo e($c?->stand_lalu !== null ? $angka($c->stand_lalu) : '–'); ?></div></td>
            <td><div class="sub">Stand kini</div><div class="angka mono"><?php echo e($c?->stand_kini !== null ? $angka($c->stand_kini) : '–'); ?></div></td>
            <td><div class="sub">Pemakaian</div><div class="angka mono"><?php echo e($angka($invoice->pemakaian_kwh)); ?> kWh</div></td>
        </tr>
    </table>

    <div class="seksi">Rincian tagihan</div>
    <table class="rincian">
        <tr><td>Biaya pemakaian (<?php echo e($angka($invoice->pemakaian_kwh)); ?> kWh × <?php echo e('Rp'.$angka($invoice->harga_per_kwh, 2)); ?>)</td><td class="kanan mono"><?php echo e($rp($invoice->biayaPemakaian())); ?></td></tr>
        <tr><td>Biaya beban</td><td class="kanan mono"><?php echo e($rp($invoice->biaya_beban)); ?></td></tr>
        <tr><td>Pajak Penerangan Jalan (<?php echo e($angka($ppj, 1)); ?>%)</td><td class="kanan mono"><?php echo e($rp($invoice->pajak)); ?></td></tr>
        <tr class="total"><td>Total tagihan</td><td class="kanan mono"><?php echo e($rp($invoice->total)); ?></td></tr>
    </table>

    <table class="blok">
        <tr><td class="label">Jatuh tempo</td><td><strong><?php echo e($invoice->jatuh_tempo->locale('id')->translatedFormat('j F Y')); ?></strong></td></tr>
        <?php if($invoice->dibayar_at): ?>
            <tr><td class="label">Dibayar</td><td><?php echo e($invoice->dibayar_at->locale('id')->translatedFormat('j F Y, H.i')); ?> WIB</td></tr>
        <?php endif; ?>
    </table>

    <p class="kaki">
        Rekening ini diterbitkan dari catatan meter yang sudah diverifikasi. Pembayaran setelah jatuh tempo
        tercatat sebagai tunggakan. Simpan bukti pembayaran Anda.
    </p>
</body>
</html>
<?php /**PATH D:\project-biller\Project-pln-biller\resources\views/invoice/rekening.blade.php ENDPATH**/ ?>
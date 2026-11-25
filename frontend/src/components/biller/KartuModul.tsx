import Kartu, { BarisNilai } from '@/components/ui/Kartu';
import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatPercent, formatTanggal } from '@/lib/format';
import type { SkorBiller } from '@/types';

const rasio = (r: number) => formatPercent(r * 100, 2);

/** Modul Pascabayar (Hari A–G & J) di 01.1. */
export function KartuModulPasca({ skor }: { skor: SkorBiller }) {
    const p = skor.nilai_per_modul.pascabayar;
    const a = skor.aturan;

    return (
        <Kartu judul="Modul Pascabayar (Hari A–G & J)" subjudul="Rekap WBM Tunggal & Ganda Industri" aksi={<StatusChip tone="info" label={p ? `Nilai: ${formatNumber(p.nilai, 1)}` : 'Belum ada data'} />}>
            {p ? (
                <>
                    <BarisNilai
                        label={`Realisasi (Bobot ${formatNumber(a?.w_realisasi ?? 40)}%)`}
                        keterangan={`${formatNumber(p.terbaca)} / ${formatNumber(p.jml_plg)} pelanggan terbaca`}
                        nilai={formatNumber(p.realisasi, 1)}
                    />
                    <BarisNilai
                        label={`Eviden Foto (Bobot ${formatNumber(a?.w_eviden ?? 30)}%)`}
                        keterangan={`${formatNumber(p.foto)} / ${formatNumber(p.terbaca)} foto berhasil valid`}
                        nilai={formatNumber(p.eviden, 1)}
                        warna="text-success"
                    />
                    <BarisNilai
                        label={`Kecepatan (Bobot ${formatNumber(a?.w_kecepatan ?? 30)}%)`}
                        keterangan={`${formatNumber(p.kecepatan.plg_per_jam, 1)} plg/jam (standar ${formatNumber(a?.sla_plg_per_jam ?? 33.3, 1)} · ACMT)`}
                        nilai={formatNumber(p.kecepatan.nilai, 1)}
                        warna="text-sky-val"
                    />
                    <BarisNilai
                        label="Potongan Rasio Kesalahan"
                        keterangan={`Baca Ulang ${rasio(p.baca_ulang.rasio)} · AP2T ${rasio(p.ap2t.rasio)}`}
                        nilai={p.nilai_dasar > 0 && p.nilai < p.nilai_dasar ? `−${formatPercent((1 - p.nilai / p.nilai_dasar) * 100, 2)}` : '0%'}
                        warna="text-pln-red"
                    />
                </>
            ) : (
                <p className="text-xs text-muted-ink">Tidak ada data pascabayar di periode ini, jadi modul ini tidak ikut membagi skor.</p>
            )}
        </Kartu>
    );
}

/** Modul Prabayar & Bonus Hari J di 01.1, termasuk modul yang menunggu berkas. */
export function KartuModulPrabayar({ skor }: { skor: SkorBiller }) {
    const p = skor.nilai_per_modul.prabayar;
    const j = skor.bonus_hari_j;
    const jadwal = `${formatTanggal(skor.jadwal_prabayar[0], 'd MMM')} – ${formatTanggal(skor.jadwal_prabayar[1], 'd MMM')}`;

    return (
        <Kartu
            judul="Modul Prabayar & Bonus Hari J"
            subjudul={`Monitoring Fisik Meter Prabayar · Jadwal ${jadwal}`}
            aksi={<StatusChip tone="success" label={p ? `Prabayar: ${formatNumber(p.nilai, 1)}` : 'Tanpa data prabayar'} />}
        >
            {p && (
                <>
                    <BarisNilai
                        label="Dibaca Normal Sesuai Jadwal"
                        keterangan={`${formatNumber(p.normal_terjadwal)} / ${formatNumber(p.pemeriksaan)} meter (kode normal · ${jadwal})`}
                        nilai={formatNumber(p.realisasi, 1)}
                        warna="text-success"
                    />
                    <BarisNilai
                        label="Koordinat GPS Valid"
                        keterangan={`${formatNumber(p.koordinat_valid)} / ${formatNumber(p.pemeriksaan)} titik koordinat terverifikasi`}
                        nilai={formatNumber(p.eviden, 1)}
                        warna="text-sky-val"
                    />
                </>
            )}
            <BarisNilai
                label="Bonus Hari J (Di Luar Rata-rata)"
                keterangan={j.jml_plg > 0 ? `${formatPercent(j.persen_tuntas, 0)} tuntas (${formatNumber(j.terbaca)} / ${formatNumber(j.jml_plg)} pelanggan)` : 'Tidak ada pelanggan Hari J'}
                nilai={`+${formatNumber(j.nilai, 1)}`}
                warna="text-violet"
            />
            <BarisNilai label="Modul Menunggu Berkas" keterangan="Invoice, PDIL, dan AP2T belum aktif periode ini" nilai="—" warna="text-faint" />
        </Kartu>
    );
}

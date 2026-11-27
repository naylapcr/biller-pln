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
                        label="Potongan Rasio Kesalahan (Pengali, Bukan Bobot)"
                        keterangan={`Nilai × (1 − rasio Baca Ulang) × (1 − rasio AP2T) · Baca Ulang ${rasio(p.baca_ulang.rasio)} · AP2T ${rasio(p.ap2t.rasio)}`}
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

/** Kehadiran di 01.1: rekap absensi bulan kerja periode (bulan sebelum BLTH) dan nilainya. */
export function KartuKehadiran({ skor }: { skor: SkorBiller }) {
    const h = skor.kehadiran;
    const a = skor.aturan;
    const tanpaFoto = h ? h.izin + h.sakit - h.berfoto : 0;
    const kurang = h ? Math.min(h.hadir, (h.terlambat ?? 0) + (h.pulang_cepat ?? 0) + (h.tanpa_pulang ?? 0)) : 0;

    return (
        <Kartu
            judul={`Kehadiran (Bobot ${formatNumber(a?.bobot_kehadiran ?? 10)}%)`}
            subjudul={h ? `Bulan kerja ${formatTanggal(`${h.bulan}-01`, 'MMMM yyyy')} · dari Absensi Saya` : 'Bulan kerja = bulan sebelum BLTH'}
            aksi={<StatusChip tone={h ? 'success' : 'neutral'} label={h ? `Nilai: ${formatNumber(h.nilai, 1)}` : 'Belum ada absensi'} />}
        >
            {h ? (
                <>
                    <BarisNilai label="Hadir Tepat Waktu" keterangan={`${formatNumber(h.hadir - kurang)} hari × 100 · masuk ≤ ${(a?.jam_masuk ?? '07:30').replace(':', '.')}, pulang ≥ ${(a?.jam_pulang ?? '16:30').replace(':', '.')}`} nilai={formatNumber(h.hadir - kurang)} warna="text-success" />
                    <BarisNilai
                        label="Terlambat / Pulang Cepat / Tanpa Absen Pulang"
                        keterangan={`${formatNumber(h.terlambat ?? 0)} terlambat · ${formatNumber(h.pulang_cepat ?? 0)} pulang cepat · ${formatNumber(h.tanpa_pulang ?? 0)} tanpa pulang · × ${formatNumber(a?.nilai_terlambat ?? 75)}`}
                        nilai={formatNumber(kurang)}
                        warna="text-pln-yellow-ink"
                    />
                    <BarisNilai label="Izin / Sakit Berlampiran" keterangan={`${formatNumber(h.berfoto)} hari × 100`} nilai={formatNumber(h.berfoto)} warna="text-sky-val" />
                    <BarisNilai
                        label="Izin / Sakit tanpa Lampiran"
                        keterangan={`${formatNumber(tanpaFoto)} hari × ${formatNumber(a?.nilai_izin_tanpa_foto ?? 50)}`}
                        nilai={formatNumber(tanpaFoto)}
                        warna="text-pln-yellow-ink"
                    />
                    <BarisNilai label="Alpa" keterangan={`${formatNumber(h.alpa)} hari × 0`} nilai={formatNumber(h.alpa)} warna="text-pln-red" />
                </>
            ) : (
                <p className="text-xs text-muted-ink">Belum ada catatan absensi di bulan kerja periode ini, jadi kehadiran tidak ikut menghitung skor.</p>
            )}
        </Kartu>
    );
}

/** Modul Prabayar & Bonus Hari J di 01.1. Modul yang menunggu berkas tampil di Statistik Skor. */
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
                label={`Bonus Hari J (Maks. +${formatNumber(skor.aturan?.bobot_bonus_j ?? 2, 1)})`}
                keterangan={
                    j.jml_plg > 0
                        ? `${formatNumber(j.terbaca)} terbaca Hari J dari ${formatNumber(j.jml_plg_total)} total pelanggan (${formatPercent(j.persen_tuntas, 0)} Hari J tuntas)`
                        : 'Tidak ada pelanggan Hari J'
                }
                nilai={`+${formatNumber(j.nilai, 2)}`}
                warna="text-violet"
            />
        </Kartu>
    );
}

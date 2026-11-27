import type { ReactNode } from 'react';

import { Donut } from '@/components/ui/DonutChart';
import Kartu from '@/components/ui/Kartu';
import RingNilai from '@/components/ui/RingNilai';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SkorBiller } from '@/types';

function Kelompok({ judul, nilai, catatan, children }: { judul: string; nilai: string; catatan?: string; children: ReactNode }) {
    return (
        <section className="flex min-w-fit flex-1 flex-col gap-2.5 rounded-xl border border-line bg-canvas px-3.5 py-3">
            <header className="flex items-baseline justify-between gap-3">
                <h3 className="text-[11px] font-bold tracking-wide text-slate-ink uppercase">{judul}</h3>
                <span className="font-mono text-xs font-bold text-ink">{nilai}</span>
            </header>
            <div className="flex flex-wrap justify-around gap-3">{children}</div>
            {catatan && <p className="text-[10px] leading-[13px] text-muted-ink">{catatan}</p>}
        </section>
    );
}

function Legenda({ warna, label, nilai }: { warna: string; label: string; nilai: string }) {
    return (
        <p className="flex items-center justify-between gap-3 text-[11px] text-slate-ink">
            <span className="flex items-center gap-1.5">
                <span className={cn('size-2 rounded-full', warna)} aria-hidden />
                {label}
            </span>
            <span className="font-mono font-bold">{nilai}</span>
        </p>
    );
}

/**
 * Statistik Skor & Komponen Modul (01.1): komposisi skor akhir (modul, kehadiran, bonus Hari J) dan
 * cincin setiap komponen: pascabayar, prabayar, kehadiran, dan Hari J.
 */
export default function StatistikSkor({ skor }: { skor: SkorBiller }) {
    const pasca = skor.nilai_per_modul.pascabayar;
    const pra = skor.nilai_per_modul.prabayar;
    const hadir = skor.kehadiran;
    const j = skor.bonus_hari_j;
    const rata = skor.rata_rata_modul ?? 0;
    const bobotHadir = hadir ? (skor.aturan?.bobot_kehadiran ?? 10) / 100 : 0;
    const bagianModul = (1 - bobotHadir) * rata;
    const bagianHadir = hadir ? bobotHadir * hadir.nilai : 0;
    const bebasSalah = pasca ? (1 - pasca.baca_ulang.rasio) * (1 - pasca.ap2t.rasio) * 100 : null;
    const persenHadir = hadir && hadir.hari > 0 ? (hadir.hadir / hadir.hari) * 100 : null;

    return (
        <Kartu judul="Statistik Skor & Komponen Modul" subjudul="Seluruh item pekerjaan: pascabayar, prabayar, kehadiran, Hari J, invoice, dan pemutusan">
            <div className="flex flex-wrap items-stretch gap-5">
                <div className="flex w-full flex-col items-center justify-center gap-3 sm:w-[200px]">
                    <Donut
                        data={[
                            { nilai: bagianModul, warna: 'stroke-pln-blue' },
                            { nilai: bagianHadir, warna: 'stroke-success-line' },
                            { nilai: j.nilai, warna: 'stroke-sky-line' },
                        ]}
                        total={100}
                        ukuran={138}
                        tebal={13}
                        tengah={formatNumber(skor.final_score, 1)}
                        keterangan="Skor Akhir"
                    />
                    <div className="grid w-full gap-1">
                        <Legenda warna="bg-pln-blue" label={`Modul × ${formatNumber((1 - bobotHadir) * 100)}%`} nilai={formatNumber(bagianModul, 1)} />
                        <Legenda warna="bg-success-line" label={`Kehadiran × ${formatNumber(bobotHadir * 100)}%`} nilai={hadir ? formatNumber(bagianHadir, 1) : '–'} />
                        <Legenda warna="bg-sky-line" label="Bonus Hari J" nilai={`+${formatNumber(j.nilai, 1)}`} />
                    </div>
                </div>

                <div className="flex min-w-0 flex-1 basis-[420px] flex-wrap gap-3">
                    <Kelompok
                        judul="Pascabayar"
                        nilai={pasca ? formatNumber(pasca.nilai, 1) : '–'}
                        catatan={pasca ? 'Bebas kesalahan = (1 − rasio Baca Ulang) × (1 − rasio AP2T)' : 'Tidak ada data pascabayar periode ini.'}
                    >
                        <RingNilai label="Realisasi" nilai={pasca?.realisasi ?? null} warna="stroke-[#2563eb]" />
                        <RingNilai label="Eviden Foto" nilai={pasca?.eviden ?? null} warna="stroke-success" />
                        <RingNilai label="Kecepatan" nilai={pasca?.kecepatan.nilai ?? null} warna="stroke-sky-val" />
                        <RingNilai label="Bebas Kesalahan" nilai={bebasSalah} warna="stroke-pln-red" />
                    </Kelompok>
                    <Kelompok judul="Prabayar" nilai={pra ? formatNumber(pra.nilai, 1) : '–'} catatan={pra ? undefined : 'Tidak ada data prabayar periode ini.'}>
                        <RingNilai label="Normal Terjadwal" nilai={pra?.realisasi ?? null} warna="stroke-success" />
                        <RingNilai label="Koordinat Valid" nilai={pra?.eviden ?? null} warna="stroke-sky-val" />
                    </Kelompok>
                    <Kelompok
                        judul="Kehadiran"
                        nilai={hadir ? formatNumber(hadir.nilai, 1) : '–'}
                        catatan={hadir ? `${formatNumber(hadir.hadir)} hadir dari ${formatNumber(hadir.hari)} hari kerja tercatat` : 'Belum ada catatan absensi di bulan kerja.'}
                    >
                        <RingNilai label="Nilai Kehadiran" nilai={hadir?.nilai ?? null} warna="stroke-success-line" />
                        <RingNilai label="Hari Hadir" nilai={persenHadir} warna="stroke-[#2563eb]" />
                    </Kelompok>
                    <Kelompok
                        judul="Hari J"
                        nilai={`+${formatNumber(j.nilai, 1)}`}
                        catatan={j.jml_plg > 0 ? `${formatNumber(j.terbaca)} / ${formatNumber(j.jml_plg)} pelanggan Hari J dari ${formatNumber(j.jml_plg_total)} total` : 'Tidak ada pelanggan Hari J.'}
                    >
                        <RingNilai label="Tuntas" nilai={j.persen_tuntas} warna="stroke-violet" />
                    </Kelompok>
                    <Kelompok judul="Menunggu Berkas" nilai="–" catatan="Invoice & PDIL belum ikut skor periode ini.">
                        <RingNilai label="Invoice" nilai={null} warna="stroke-sky-val" />
                        <RingNilai label="Pemutusan (PDIL)" nilai={null} warna="stroke-pln-red" />
                    </Kelompok>
                </div>
            </div>
        </Kartu>
    );
}

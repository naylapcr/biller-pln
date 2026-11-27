import { Info } from 'lucide-react';

import PanelTransparansi from '@/components/biller/PanelTransparansi';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { formatNumber, formatPercent, formatTanggalWaktu } from '@/lib/format';
import { metaKategori } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { SkorBiller } from '@/types';

const WARNA_KATEGORI: Record<string, string> = { Baik: 'text-[#4ade80]', Cukup: 'text-sky-side', 'Perlu Pendampingan': 'text-pln-yellow' };

function KotakKaca({ label, nilai, keterangan, warna }: { label: string; nilai: string; keterangan: string; warna: string }) {
    return (
        <div className="flex min-w-28 flex-1 flex-col gap-1 rounded-[14px] border border-white/16 bg-white/10 px-[18px] py-3.5 sm:flex-none">
            <p className="text-[10px] leading-[13px] font-bold text-sky-side">{label}</p>
            <p className={cn('text-[22px] leading-7 font-extrabold whitespace-nowrap', warna)}>{nilai}</p>
            <p className="text-[11px] leading-[14px] text-line">{keterangan}</p>
        </div>
    );
}

/** Label peringkat: Top 5 Teratas, Top 5 Terbawah, atau posisi dari jumlah biller. */
export function labelPeringkat(peringkat: number | null, jumlah: number) {
    if (peringkat === null) return `dari ${jumlah} Biller`;
    if (peringkat <= 5) return 'Top 5 Teratas';
    if (jumlah > 10 && peringkat > jumlah - 5) return 'Top 5 Terbawah';
    return `dari ${jumlah} Biller`;
}

/**
 * Kartu skor akhir Biller (01.1): skor 0–100 dengan formula, peringkat, kategori, kehadiran, dan
 * bonus Hari J. Skor akhir dibatasi maksimal 100.
 */
export default function KartuSkorSaya({ skor }: { skor: SkorBiller }) {
    const a = skor.aturan;
    const ambang =
        skor.kategori === 'Baik' ? `Ambang ≥ ${formatNumber(a?.ambang_grade_a ?? 85, 1)}` : skor.kategori === 'Perlu Pendampingan' ? `Ambang < ${formatNumber(a?.ambang_kritis ?? 65, 1)}` : `Baik ≥ ${formatNumber(a?.ambang_grade_a ?? 85, 1)}`;
    const bonus = skor.bonus_hari_j;
    const hadir = skor.kehadiran;
    const bobot = hadir ? (a?.bobot_kehadiran ?? 10) : 0;

    return (
        <section className="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 rounded-[18px] bg-[linear-gradient(141deg,#0f172a_0%,#1e3a8a_54%,#0369a1_83%)] px-5 py-[22px] shadow-[0_8px_22px_rgb(30_58_138/0.14)] sm:px-[26px]">
            <div className="min-w-0">
                <span className="inline-flex rounded-full bg-[#0c4a6e] px-2.5 py-1 text-[11px] leading-[14px] font-semibold text-sky-side">
                    SKOR AKHIR PERIODE {skor.periode}
                    {skor.is_final ? ' · FINAL' : ''}
                </span>
                <p className="mt-1.5 flex flex-wrap items-end gap-x-3.5">
                    <span className="text-[46px] leading-[58px] font-extrabold text-white">{formatNumber(skor.final_score, 1)}</span>
                    <span className="pb-2 text-sm text-[#93c5fd]">/ 100</span>
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                    {skor.dihitung_at && <p className="text-[11px] text-[#93c5fd]">Dihitung {formatTanggalWaktu(skor.dihitung_at)}</p>}
                    <Dialog>
                        <DialogTrigger className="inline-flex items-center gap-1 rounded-full border border-white/25 px-2.5 py-1 text-[11px] font-semibold text-sky-side hover:bg-white/10">
                            <Info className="size-3.5" aria-hidden />
                            Cara hitung skor
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-[620px]">
                            <DialogHeader>
                                <DialogTitle>Cara Hitung Skor Periode {skor.periode}</DialogTitle>
                                <DialogDescription>
                                    Skor akhir = {formatNumber(100 - bobot)}% rata-rata modul{hadir ? ` + ${formatNumber(bobot)}% kehadiran` : ''} + bonus Hari J, paling tinggi 100.
                                </DialogDescription>
                            </DialogHeader>
                            <PanelTransparansi skor={skor} />
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            <div className="flex w-full flex-wrap gap-3.5 sm:w-auto">
                <KotakKaca
                    label="PERINGKAT"
                    nilai={`#${skor.peringkat ?? '–'} / ${skor.jumlah_biller}`}
                    keterangan={labelPeringkat(skor.peringkat, skor.jumlah_biller)}
                    warna="text-pln-yellow"
                />
                <KotakKaca
                    label="KEHADIRAN"
                    nilai={hadir ? formatPercent(hadir.nilai, 1) : '–'}
                    keterangan={hadir ? `${hadir.hadir}/${hadir.hari} hari hadir` : 'Belum ada absensi'}
                    warna="text-[#6ee7b7]"
                />
                <KotakKaca label="KATEGORI" nilai={metaKategori(skor.kategori).label} keterangan={ambang} warna={WARNA_KATEGORI[skor.kategori ?? ''] ?? 'text-white'} />
                <KotakKaca
                    label="BONUS HARI J"
                    nilai={`+${formatNumber(bonus.nilai, 1)}`}
                    keterangan={bonus.jml_plg > 0 ? `${formatPercent(bonus.persen_tuntas, 0)} tuntas · maks +${formatNumber(a?.bobot_bonus_j ?? 2, 1)}` : 'Tidak ada Hari J'}
                    warna="text-sky-line"
                />
            </div>
        </section>
    );
}

import { formatNumber, formatPercent, formatTanggalWaktu } from '@/lib/format';
import { metaKategori } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { SkorBiller } from '@/types';

const WARNA_KATEGORI: Record<string, string> = { 'Grade A': 'text-[#4ade80]', 'Perlu Evaluasi': 'text-pln-yellow', Kritis: 'text-[#fca5a5]' };

function KotakKaca({ label, nilai, keterangan, warna }: { label: string; nilai: string; keterangan: string; warna: string }) {
    return (
        <div className="flex min-w-28 flex-1 flex-col gap-1 rounded-[14px] border border-white/16 bg-white/10 px-[18px] py-3.5 sm:flex-none">
            <p className="text-[10px] leading-[13px] font-bold text-sky-side">{label}</p>
            <p className={cn('text-[22px] leading-7 font-extrabold whitespace-nowrap', warna)}>{nilai}</p>
            <p className="text-[11px] leading-[14px] text-line">{keterangan}</p>
        </div>
    );
}

/**
 * Kartu skor akhir Biller (01.1): skor "/ 100 + Bonus Hari J" dengan formula, peringkat, kategori,
 * dan bonus Hari J. Skor bisa melewati 100 karena bonus ditambahkan di luar rata-rata.
 */
export default function KartuSkorSaya({ skor }: { skor: SkorBiller }) {
    const a = skor.aturan;
    const ambang =
        skor.kategori === 'Grade A' ? `Ambang ≥ ${formatNumber(a?.ambang_grade_a ?? 85, 1)}` : skor.kategori === 'Kritis' ? `Ambang < ${formatNumber(a?.ambang_kritis ?? 65, 1)}` : `Grade A ≥ ${formatNumber(a?.ambang_grade_a ?? 85, 1)}`;
    const bonus = skor.bonus_hari_j;

    return (
        <section className="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 rounded-[18px] bg-[linear-gradient(141deg,#0f172a_0%,#1e3a8a_54%,#0369a1_83%)] px-5 py-[22px] shadow-[0_8px_22px_rgb(30_58_138/0.14)] sm:px-[26px]">
            <div className="min-w-0">
                <span className="inline-flex rounded-full bg-[#0c4a6e] px-2.5 py-1 text-[11px] leading-[14px] font-semibold text-sky-side">
                    SKOR AKHIR PERIODE {skor.periode}
                    {skor.is_final ? ' · FINAL' : ''}
                </span>
                <p className="mt-1.5 flex flex-wrap items-end gap-x-3.5">
                    <span className="text-[46px] leading-[58px] font-extrabold text-white">{formatNumber(skor.final_score, 1)}</span>
                    <span className="pb-2 text-sm text-[#93c5fd]">/ 100 + Bonus Hari J</span>
                </p>
                <p className="text-xs text-line">
                    Formula: Rata-rata modul dikerjakan ({formatNumber(skor.rata_rata_modul, 1)}) + Bonus Hari J (+{formatNumber(bonus.nilai, 1)}) = {formatNumber(skor.final_score, 1)}
                </p>
                {skor.dihitung_at && <p className="mt-1 text-[11px] text-[#93c5fd]">Dihitung {formatTanggalWaktu(skor.dihitung_at)}</p>}
            </div>
            <div className="flex w-full flex-wrap gap-3.5 sm:w-auto">
                <KotakKaca
                    label="PERINGKAT"
                    nilai={`#${skor.peringkat ?? '–'} / ${skor.jumlah_biller}`}
                    keterangan={skor.peringkat !== null && skor.peringkat <= 5 ? 'Top 5 Biller' : `dari ${skor.jumlah_biller} Biller`}
                    warna="text-pln-yellow"
                />
                <KotakKaca label="KATEGORI" nilai={metaKategori(skor.kategori).label} keterangan={ambang} warna={WARNA_KATEGORI[skor.kategori ?? ''] ?? 'text-white'} />
                <KotakKaca
                    label="BONUS HARI J"
                    nilai={`+${formatNumber(bonus.nilai, 1)}`}
                    keterangan={bonus.jml_plg > 0 ? `${formatPercent(bonus.persen_tuntas, 0)} Tuntas Hari J` : 'Tidak ada Hari J'}
                    warna="text-sky-line"
                />
            </div>
        </section>
    );
}

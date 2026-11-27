import { formatNumber } from '@/lib/format';
import type { SkorBiller } from '@/types';

const b = (persen: number | undefined, bawaan: number) => formatNumber((persen ?? bawaan) / 100, 2).replace(/0$/, '');

/** Rincian cara hitung skor dengan angka biller sendiri dan bobot periode (dibuka lewat tombol "Cara hitung skor"). */
export default function PanelTransparansi({ skor }: { skor: SkorBiller }) {
    const a = skor.aturan;
    const pasca = skor.nilai_per_modul.pascabayar;
    const pra = skor.nilai_per_modul.prabayar;
    const modul = [pasca && formatNumber(pasca.nilai, 1), pra && formatNumber(pra.nilai, 1)].filter(Boolean).join('; ');
    const hadirBobot = a?.bobot_kehadiran ?? 10;

    return (
        <div className="rounded-10 border border-sky-side bg-sky-row px-3 py-2.5">
            <p className="text-[10px] leading-[13px] font-bold text-sky-ink">TRANSPARANSI CARA HITUNG SKOR</p>
            <div className="mt-1 grid gap-0.5 font-mono text-[10px] leading-[13px] text-ink-soft">
                {pasca && (
                    <p>
                        Pasca: ({b(a?.w_realisasi, 40)}×{formatNumber(pasca.realisasi, 1)} + {b(a?.w_eviden, 30)}×{formatNumber(pasca.eviden, 1)} + {b(a?.w_kecepatan, 30)}×
                        {formatNumber(pasca.kecepatan.nilai, 1)}) × (1 − {formatNumber(pasca.baca_ulang.rasio, 4)})
                        {pasca.ap2t.rasio > 0 && ` × (1 − ${formatNumber(pasca.ap2t.rasio, 4)})`} = {formatNumber(pasca.nilai, 1)}
                    </p>
                )}
                {pra && (
                    <p>
                        Prabayar: ({formatNumber(pra.realisasi, 1)} + {formatNumber(pra.eviden, 1)}) ÷ 2 = {formatNumber(pra.nilai, 1)}
                    </p>
                )}
                {skor.kehadiran && (
                    <p>
                        Kehadiran: rata-rata {skor.kehadiran.hari} hari kerja (tepat waktu 100 · terlambat/pulang cepat {formatNumber(a?.nilai_terlambat ?? 75)} · izin/sakit berlampiran 100 ·
                        alpa 0) = {formatNumber(skor.kehadiran.nilai, 1)}
                    </p>
                )}
                {skor.bonus_hari_j.jml_plg > 0 && (
                    <p>
                        Bonus J: {formatNumber(skor.bonus_hari_j.terbaca)} ÷ {formatNumber(skor.bonus_hari_j.jml_plg_total)} pelanggan × 100 (maks. {formatNumber(a?.bobot_bonus_j ?? 2, 1)}) ={' '}
                        {formatNumber(skor.bonus_hari_j.nilai, 2)}
                    </p>
                )}
                <p className="font-bold text-pln-blue">
                    Akhir: {skor.kehadiran ? `${b(100 - hadirBobot, 90)}×Rata(${modul}) + ${b(hadirBobot, 10)}×${formatNumber(skor.kehadiran.nilai, 1)}` : `Rata(${modul})`} + Bonus J (+
                    {formatNumber(skor.bonus_hari_j.nilai, 1)}) = {formatNumber(skor.final_score, 1)} (maks. 100)
                </p>
            </div>
        </div>
    );
}

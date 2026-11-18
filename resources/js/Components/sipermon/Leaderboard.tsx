import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BarisLeaderboard } from '@/types';

/** Daftar Top/Bottom N. Rincian denda hanya tampil bila datanya dikirim (bukan untuk Biller). */
export default function Leaderboard({
    judul,
    baris,
    nada,
    sorotBillerId,
    onPilih,
}: {
    judul: string;
    baris: BarisLeaderboard[];
    nada: 'atas' | 'bawah';
    sorotBillerId?: number;
    onPilih?: (billerId: number) => void;
}) {
    return (
        <section className="rounded-14 border border-border p-5">
            <h2 className="text-section-title text-ink">{judul}</h2>
            {baris.length === 0 ? (
                <p className="mt-3 text-sm text-muted-ink">Belum ada skor di periode ini.</p>
            ) : (
                <ol className="mt-3 divide-y divide-line-row">
                    {baris.map((b) => {
                        const isi = (
                            <>
                                <span
                                    className={cn(
                                        'inline-flex size-8 shrink-0 items-center justify-center rounded-full font-mono text-[13px] font-semibold',
                                        nada === 'atas' ? 'bg-success-tint text-success-ink' : 'bg-error-tint text-error-ink',
                                    )}
                                >
                                    {b.peringkat}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium text-ink">{b.nama}</span>
                                    <span className="block text-xs text-muted-ink">
                                        {b.kode_petugas}
                                        {b.total_denda !== undefined && ` · denda ${formatNumber(b.total_denda, 1)}`}
                                    </span>
                                </span>
                                <span className="font-mono text-sm font-semibold">{formatNumber(b.final_score, 1)}</span>
                            </>
                        );

                        return (
                            <li key={b.biller_id} className={cn(sorotBillerId === b.biller_id && 'bg-pln-yellow-light/60')}>
                                {onPilih ? (
                                    <button type="button" onClick={() => onPilih(b.biller_id)} className="flex w-full items-center gap-3 py-2.5 text-left hover:bg-table-head/60">
                                        {isi}
                                    </button>
                                ) : (
                                    <div className="flex items-center gap-3 py-2.5">{isi}</div>
                                )}
                            </li>
                        );
                    })}
                </ol>
            )}
        </section>
    );
}

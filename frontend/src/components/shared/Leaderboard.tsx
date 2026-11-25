import type { ReactNode } from 'react';

import Kartu from '@/components/ui/Kartu';
import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatPercent } from '@/lib/format';
import { metaKategori, warnaSkor } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { BarisLeaderboard } from '@/types';

const MEDALI: Record<number, string> = {
    1: 'bg-[#fef08a] text-[#a16207]',
    2: 'bg-line text-slate-ink',
    3: 'bg-[#ffedd5] text-[#c2410c]',
};

/** Lingkaran peringkat: 1–3 medali, lainnya biru; peringkat bawah merah atau kuning. */
function Lingkaran({ b, nada, besar }: { b: BarisLeaderboard; nada: 'atas' | 'bawah'; besar: boolean }) {
    const warna =
        nada === 'bawah'
            ? b.kategori === 'Perlu Evaluasi'
                ? 'bg-pln-yellow-light text-pln-yellow-ink'
                : 'bg-error-tint text-error-ink'
            : (MEDALI[b.peringkat] ?? 'bg-pln-blue-light text-pln-blue');

    return <span className={cn('inline-flex shrink-0 items-center justify-center rounded-full text-xs font-extrabold', besar ? 'size-[34px]' : 'size-7', warna)}>#{b.peringkat}</span>;
}

function rincian(b: BarisLeaderboard, nada: 'atas' | 'bawah'): string {
    if (b.final_score === undefined) return b.sektor ?? 'Sektor belum diisi';
    const nip = b.nip ?? b.login_acmt ?? '';
    if (nada === 'atas') return [nip, b.pascabayar != null && `Pasca ${formatNumber(b.pascabayar, 1)}`, `Bonus J +${formatNumber(b.bonus_hari_j, 1)}`].filter(Boolean).join(' · ');

    return [nip, b.rasio_bu != null && `BU ${formatPercent(b.rasio_bu * 100, 1)}`, b.realisasi != null && `Realisasi ${formatPercent(b.realisasi, 1)}`].filter(Boolean).join(' · ');
}

/**
 * Daftar Top/Bottom N. Untuk Biller (mode privasi) hanya nama, sektor, dan peringkat; skor dan
 * kategori tampil hanya bila datanya dikirim server (Koordinator).
 */
export default function Leaderboard({
    judul,
    subjudul,
    chip,
    baris,
    nada,
    sorotBillerId,
    onPilih,
}: {
    judul: string;
    subjudul: string;
    chip: ReactNode;
    baris: BarisLeaderboard[];
    nada: 'atas' | 'bawah';
    sorotBillerId?: number;
    onPilih?: (billerId: number) => void;
}) {
    return (
        <Kartu judul={judul} subjudul={subjudul} aksi={chip} className="self-start">
            {baris.length === 0 ? (
                <p className="text-xs text-muted-ink">Belum ada skor di periode ini.</p>
            ) : (
                <ol className="grid gap-2.5">
                    {[...baris].sort((a, b) => a.peringkat - b.peringkat).map((b) => {
                        const lengkap = b.final_score !== undefined;
                        const sendiri = sorotBillerId === b.biller_id;
                        const isi = (
                            <>
                                <Lingkaran b={b} nada={nada} besar={!lengkap} />
                                <span className="min-w-0 flex-1 text-left">
                                    <span className={cn('block truncate font-bold text-ink', lengkap ? 'text-xs' : 'text-[13px]')}>{b.nama}</span>
                                    <span className="mt-px block truncate text-[10px] text-muted-ink">{rincian(b, nada)}</span>
                                </span>
                                {lengkap ? (
                                    <>
                                        <span className={cn('text-sm font-extrabold', warnaSkor(b.kategori))}>{formatNumber(b.final_score, 1)}</span>
                                        <StatusChip {...metaKategori(b.kategori)} className="hidden sm:inline-flex" />
                                    </>
                                ) : sendiri ? (
                                    <StatusChip label="Anda" tone="success" />
                                ) : (
                                    <StatusChip label={`Peringkat #${b.peringkat}`} tone="neutral" />
                                )}
                            </>
                        );
                        const kelas = cn(
                            'flex w-full items-center gap-2.5 rounded-10 px-3 py-2.5',
                            sendiri ? 'border border-[#86efac] bg-success-row' : 'bg-canvas',
                            onPilih && 'transition-colors hover:bg-line-row',
                        );

                        return (
                            <li key={b.biller_id}>
                                {onPilih ? (
                                    <button type="button" onClick={() => onPilih(b.biller_id)} className={kelas}>
                                        {isi}
                                    </button>
                                ) : (
                                    <div className={kelas}>{isi}</div>
                                )}
                            </li>
                        );
                    })}
                </ol>
            )}
        </Kartu>
    );
}

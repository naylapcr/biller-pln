import { Link } from '@inertiajs/react';

import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import type { TingkatIssue } from '@/lib/status';
import { cn } from '@/lib/utils';

export interface Issue {
    id: number;
    sheet: string | null;
    row_number: number | null;
    kode: string;
    tingkat: TingkatIssue;
    pesan: string;
}

const KOTAK: Record<TingkatIssue, string> = {
    error: 'border-error-line bg-error-row text-error-deep',
    warning: 'border-pln-yellow-line bg-pln-yellow-row text-[#92400e]',
    info: 'border-pln-blue-soft bg-pln-blue-row text-pln-blue-dark',
};

/** Satu issue validasi: ERROR #n (merah), WARNING #n (kuning), atau INFO #n (biru). */
export default function BarisIssue({ issue, nomor }: { issue: Issue; nomor: number }) {
    const lokasi = [issue.sheet && `Sheet: ${issue.sheet}`, issue.row_number ? `Baris ${issue.row_number}` : 'Berkas'].filter(Boolean).join(' · ');

    return (
        <li className={cn('flex flex-wrap items-center gap-x-3.5 gap-y-2 rounded-xl border px-3.5 py-3', KOTAK[issue.tingkat])}>
            {issue.tingkat === 'error' ? (
                <>
                    <StatusChip label={`ERROR #${nomor}`} tone="danger" />
                    <span className="font-mono text-[10px] font-bold text-slate-mid">{lokasi}</span>
                    <span className="font-mono text-[10px] font-bold text-error-ink">{issue.kode}</span>
                    <p className="min-w-0 flex-1 basis-60 text-xs leading-[15px]">{issue.pesan}</p>
                </>
            ) : (
                <>
                    <div className="min-w-0 flex-1 basis-72">
                        <p className="text-[10px] font-bold">
                            {issue.tingkat === 'warning' ? 'WARNING' : 'INFO'} #{nomor} · <span className="font-mono">{issue.kode}</span> · {lokasi}
                        </p>
                        <p className="mt-0.5 text-xs leading-[15px]">{issue.pesan}</p>
                    </div>
                    {issue.kode === 'PETUGAS_TIDAK_DIKENAL' ? (
                        <Button variant="secondary" size="sm" asChild>
                            <Link href={route('pemetaan.index')}>Lihat Pemetaan Petugas</Link>
                        </Button>
                    ) : (
                        <StatusChip label={issue.tingkat === 'warning' ? 'Opsional · Boleh Commit' : 'Catatan'} tone={issue.tingkat === 'warning' ? 'waiting' : 'info'} />
                    )}
                </>
            )}
        </li>
    );
}

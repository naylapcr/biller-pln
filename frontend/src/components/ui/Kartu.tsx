import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface KartuProps {
    judul?: ReactNode;
    subjudul?: ReactNode;
    /** Chip atau tombol di kanan judul. */
    aksi?: ReactNode;
    children?: ReactNode;
    className?: string;
}

/** Kartu putih radius 16 dengan bayangan tipis; judul 15 px tebal dan subjudul 11 px. */
export default function Kartu({ judul, subjudul, aksi, children, className }: KartuProps) {
    return (
        <section className={cn('flex flex-col gap-3.5 rounded-2xl border border-line bg-white p-5 shadow-kartu', className)}>
            {(judul || aksi) && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0 flex-1 basis-52">
                        <h2 className="text-section-title text-ink">{judul}</h2>
                        {subjudul && <p className="mt-0.5 text-[11px] text-muted-ink">{subjudul}</p>}
                    </div>
                    {aksi && <div className="flex flex-wrap items-center gap-2">{aksi}</div>}
                </div>
            )}
            {children}
        </section>
    );
}

/** Baris kartu abu: label + keterangan di kiri, nilai tebal di kanan. */
export function BarisNilai({ label, keterangan, nilai, warna = 'text-pln-blue', className }: { label: ReactNode; keterangan?: ReactNode; nilai: ReactNode; warna?: string; className?: string }) {
    return (
        <div className={cn('flex items-center justify-between gap-3 rounded-10 bg-canvas px-3 py-2.5', className)}>
            <div className="min-w-0">
                <p className="text-xs font-semibold text-ink-soft">{label}</p>
                {keterangan && <p className="mt-0.5 text-[11px] text-muted-ink">{keterangan}</p>}
            </div>
            <span className={cn('shrink-0 text-[15px] font-extrabold', warna)}>{nilai}</span>
        </div>
    );
}

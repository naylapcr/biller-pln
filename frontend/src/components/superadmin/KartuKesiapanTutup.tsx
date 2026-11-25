import DonutChart from '@/components/ui/DonutChart';
import StatusChip from '@/components/ui/StatusChip';

export interface SyaratTutup {
    kunci: string;
    label: string;
    lolos: boolean;
    blokir: boolean;
    pesan: string | null;
}

export const bisaDitutup = (syarat: SyaratTutup[]) => syarat.every((s) => s.lolos || !s.blokir);

export const persenSiap = (syarat: SyaratTutup[]) => (syarat.length ? Math.round((syarat.filter((s) => s.lolos).length / syarat.length) * 100) : 0);

const RINGKAS: Record<string, { label: string; ya: string; tidak: string }> = {
    parameter: { label: 'Bonus J & Ambang', ya: 'Lengkap ✓', tidak: 'Belum valid' },
    batch: { label: 'Batch Menunggu', ya: 'Tidak ada ✓', tidak: 'Ada' },
    data: { label: 'Data Committed', ya: 'Ada ✓', tidak: 'Belum ada' },
    pemetaan: { label: 'Alias ACMT', ya: 'Lengkap ✓', tidak: 'Pending' },
};

/** Donut kesiapan tutup periode (03.0): persen prasyarat yang terpenuhi. */
export default function KartuKesiapanTutup({ syarat }: { syarat: SyaratTutup[] }) {
    return (
        <DonutChart
            bawah
            total={syarat.length}
            tengah={`${persenSiap(syarat)}%`}
            keterangan="Prasyarat"
            warnaTunggal="stroke-pln-blue"
            data={syarat.map((s) => {
                const r = RINGKAS[s.kunci] ?? { label: s.label, ya: 'Ya ✓', tidak: 'Belum' };
                return {
                    label: r.label,
                    nilai: s.lolos ? 1 : 0,
                    warna: 'stroke-pln-blue',
                    titik: s.lolos ? 'bg-success-line' : s.blokir ? 'bg-[#ef4444]' : 'bg-[#f59e0b]',
                    teks: s.lolos ? r.ya : s.kunci === 'pemetaan' ? `${s.pesan?.match(/\d+/)?.[0] ?? ''} Pending` : r.tidak,
                };
            })}
        />
    );
}

/** Chip prasyarat di gerbang penutupan (03.4). Login belum terpetakan hanya peringatan. */
export function ChipSyarat({ syarat }: { syarat: SyaratTutup[] }) {
    return (
        <div className="flex flex-wrap gap-2.5">
            {syarat.map((s) => (
                <StatusChip
                    key={s.kunci}
                    label={s.lolos ? `✓ ${s.label}` : `${s.blokir ? '✕' : '!'} ${(s.pesan ?? s.label).replace(/\.$/, '')}`}
                    tone={s.lolos ? 'success' : s.blokir ? 'danger' : 'waiting'}
                    className="max-w-full whitespace-normal"
                />
            ))}
        </div>
    );
}

/** Daftar centang prasyarat di modal tutup periode (04.4a). */
export function DaftarSyarat({ syarat }: { syarat: SyaratTutup[] }) {
    return (
        <ul className="grid gap-1.5 rounded-10 bg-canvas px-3 py-2.5 text-[11px] leading-[15px] font-semibold">
            {syarat.map((s) => (
                <li key={s.kunci} className={s.lolos ? 'text-success-ink' : s.blokir ? 'text-pln-red' : 'text-pln-yellow-ink'}>
                    {s.lolos ? '✓' : s.blokir ? '✕' : '!'} {s.lolos ? s.label : (s.pesan ?? s.label)}
                </li>
            ))}
        </ul>
    );
}

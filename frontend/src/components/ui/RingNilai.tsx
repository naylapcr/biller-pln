import { Donut } from '@/components/ui/DonutChart';
import { formatNumber } from '@/lib/format';

/** Cincin kecil 68 px untuk komponen Realisasi, Eviden, Kecepatan. */
export default function RingNilai({ label, nilai, warna }: { label: string; nilai: number | null; warna: string }) {
    return (
        <figure className="flex flex-col items-center gap-1.5">
            <Donut
                data={[{ nilai: nilai ?? 0, warna }]}
                total={100}
                ukuran={68}
                tebal={7}
                tengah={nilai === null ? '–' : `${formatNumber(nilai, nilai >= 99.95 ? 0 : 1)}%`}
                kelasTengah="text-xs leading-[15px]"
            />
            <figcaption className="text-[11px] font-semibold text-slate-ink">{label}</figcaption>
        </figure>
    );
}

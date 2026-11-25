import { Link } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';

import EmptyState from '@/components/ui/EmptyState';
import { Button } from '@/components/ui/button';

const LANGKAH = [
    ['Pastikan Parameter Periode', 'Super Admin memeriksa bobot R·E·K, Bonus Hari J & ambang kategori.'],
    ['Unggah Berkas ACMT', 'Koordinator mengunggah WBM Pascabayar, Baca Ulang, atau Prabayar.'],
    ['Validasi & Commit', 'Sistem memvalidasi GRAND TOTAL lalu menghitung skor otomatis.'],
];

/** 04.3 Dashboard saat periode belum punya data. */
export default function PeriodeKosong({ kode }: { kode: string }) {
    return (
        <EmptyState
            icon={ArrowUp}
            title={`Belum Ada Data Kinerja untuk Periode BLTH ${kode}`}
            className="lg:mt-[12vh]"
            action={
                <Button asChild>
                    <Link href={route('unggah.index', { periode: kode })}>+ Unggah Berkas Pertama</Link>
                </Button>
            }
        >
            <p className="mx-auto max-w-[520px] leading-5">Skor 25 Biller, Donut Chart distribusi, dan papan Top 5 / Bottom 5 akan muncul otomatis setelah berkas pertama di-commit.</p>
            <ol className="mt-[18px] grid gap-3.5 text-left md:grid-cols-3">
                {LANGKAH.map(([judul, isi], i) => (
                    <li key={judul} className="rounded-xl border border-line bg-canvas p-4">
                        <p className="text-[10px] font-bold text-sky-val">LANGKAH {i + 1}</p>
                        <p className="mt-1 text-[13px] font-bold text-ink">{judul}</p>
                        <p className="mt-1 text-[11px] leading-4">{isi}</p>
                    </li>
                ))}
            </ol>
        </EmptyState>
    );
}

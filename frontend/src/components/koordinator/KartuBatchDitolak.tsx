import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface CekOtomatis {
    kunci: string;
    label: string;
    lolos: boolean;
}

const CEK: Record<string, { judul: string; gagal: string; lolos: string }> = {
    grand_total: { judul: 'GRAND TOTAL vs Hitung Sistem', gagal: 'Tidak Cocok', lolos: 'Cocok' },
    header: { judul: 'Validitas Header Kolom', gagal: 'Tidak Lengkap', lolos: 'Lengkap' },
    sha256: { judul: 'Cek Duplikasi SHA-256', gagal: 'Terdeteksi Ganda', lolos: 'Berkas Baru' },
    periode_unit: { judul: 'Periode & Unit ULP', gagal: 'Tidak Sesuai', lolos: 'Sesuai' },
};

/** 04.1 Banner "Batch Otomatis Ditolak Sistem" dengan 4 cek (GRAND TOTAL, header, SHA-256, periode & unit). */
export default function KartuBatchDitolak({ cek, jumlahError, csv }: { cek: CekOtomatis[]; jumlahError: number; csv: string }) {
    return (
        <>
            <section role="alert" className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-[1.5px] border-[#ef4444] bg-error-row px-5 py-[18px]">
                <div className="min-w-0 flex-1 basis-72">
                    <h2 className="text-[15px] font-extrabold text-error-deep">✕ Batch Otomatis Ditolak Sistem</h2>
                    <p className="mt-1 max-w-[580px] text-xs leading-[15px] text-error-deep">
                        Ditemukan {formatNumber(jumlahError)} error kritis. Perbaiki berkas Excel sumber di ACMT lalu unggah ulang — tombol Commit dinonaktifkan selama masih ada Error.
                    </p>
                </div>
                <Button variant="secondary" asChild>
                    <a href={csv}>Unduh Laporan Error (.csv)</a>
                </Button>
            </section>
            <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                {cek.map((c) => {
                    const t = CEK[c.kunci] ?? { judul: c.label, gagal: 'Gagal', lolos: 'Lolos' };
                    return (
                        <div key={c.kunci} className={cn('rounded-2xl border p-4', c.lolos ? 'border-line bg-white shadow-kartu' : 'border-error-line bg-error-row')}>
                            <p className="text-[10px] font-semibold text-muted-ink">{t.judul}</p>
                            <p className={cn('mt-1 text-lg leading-[23px] font-extrabold', c.lolos ? 'text-success' : 'text-error-ink')}>{c.lolos ? t.lolos : t.gagal}</p>
                            <p className={cn('mt-1 text-[11px] font-medium', c.lolos ? 'text-success' : 'text-pln-red')}>
                                {c.lolos ? `✓ ${c.label}` : '✕ Lihat rincian error di bawah'}
                            </p>
                        </div>
                    );
                })}
            </div>
        </>
    );
}

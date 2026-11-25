import { LoaderCircle } from 'lucide-react';
import { useRef, useState, type DragEvent } from 'react';

import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface UnggahDropzoneProps {
    maksKb: number;
    /** Nama modul yang dipilih, tampil di keterangan. */
    modul: string;
    error?: string;
    /** Persen unggah selama berkas dikirim. */
    progres: number | null;
    nonaktif?: boolean;
    onPilih: (file: File) => void;
}

/** Dropzone berkas .xlsx/.csv (02.3). Berkas yang dipilih langsung diunggah dan divalidasi. */
export default function UnggahDropzone({ maksKb, modul, error, progres, nonaktif, onPilih }: UnggahDropzoneProps) {
    const input = useRef<HTMLInputElement>(null);
    const [seret, setSeret] = useState(false);
    const sibuk = progres !== null;
    const mati = sibuk || nonaktif;

    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        setSeret(false);
        const f = e.dataTransfer.files?.[0];
        if (f && !mati) onPilih(f);
    };

    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setSeret(true);
            }}
            onDragLeave={() => setSeret(false)}
            onDrop={onDrop}
            className={cn(
                'flex min-h-[142px] flex-col items-center justify-center gap-2.5 rounded-2xl border-2 border-dashed px-6 py-5 text-center transition-colors',
                seret ? 'border-pln-blue bg-pln-blue-light' : 'border-sky-line bg-sky-row',
                error && 'border-error-line bg-error-row',
                nonaktif && 'opacity-60',
            )}
        >
            <p className="text-[15px] font-bold text-sky-ink">↑ Seret & Lepas Berkas Rekap WBM (.xlsx / .csv)</p>
            <p className="text-xs text-muted-ink">
                {modul} · Maks. {formatNumber(maksKb / 1024)} MB · Format sesuai ekspor ACMT
            </p>
            <Button type="button" disabled={mati} onClick={() => input.current?.click()}>
                {sibuk && <LoaderCircle className="animate-spin" />}
                {sibuk ? `Mengunggah ${formatNumber(progres)}%` : 'Pilih Berkas & Jalankan Validasi'}
            </Button>
            <input
                ref={input}
                type="file"
                accept=".xlsx,.csv"
                className="sr-only"
                tabIndex={-1}
                aria-label="Berkas rekap ACMT"
                onChange={(e) => {
                    const f = e.target.files?.[0];
                    if (f) onPilih(f);
                    e.target.value = '';
                }}
            />
            {error && <p className="text-xs font-semibold text-error-ink">{error}</p>}
        </div>
    );
}

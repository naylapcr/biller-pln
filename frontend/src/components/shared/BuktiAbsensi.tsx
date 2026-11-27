import { Camera, FileText, MapPin } from 'lucide-react';

import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatTanggal } from '@/lib/format';

/** Tautan lampiran izin/sakit (foto atau PDF, dibuka di tab baru; server memeriksa hak akses). */
export function LinkFoto({ url, pdf = false }: { url: string | null; pdf?: boolean }) {
    if (!url) return <span className="text-faint">—</span>;
    const Ikon = pdf ? FileText : Camera;

    return (
        <a href={url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 font-semibold whitespace-nowrap text-pln-blue hover:underline">
            <Ikon className="size-3.5" aria-hidden />
            {pdf ? 'Lihat PDF' : 'Lihat foto'}
        </a>
    );
}

/** Lokasi absen: jarak ke kantor, bertaut ke peta. */
export function JarakKantor({ lat, lng, jarak }: { lat: number | null; lng: number | null; jarak: number | null }) {
    if (lat === null || lng === null) return <span className="text-faint">—</span>;

    return (
        <a
            href={`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 whitespace-nowrap hover:text-pln-blue hover:underline"
        >
            <MapPin className="size-3.5 text-success" aria-hidden />
            {jarak !== null ? `${formatNumber(jarak)} m dari kantor` : `${lat.toFixed(4)}, ${lng.toFixed(4)}`}
        </a>
    );
}

/** Jam masuk dan pulang (WIB). Hadir tanpa absen pulang ditandai. */
export function JamMasukPulang({ masuk, pulang, status }: { masuk: string | null; pulang: string | null; status: string }) {
    if (!masuk) return <span className="text-faint">—</span>;

    return (
        <span className="font-mono whitespace-nowrap">
            {formatTanggal(masuk, 'HH:mm')}
            {' – '}
            {pulang ? formatTanggal(pulang, 'HH:mm') : status === 'hadir' ? <span className="font-sans text-[11px] text-pln-yellow-ink">belum pulang</span> : '—'}
        </span>
    );
}

/** Chip terlambat / pulang cepat (menit). Tidak tampil bila tepat waktu. */
export function ChipTepatWaktu({ terlambat, cepat }: { terlambat: number | null | undefined; cepat: number | null | undefined }) {
    return (
        <>
            {!!terlambat && terlambat > 0 && <StatusChip tone="waiting" label={`Terlambat ${formatNumber(terlambat)} mnt`} />}
            {!!cepat && cepat > 0 && <StatusChip tone="waiting" label={`Pulang cepat ${formatNumber(cepat)} mnt`} />}
        </>
    );
}

/** IP dan perangkat saat absen, untuk melihat titip absen. */
export function IpPerangkat({ ip, perangkat }: { ip: string | null; perangkat: string | null }) {
    if (!ip) return <span className="text-faint">—</span>;

    return (
        <span className="block leading-4 whitespace-nowrap">
            <span className="font-mono">{ip}</span>
            {perangkat && <span className="block text-[10px] text-muted-ink">{perangkat}</span>}
        </span>
    );
}

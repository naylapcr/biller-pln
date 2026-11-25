
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import StatusChip from '@/components/ui/StatusChip';
import { formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI } from '@/lib/status';
import type { OrangAbsensi, StatusAbsensi } from '@/types';

export interface BarisHarian extends OrangAbsensi {
    attendance_id: number | null;
    status: StatusAbsensi | 'belum';
    absen_at: string | null;
    lat: number | null;
    lng: number | null;
    keterangan: string | null;
    sumber: 'mandiri' | 'sistem' | null;
    dikoreksi: boolean;
}

/** Pantauan harian absensi semua biller (02.7). */
export default function TabelPantauanHarian({ data }: { data: BarisHarian[] }) {
    const columns: ColumnDef<BarisHarian>[] = [
        {
            header: 'NAMA BILLER',
            cell: ({ row: { original: b } }) => (
                <>
                    {b.nama}
                    {b.peran !== 'biller' && <span className="block text-[10px] text-muted-ink">{b.peran_label}</span>}
                </>
            ),
        },
        { header: 'NIP', cell: ({ row }) => <span className="font-mono">{row.original.nip}</span> },
        { header: 'STATUS', cell: ({ row }) => <StatusChip {...STATUS_ABSENSI[row.original.status]} titik /> },
        {
            header: 'JAM SERVER (WIB)',
            cell: ({ row }) => <span className="font-mono whitespace-nowrap">{row.original.absen_at ? `${formatTanggal(row.original.absen_at, 'HH:mm:ss')} WIB` : '—'}</span>,
        },
        {
            header: 'LOKASI',
            cell: ({ row: { original: b } }) =>
                b.lat !== null && b.lng !== null ? (
                    <a href={`https://www.google.com/maps/search/?api=1&query=${b.lat},${b.lng}`} target="_blank" rel="noreferrer" className="hover:text-pln-blue hover:underline">
                        Terdeteksi ✓
                    </a>
                ) : b.status === 'belum' ? (
                    '—'
                ) : (
                    'Tidak dibagikan'
                ),
        },
        {
            header: 'KETERANGAN',
            cell: ({ row: { original: b } }) => [b.keterangan ?? (b.status === 'alpa' && b.sumber === 'sistem' ? 'Alpa otomatis 23:59' : null), b.dikoreksi && 'Dikoreksi'].filter(Boolean).join(' · ') || '—',
        },

    ];

    return <DataTable columns={columns} data={data} getRowId={(b) => String(b.user_id)} empty="Tidak ada akun yang wajib absen." />;
}

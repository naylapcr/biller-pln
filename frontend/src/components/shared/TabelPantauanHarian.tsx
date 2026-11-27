
import { ChipTepatWaktu, IpPerangkat, JamMasukPulang, JarakKantor, LinkFoto } from '@/components/shared/BuktiAbsensi';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import StatusChip from '@/components/ui/StatusChip';
import { formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI } from '@/lib/status';
import type { BarisAbsensi, OrangAbsensi, StatusAbsensi } from '@/types';

export interface BarisHarian extends OrangAbsensi, Omit<BarisAbsensi, 'id' | 'tanggal' | 'status' | 'sumber' | 'lampiran_pdf'> {
    attendance_id: number | null;
    status: StatusAbsensi | 'belum';
    sumber: 'mandiri' | 'sistem' | null;
    lampiran_pdf: boolean | null;
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
        {
            header: 'STATUS',
            cell: ({ row: { original: b } }) => (
                <span className="flex flex-wrap gap-1">
                    <StatusChip {...STATUS_ABSENSI[b.status]} titik />
                    <ChipTepatWaktu terlambat={b.terlambat_menit} cepat={b.pulang_cepat_menit} />
                </span>
            ),
        },
        { header: 'MASUK – PULANG', cell: ({ row: { original: b } }) => <JamMasukPulang masuk={b.absen_at} pulang={b.pulang_at} status={b.status} /> },
        {
            header: 'LOKASI',
            cell: ({ row: { original: b } }) => <JarakKantor lat={b.lat} lng={b.lng} jarak={b.jarak_m} />,
        },
        { header: 'LAMPIRAN', cell: ({ row }) => <LinkFoto url={row.original.foto_url} pdf={!!row.original.lampiran_pdf} /> },
        { header: 'IP · PERANGKAT', cell: ({ row: { original: b } }) => <IpPerangkat ip={b.ip} perangkat={b.perangkat} /> },
        {
            header: 'KETERANGAN',
            cell: ({ row: { original: b } }) => [b.keterangan ?? (b.status === 'alpa' && b.sumber === 'sistem' ? 'Alpa otomatis 23:59' : null), b.dikoreksi && 'Dikoreksi'].filter(Boolean).join(' · ') || '—',
        },

    ];

    return <DataTable columns={columns} data={data} getRowId={(b) => String(b.user_id)} empty="Tidak ada akun yang wajib absen." />;
}

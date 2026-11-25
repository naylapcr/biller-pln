import { router } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import StatusChip from '@/components/ui/StatusChip';
import { Input } from '@/components/ui/input';
import { formatNumber, formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI, type StatusTone } from '@/lib/status';
import type { BarisAbsensi, RingkasanAbsensi } from '@/types';

/** Kartu Ringkasan Bulan Ini: Hadir, Izin, Sakit, Alpa. */
export function RingkasanBulan({ bulan, ringkasan, className }: { bulan: string; ringkasan: RingkasanAbsensi; className?: string }) {
    const baris: [string, number, StatusTone][] = [
        ['Hadir', ringkasan.hadir, 'success'],
        ['Izin', ringkasan.izin, 'info'],
        ['Sakit', ringkasan.sakit, 'waiting'],
        ['Alpa (Lewat 23:59)', ringkasan.alpa, 'danger'],
    ];

    return (
        <Kartu judul="Ringkasan Bulan Ini" subjudul={`Periode ${formatTanggal(`${bulan}-01`, 'MMMM yyyy')}`} className={className}>
            {baris.map(([label, n, tone]) => (
                <div key={label} className="flex items-center justify-between gap-3 rounded-10 bg-canvas px-3 py-2.5">
                    <span className="text-xs font-semibold text-slate-ink">{label}</span>
                    <StatusChip label={`${formatNumber(n)} Hari`} tone={tone} />
                </div>
            ))}
        </Kartu>
    );
}

/** Riwayat absensi sendiri (TANGGAL, STATUS, JAM SERVER, KOORDINAT LOKASI, KETERANGAN, SUMBER). */
export default function RiwayatAbsensi({ bulan, riwayat }: { bulan: string; riwayat: BarisAbsensi[] }) {
    const columns: ColumnDef<BarisAbsensi>[] = [
        { header: 'TANGGAL', cell: ({ row }) => <span className="whitespace-nowrap">{formatTanggal(row.original.tanggal, 'd MMM yyyy')}</span> },
        { header: 'STATUS', cell: ({ row }) => <StatusChip {...STATUS_ABSENSI[row.original.status]} titik /> },
        {
            header: 'JAM SERVER (WIB)',
            cell: ({ row }) => <span className="font-mono whitespace-nowrap">{row.original.absen_at ? `${formatTanggal(row.original.absen_at, 'HH:mm:ss')} WIB` : '–'}</span>,
        },
        {
            header: 'KOORDINAT LOKASI',
            cell: ({ row: { original: a } }) =>
                a.lat !== null && a.lng !== null ? (
                    <a
                        href={`https://www.google.com/maps/search/?api=1&query=${a.lat},${a.lng}`}
                        target="_blank"
                        rel="noreferrer"
                        className="font-mono whitespace-nowrap hover:text-pln-blue hover:underline"
                    >
                        {a.lat.toFixed(4)}, {a.lng.toFixed(4)}
                    </a>
                ) : (
                    'Tidak dibagikan'
                ),
        },
        {
            header: 'KETERANGAN',
            cell: ({ row: { original: a } }) => (a.alasan_diubah ? [a.keterangan, `Dikoreksi: ${a.alasan_diubah}`].filter(Boolean).join(' · ') : (a.keterangan ?? '—')),
        },
        { header: 'SUMBER', cell: ({ row }) => (row.original.dikoreksi ? 'Koreksi SA' : row.original.sumber === 'mandiri' ? 'Mandiri' : 'Sistem') },
    ];

    return (
        <Kartu
            judul={`Riwayat Absensi Saya — ${formatTanggal(`${bulan}-01`, 'MMMM yyyy')}`}
            aksi={
                <Input
                    type="month"
                    aria-label="Bulan"
                    className="h-9 w-40"
                    value={bulan}
                    onChange={(e) => e.target.value && router.get(window.location.pathname, { bulan: e.target.value }, { preserveScroll: true })}
                />
            }
        >
            <DataTable columns={columns} data={riwayat} getRowId={(a) => String(a.id)} empty="Belum ada absensi di bulan ini." />
        </Kartu>
    );
}

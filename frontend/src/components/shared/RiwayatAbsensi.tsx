import { router } from '@inertiajs/react';

import { ChipTepatWaktu, JamMasukPulang, JarakKantor, LinkFoto } from '@/components/shared/BuktiAbsensi';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import StatusChip from '@/components/ui/StatusChip';
import { Input } from '@/components/ui/input';
import { formatNumber, formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI, type StatusTone } from '@/lib/status';
import type { BarisAbsensi, RingkasanAbsensi } from '@/types';

/** Kartu Ringkasan Bulan Ini: Hadir, Terlambat, Pulang Cepat, Izin, Sakit, Alpa (tidak absen). */
export function RingkasanBulan({ bulan, ringkasan, className }: { bulan: string; ringkasan: RingkasanAbsensi; className?: string }) {
    const baris: [string, number, StatusTone][] = [
        ['Hadir', ringkasan.hadir, 'success'],
        ['Terlambat', ringkasan.terlambat ?? 0, 'waiting'],
        ['Pulang Cepat', ringkasan.pulang_cepat ?? 0, 'waiting'],
        ['Izin', ringkasan.izin, 'info'],
        ['Sakit', ringkasan.sakit, 'info'],
        ['Alpa (Tidak Absen)', ringkasan.alpa, 'danger'],
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

/** Riwayat absensi sendiri (TANGGAL, STATUS, MASUK – PULANG, LOKASI, LAMPIRAN, KETERANGAN). */
export default function RiwayatAbsensi({ bulan, riwayat }: { bulan: string; riwayat: BarisAbsensi[] }) {
    const columns: ColumnDef<BarisAbsensi>[] = [
        { header: 'TANGGAL', cell: ({ row }) => <span className="whitespace-nowrap">{formatTanggal(row.original.tanggal, 'd MMM yyyy')}</span> },
        {
            header: 'STATUS',
            cell: ({ row: { original: a } }) => (
                <span className="flex flex-wrap gap-1">
                    <StatusChip {...STATUS_ABSENSI[a.status]} titik />
                    <ChipTepatWaktu terlambat={a.terlambat_menit} cepat={a.pulang_cepat_menit} />
                </span>
            ),
        },
        { header: 'MASUK – PULANG', cell: ({ row: { original: a } }) => <JamMasukPulang masuk={a.absen_at} pulang={a.pulang_at} status={a.status} /> },
        {
            header: 'LOKASI',
            cell: ({ row: { original: a } }) => <JarakKantor lat={a.lat} lng={a.lng} jarak={a.jarak_m} />,
        },
        { header: 'LAMPIRAN', cell: ({ row }) => <LinkFoto url={row.original.foto_url} pdf={row.original.lampiran_pdf} /> },
        {
            header: 'KETERANGAN',
            cell: ({ row: { original: a } }) => (a.alasan_diubah ? [a.keterangan, `Dikoreksi: ${a.alasan_diubah}`].filter(Boolean).join(' · ') : (a.keterangan ?? '—')),
        },
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

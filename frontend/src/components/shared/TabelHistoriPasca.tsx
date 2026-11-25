import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatPercent, formatTanggal } from '@/lib/format';
import type { HistoriPasca } from '@/types';

const bagian = (a: number, b: number) => (b > 0 ? ` (${formatPercent((a / b) * 100, a === b ? 0 : 1)})` : '');

/**
 * Histori WO pascabayar per hari baca dan RBM (01.3). Kecepatan = TERBACA ÷ (hari aktif RBM × 6 jam),
 * sesuai rumus 6.1; MULAI–SELESAI menampilkan tanggal dan jam.
 */
export default function TabelHistoriPasca({ data, bonusHariJ }: { data: HistoriPasca[]; bonusHariJ?: number }) {
    const columns: ColumnDef<HistoriPasca>[] = [
        { header: 'HARI', cell: ({ row }) => <StatusChip label={`Hari ${row.original.hari_baca}`} tone={row.original.hari_baca === 'J' ? 'violet' : 'info'} /> },
        { header: 'KODE RBM', cell: ({ row }) => <span className="font-mono">{row.original.kode_rbm}</span> },
        { header: 'JENIS METER', cell: ({ row }) => (row.original.jenis_meter === 'tunggal' ? 'Tunggal' : 'Ganda Ind.') },
        { header: 'JML PLG', cell: ({ row }) => formatNumber(row.original.jml_plg) },
        { header: 'TERBACA', cell: ({ row: { original: r } }) => formatNumber(r.terbaca) + bagian(r.terbaca, r.jml_plg) },
        { header: 'FOTO VALID', cell: ({ row: { original: r } }) => formatNumber(r.foto) + bagian(r.foto, r.terbaca) },
        {
            header: 'MULAI – SELESAI (ACMT)',
            cell: ({ row: { original: r } }) => (
                <span className="font-mono text-[11px] whitespace-nowrap">
                    {formatTanggal(r.mulai_at, 'd/MM HH:mm')} – {formatTanggal(r.selesai_at, 'd/MM HH:mm')}
                </span>
            ),
        },
        {
            header: 'KECEPATAN',
            cell: ({ row: { original: r } }) => (
                <span className="whitespace-nowrap" title={r.hari_aktif ? `${r.hari_aktif} hari aktif × 6 jam` : undefined}>
                    {r.hari_baca === 'J' && bonusHariJ ? `Bonus +${formatNumber(bonusHariJ, 1)}` : r.plg_per_jam === null ? '–' : `${formatNumber(r.plg_per_jam, 1)} plg/jam`}
                </span>
            ),
        },
    ];

    return <DataTable columns={columns} data={data} getRowId={(r) => String(r.id)} empty="Belum ada data pascabayar." />;
}

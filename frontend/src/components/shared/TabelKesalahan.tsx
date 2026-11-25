import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import StatusChip from '@/components/ui/StatusChip';
import { formatNumber } from '@/lib/format';
import type { StatusMeta } from '@/lib/status';
import type { Kesalahan } from '@/types';

function hasil(k: Kesalahan): StatusMeta {
    if (k.dihitung) return { label: `${k.hasil_label} (−)`, tone: 'danger' };
    return k.sumber === 'Baca Ulang' ? { label: 'Sisipan (0)', tone: 'waiting' } : { label: 'Frontliner (0)', tone: 'success' };
}

/** Rincian Kesalahan: kasus Baca Ulang dan koreksi AP2T (01.1). */
export default function TabelKesalahan({ data }: { data: Kesalahan[] }) {
    const columns: ColumnDef<Kesalahan>[] = [
        { header: 'IDPEL', cell: ({ row }) => <span className="font-mono">{row.original.idpel}</span> },
        { header: 'KODE RBM', cell: ({ row }) => <span className="font-mono">{row.original.kode_rbm ?? '–'}</span> },
        { header: 'SUMBER', cell: ({ row }) => <StatusChip label={row.original.sumber} tone={row.original.sumber === 'AP2T' ? 'sky' : 'info'} /> },
        {
            header: 'STAN AWAL → BU',
            cell: ({ row: { original: k } }) => (
                <span className="font-mono whitespace-nowrap">
                    {formatNumber(k.stan_awal, 0)} → {formatNumber(k.stan_bu, 0)}
                </span>
            ),
        },
        { header: 'HASIL PENILAIAN', cell: ({ row }) => <StatusChip {...hasil(row.original)} /> },
        { header: 'KETERANGAN', cell: ({ row }) => row.original.keterangan ?? '–' },
    ];

    return <DataTable columns={columns} data={data} getRowId={(k) => k.id} empty="Tidak ada kasus kesalahan di periode ini." />;
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import PeriodeSelect from '@/components/shared/PeriodeSelect';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import type { Option, PageProps, PeriodeInfo } from '@/types';

interface LoginBelum {
    login: string;
    baris: number;
    per_modul: Record<string, number>;
    batch: { id: string; file_name: string; modul: string | null }[];
}

interface Alias {
    id: number;
    login_acmt: string;
    biller: string | null;
    kode_petugas: string | null;
    berlaku_mulai: string | null;
    aktif: boolean;
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    belum: LoginBelum[];
    billerOptions: Option<number>[];
    aliases: Alias[];
}>;

/** Baris satu login yang belum terpetakan, dengan pilihan biller. */
function BarisPetakan({ login, billerOptions }: { login: LoginBelum; billerOptions: Option<number>[] }) {
    const form = useForm({ login_acmt: login.login, biller_id: '' });
    const galat = form.errors.login_acmt ?? form.errors.biller_id;

    return (
        <form
            className="flex flex-wrap items-center gap-3 rounded-xl border border-pln-yellow-line bg-pln-yellow-row px-4 py-3.5"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('pengaturan.pemetaan.store'), { preserveScroll: true });
            }}
        >
            <div className="min-w-0 flex-1 basis-56">
                <p className="font-mono text-[13px] font-bold break-all text-[#92400e]">{login.login}</p>
                <p className="mt-0.5 text-[11px] text-[#92400e]">
                    {formatNumber(login.baris)} baris ({Object.entries(login.per_modul).map(([m, n]) => `${m} ${formatNumber(n)}`).join(', ')}) · {login.batch.map((b) => b.file_name).join(', ')}
                </p>
                {galat && <p className="mt-1 text-[11px] font-semibold text-error-ink">{galat}</p>}
            </div>
            <NativeSelect className="w-full sm:w-64" aria-label={`Biller untuk ${login.login}`} value={form.data.biller_id} onChange={(e) => form.setData('biller_id', e.target.value)}>
                <option value="">Pilih Biller…</option>
                {billerOptions.map((b) => (
                    <option key={b.value} value={b.value}>
                        {b.label}
                    </option>
                ))}
            </NativeSelect>
            <Button type="submit" variant="success" disabled={!form.data.biller_id || form.processing}>
                {form.processing && <LoaderCircle className="animate-spin" />}
                Simpan Alias & Hitung Ulang
            </Button>
        </form>
    );
}

/** 03.3 Pemetaan Petugas & Alias (Super Admin). */
export default function PemetaanAlias() {
    const { periode, periodeOptions, belum, billerOptions, aliases } = usePage<Props>().props;

    const columns: ColumnDef<Alias>[] = [
        { header: 'LOGIN ACMT (ALIAS)', cell: ({ row }) => <span className="font-mono font-bold">{row.original.login_acmt}</span> },
        { header: 'BILLER TERPETAKAN', cell: ({ row }) => row.original.biller ?? '–' },
        { header: 'KODE PETUGAS', cell: ({ row }) => <span className="font-mono">{row.original.kode_petugas ?? '–'}</span> },
        { header: 'BERLAKU MULAI', cell: ({ row }) => formatTanggal(row.original.berlaku_mulai, 'dd MMM yyyy') },
        { header: 'STATUS', cell: ({ row }) => (row.original.aktif ? <StatusChip label="Terpetakan ✓" tone="success" /> : <StatusChip label="Biller nonaktif" tone="neutral" />) },
    ];

    return (
        <>
            <Head title="Pemetaan Petugas & Alias" />
            <PageHeader
                title="Pemetaan Petugas & Simpan Alias (biller_aliases)"
                description="Petakan login ACMT baru ke Master 25 Biller · Menyimpan alias langsung mengisi biller_id & menghitung ulang skor"
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} />
                        <StatusChip titik label={belum.length ? `${belum.length} Login Belum Terpetakan` : 'Semua Terpetakan'} tone={belum.length ? 'waiting' : 'success'} />
                    </>
                }
            />
            <div className="grid gap-4">
                <Kartu judul="Petakan Login ACMT Baru ke Master Biller" className={belum.length ? 'border-[1.5px] border-[#f59e0b]' : undefined}>
                    <p className="text-xs leading-[15px] text-muted-ink">
                        Setelah alias disimpan ke tabel biller_aliases, seluruh baris reading_logs / reread_cases / prepaid_inspections terkait otomatis terisi biller_id dan skor periode terbuka
                        dihitung ulang.
                    </p>
                    {belum.map((b) => (
                        <BarisPetakan key={b.login} login={b} billerOptions={billerOptions} />
                    ))}
                    {belum.length === 0 && <p className="text-xs font-semibold text-success">✓ Semua login ACMT sudah terpetakan.</p>}
                </Kartu>
                <Kartu judul="Riwayat Alias Login ACMT Aktif (biller_aliases)">
                    <DataTable columns={columns} data={aliases} getRowId={(a) => String(a.id)} empty="Belum ada alias." />
                </Kartu>
            </div>
        </>
    );
}

PemetaanAlias.layout = layoutSuperAdmin;

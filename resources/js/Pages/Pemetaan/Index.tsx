import { useForm, usePage } from '@inertiajs/react';
import { CircleCheck, Link2 } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import Toolbar from '@/Components/shared/Toolbar';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { Option, PageProps, PeriodeInfo } from '@/types';

interface Baris {
    login: string;
    baris: number;
    per_modul: Record<string, number>;
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    belum: Baris[];
    billerOptions: Option<number>[];
    bisaMemetakan: boolean;
}>;

export default function PemetaanIndex() {
    const { periode, periodeOptions, belum, billerOptions, bisaMemetakan } = usePage<Props>().props;
    const [login, setLogin] = useState<string | null>(null);

    const columns: ColumnDef<Baris>[] = [
        { header: 'Login ACMT', cell: ({ row }) => <span className="font-mono">{row.original.login}</span> },
        {
            header: 'Baris tanpa biller',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono">{formatNumber(row.original.baris)}</span>,
        },
        {
            header: 'Modul',
            cell: ({ row }) => (
                <span className="text-[13px] text-muted-ink">
                    {Object.entries(row.original.per_modul)
                        .map(([m, n]) => `${m} ${formatNumber(n)}`)
                        .join(' · ')}
                </span>
            ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) =>
                bisaMemetakan ? (
                    <Button size="sm" onClick={() => setLogin(row.original.login)}>
                        <Link2 />
                        Petakan
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout title="Pemetaan petugas">
            <PageHeader
                title="Pemetaan petugas"
                description="Login ACMT yang belum cocok dengan master biller. Barisnya tetap tersimpan tanpa biller dan belum masuk skor siapa pun sampai dipetakan."
            />
            <Toolbar>
                <PeriodeSelect value={periode?.kode} options={periodeOptions} />
            </Toolbar>

            {belum.length === 0 ? (
                <EmptyState icon={CircleCheck} title="Semua petugas sudah terpetakan">
                    Tidak ada baris tanpa biller di periode ini.
                </EmptyState>
            ) : (
                <>
                    {!bisaMemetakan && <p className="mb-3 text-[13px] text-muted-ink">Pemetaan dilakukan oleh Super Admin.</p>}
                    <DataTable columns={columns} data={belum} getRowId={(b) => b.login} />
                </>
            )}

            {login && <DialogAlias key={login} login={login} billerOptions={billerOptions} onClose={() => setLogin(null)} />}
        </AppLayout>
    );
}

function DialogAlias({ login, billerOptions, onClose }: { login: string; billerOptions: Option<number>[]; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({ login_acmt: login, biller_id: '', berlaku_mulai: '' });

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            title={`Petakan ${login}`}
            description="Login ini menjadi alias biller yang dipilih. Baris di periode terbuka diisi biller-nya dan skornya dihitung ulang."
            submitLabel="Simpan pemetaan"
            processing={processing}
            onSubmit={() => post(route('pemetaan.store'), { preserveScroll: true, onSuccess: onClose })}
        >
            <FormField id="login_acmt" label="Login ACMT" error={errors.login_acmt}>
                <Input id="login_acmt" value={data.login_acmt} readOnly className="font-mono" />
            </FormField>
            <FormField id="biller_id" label="Biller" required error={errors.biller_id}>
                <NativeSelect id="biller_id" value={data.biller_id} onChange={(e) => setData('biller_id', e.target.value)}>
                    <option value="">Pilih biller</option>
                    {billerOptions.map((b) => (
                        <option key={b.value} value={b.value}>
                            {b.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField id="berlaku_mulai" label="Berlaku mulai" error={errors.berlaku_mulai} hint="Opsional, untuk catatan.">
                <Input id="berlaku_mulai" type="date" value={data.berlaku_mulai} onChange={(e) => setData('berlaku_mulai', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}

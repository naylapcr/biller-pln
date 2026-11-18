import { router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import RowActions from '@/Components/shared/RowActions';
import SearchInput from '@/Components/shared/SearchInput';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import type { PageProps } from '@/types';

interface Biller {
    id: number;
    nip: string | null;
    nama: string;
    zona_wilayah: string | null;
    kode_petugas_acmt: string;
    login_acmt: string;
    is_active: boolean;
    akun: string | null;
    aliases: { id: number; login_acmt: string; berlaku_mulai: string | null }[];
}

type Props = PageProps<{ billers: Biller[]; filters: { q?: string } }>;

export default function BillersIndex() {
    const { billers, filters } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '' });
    const [form, setForm] = useState<{ open: boolean; biller: Biller | null }>({ open: false, biller: null });
    const hapus = useConfirmAction<Biller>();

    const columns: ColumnDef<Biller>[] = [
        {
            header: 'Biller',
            cell: ({ row: { original: b } }) => (
                <div>
                    <p className="font-medium text-ink">{b.nama}</p>
                    <p className="text-xs text-muted-ink">
                        {b.nip ? `NIP ${b.nip}` : 'NIP belum diisi'}
                        {b.zona_wilayah ? ` · ${b.zona_wilayah}` : ''}
                    </p>
                </div>
            ),
        },
        { header: 'Kode petugas', cell: ({ row }) => <span className="font-mono">{row.original.kode_petugas_acmt}</span> },
        {
            header: 'Login ACMT & alias',
            cell: ({ row: { original: b } }) => (
                <div className="flex flex-wrap items-center gap-1.5 font-mono text-[13px]">
                    <span>{b.login_acmt}</span>
                    {b.aliases.map((a) => (
                        <span key={a.id} className="inline-flex items-center gap-1 rounded-full bg-table-head px-2 py-0.5 text-xs">
                            {a.login_acmt}
                            <button
                                type="button"
                                aria-label={`Hapus alias ${a.login_acmt}`}
                                className="text-muted-ink hover:text-error-ink"
                                onClick={() => router.delete(route('pengaturan.alias.destroy', a.id), { preserveScroll: true })}
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                </div>
            ),
        },
        { header: 'Akun', cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.akun ?? '–'}</span> },
        {
            header: 'Status',
            cell: ({ row }) => (row.original.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: b } }) => (
                <RowActions
                    label={b.nama}
                    actions={[
                        { label: 'Ubah', icon: Pencil, onSelect: () => setForm({ open: true, biller: b }) },
                        { label: 'Hapus', icon: Trash2, danger: true, onSelect: () => hapus.ask(b) },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Master biller">
            <PageHeader
                title="Master biller"
                description={`${billers.length} biller. Login ACMT dan kode petugas dipakai untuk mencocokkan baris berkas ACMT; alias menampung login pengganti.`}
                actions={
                    <Button onClick={() => setForm({ open: true, biller: null })}>
                        <Plus />
                        Tambah biller
                    </Button>
                }
            />
            <Toolbar>
                <SearchInput className="md:w-72" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama, login, atau kode" />
            </Toolbar>
            <DataTable columns={columns} data={billers} getRowId={(b) => String(b.id)} empty="Belum ada biller." />

            {form.open && <BillerForm key={form.biller?.id ?? 'baru'} biller={form.biller} onClose={() => setForm({ open: false, biller: null })} />}

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus biller?"
                description="Biller yang sudah punya akun atau skor tidak bisa dihapus; nonaktifkan saja."
                confirmLabel="Hapus biller"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.billers.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function BillerForm({ biller, onClose }: { biller: Biller | null; onClose: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        nama: biller?.nama ?? '',
        nip: biller?.nip ?? '',
        zona_wilayah: biller?.zona_wilayah ?? '',
        login_acmt: biller?.login_acmt ?? '18111.',
        kode_petugas_acmt: biller?.kode_petugas_acmt ?? '',
        is_active: biller?.is_active ?? true,
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (biller) put(route('pengaturan.billers.update', biller.id), opts);
        else post(route('pengaturan.billers.store'), opts);
    };

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            title={biller ? `Ubah ${biller.nama}` : 'Tambah biller'}
            description="Login ACMT dicocokkan tanpa memperhatikan huruf besar-kecil, tetapi harus persis sama."
            submitLabel={biller ? 'Simpan perubahan' : 'Tambah biller'}
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="nama" label="Nama" required error={errors.nama}>
                <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="login_acmt" label="Login ACMT" required error={errors.login_acmt} hint="Contoh: 18111.akmal">
                    <Input id="login_acmt" className="font-mono" autoCapitalize="none" value={data.login_acmt} onChange={(e) => setData('login_acmt', e.target.value)} />
                </FormField>
                <FormField id="kode_petugas_acmt" label="Kode petugas" required error={errors.kode_petugas_acmt} hint="Contoh: AWA">
                    <Input id="kode_petugas_acmt" className="font-mono uppercase" value={data.kode_petugas_acmt} onChange={(e) => setData('kode_petugas_acmt', e.target.value)} />
                </FormField>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="nip" label="NIP" error={errors.nip}>
                    <Input id="nip" className="font-mono" value={data.nip} onChange={(e) => setData('nip', e.target.value)} />
                </FormField>
                <FormField id="zona_wilayah" label="Zona wilayah" error={errors.zona_wilayah}>
                    <Input id="zona_wilayah" value={data.zona_wilayah} onChange={(e) => setData('zona_wilayah', e.target.value)} />
                </FormField>
            </div>
            <div className="flex items-center gap-2.5">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                <Label htmlFor="is_active" className="text-sm font-normal">
                    Biller aktif (ikut Top/Bottom 5)
                </Label>
            </div>
        </FormDialog>
    );
}

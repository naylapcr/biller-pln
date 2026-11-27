import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Trash2, X } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/components/ui/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import FormDialog from '@/components/ui/FormDialog';
import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import RowActions from '@/components/ui/RowActions';
import SearchInput from '@/components/ui/SearchInput';
import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
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

type Props = PageProps<{ billers: Biller[]; filters: { q?: string }; statistik: { biller: number; aktif: number; alias: number; belum: number } }>;

/** 03.2 Master 25 Biller. */
export default function MasterBiller() {
    const { billers, filters, statistik } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '' });
    const [form, setForm] = useState<{ open: boolean; biller: Biller | null }>({ open: false, biller: null });
    const hapus = useConfirmAction<Biller>();
    const hapusAlias = useConfirmAction<Biller['aliases'][number]>();

    const columns: ColumnDef<Biller>[] = [
        { header: 'NIP', cell: ({ row }) => <span className="font-mono">{row.original.nip ?? '–'}</span> },
        { header: 'NAMA BILLER', cell: ({ row }) => <span className="font-bold text-ink">{row.original.nama}</span> },
        { header: 'WILAYAH', cell: ({ row }) => row.original.zona_wilayah ?? '–' },
        { header: 'KODE PETUGAS', cell: ({ row }) => <span className="font-mono font-bold">{row.original.kode_petugas_acmt}</span> },
        { header: 'USER ACMT UTAMA', cell: ({ row }) => <span className="font-mono">{row.original.login_acmt}</span> },
        {
            header: 'ALIAS',
            cell: ({ row: { original: b } }) => (
                <div className="flex flex-wrap items-center gap-1.5">
                    {b.aliases.length === 0 && '—'}
                    {b.aliases.map((a) => (
                        <span key={a.id} className="inline-flex items-center gap-1 rounded-full bg-sky-tint px-2.5 py-1 font-mono text-[10px] text-sky-ink">
                            {a.login_acmt}
                            <button type="button" aria-label={`Hapus alias ${a.login_acmt}`} className="hover:text-error-ink" onClick={() => hapusAlias.ask(a)}>
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                </div>
            ),
        },
        { header: 'STATUS', cell: ({ row }) => (row.original.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />) },
        {
            id: 'aksi',
            header: 'AKSI',
            cell: ({ row: { original: b } }) => (
                <div className="flex items-center gap-1">
                    <Button variant="secondary" size="sm" onClick={() => setForm({ open: true, biller: b })}>
                        Edit
                    </Button>
                    <RowActions label={b.nama} actions={[{ label: 'Hapus', icon: Trash2, danger: true, onSelect: () => hapus.ask(b) }]} />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Master 25 Biller" />
            <PageHeader
                title={`Master Data ${statistik.biller} Biller (billers)`}
                description="Kelola NIP, wilayah tugas, kode petugas ACMT, user ACMT utama, dan daftar alias"
                actions={
                    <>
                        <Button variant="secondary" asChild>
                            <Link href={route('pengaturan.pemetaan.index')}>Pemetaan Alias ({statistik.belum})</Link>
                        </Button>
                        <Button onClick={() => setForm({ open: true, biller: null })}>+ Tambah Biller</Button>
                    </>
                }
            />
            <div className="grid gap-4">
                <div className="grid gap-3.5 sm:grid-cols-3">
                    <StatCard label="Total Master Biller Aktif" value={`${statistik.aktif} Biller`} />
                    <StatCard label="Kode Petugas ACMT Unik" value={`${statistik.biller} Kode`} valueClassName="text-pln-blue" />
                    <StatCard label="Total Alias Login ACMT" value={`${statistik.alias} Alias`} valueClassName="text-success" />
                </div>
                <Kartu
                    judul={`Daftar Master ${statistik.biller} Biller ULP Kota Barat`}
                    aksi={<SearchInput className="w-full sm:w-64" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama, login, atau kode" />}
                >
                    <DataTable columns={columns} data={billers} getRowId={(b) => String(b.id)} empty="Belum ada biller." minLebar={860} />
                </Kartu>
            </div>

            {form.open && <BillerForm key={form.biller?.id ?? 'baru'} biller={form.biller} onClose={() => setForm({ open: false, biller: null })} />}

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                chip={{ label: 'AKSI PERMANEN · SUPER ADMIN', tone: 'danger' }}
                garis="merah"
                title={`Hapus ${hapus.target?.nama ?? 'biller'}?`}
                description="Biller yang sudah punya akun atau skor tidak bisa dihapus; nonaktifkan saja."
                confirmLabel="Ya, Hapus Biller"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.billers.destroy', hapus.target.id))}
            />
            <ConfirmDialog
                open={hapusAlias.open}
                onOpenChange={(v) => !v && hapusAlias.close()}
                chip={{ label: 'SUPER ADMIN · BILLER_ALIASES', tone: 'waiting' }}
                garis="kuning"
                title={`Hapus alias ${hapusAlias.target?.login_acmt ?? ''}?`}
                description="Data login ini di periode terbuka kembali belum terpetakan dan skor dihitung ulang."
                confirmLabel="Ya, Hapus Alias"
                tone="destructive"
                processing={hapusAlias.processing}
                onConfirm={() => hapusAlias.target && hapusAlias.run('delete', route('pengaturan.alias.destroy', hapusAlias.target.id))}
            />
        </>
    );
}

/** 04.5b Tambah / Edit Master Biller. */
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
            chip={{ label: 'SUPER ADMIN · TABEL BILLERS', tone: 'info' }}
            title={biller ? 'Edit Master Biller' : 'Tambah Master Biller'}
            submitLabel="Simpan Master Biller"
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="nip" label="NIP Biller" error={errors.nip}>
                <Input id="nip" className="font-mono" value={data.nip} onChange={(e) => setData('nip', e.target.value)} />
            </FormField>
            <FormField id="nama" label="Nama Biller" required error={errors.nama}>
                <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} />
            </FormField>
            <FormField id="zona_wilayah" label="Nama Wilayah / Sektor" error={errors.zona_wilayah}>
                <Input id="zona_wilayah" value={data.zona_wilayah} onChange={(e) => setData('zona_wilayah', e.target.value)} />
            </FormField>
            <FormField id="kode_petugas_acmt" label="Kode Petugas ACMT (Unik)" required error={errors.kode_petugas_acmt}>
                <Input id="kode_petugas_acmt" className="font-mono uppercase" placeholder="Contoh: AWA" value={data.kode_petugas_acmt} onChange={(e) => setData('kode_petugas_acmt', e.target.value)} />
            </FormField>
            <FormField id="login_acmt" label="User ACMT Utama (Unik)" required error={errors.login_acmt}>
                <Input id="login_acmt" className="font-mono" autoCapitalize="none" value={data.login_acmt} onChange={(e) => setData('login_acmt', e.target.value)} />
            </FormField>
            <div className="flex items-center gap-2.5">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                <Label htmlFor="is_active">Biller aktif (ikut Top 5 Teratas/Terbawah)</Label>
            </div>
        </FormDialog>
    );
}

MasterBiller.layout = layoutSuperAdmin;

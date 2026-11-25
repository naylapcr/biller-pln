import { Head, useForm, usePage } from '@inertiajs/react';
import { LockOpen, Trash2 } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/components/ui/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import FormDialog from '@/components/ui/FormDialog';
import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import PasswordInput from '@/components/ui/PasswordInput';
import RowActions from '@/components/ui/RowActions';
import SearchInput from '@/components/ui/SearchInput';
import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import type { StatusTone } from '@/lib/status';
import type { Option, PageProps, Paginated, Role } from '@/types';

interface UserRow {
    id: number;
    username_nip: string;
    nama: string;
    role: Role;
    role_label: string;
    biller_id: number | null;
    biller: string | null;
    is_active: boolean;
    terkunci: boolean;
    gagal_login: number;
    last_login_at: string | null;
}

type BillerOption = Option<number> & { terpakai: boolean };

type Props = PageProps<{
    users: Paginated<UserRow>;
    filters: { q?: string; role?: string };
    roleOptions: Option[];
    jumlah: Partial<Record<Role, number>>;
    billerOptions: BillerOption[];
}>;

const TONE_PERAN: Record<Role, StatusTone> = { super_admin: 'waiting', admin: 'info', biller: 'success' };

/** 03.1 Manajemen Akun. */
export default function ManajemenAkun() {
    const { users, filters, roleOptions, jumlah, billerOptions, auth } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', role: filters.role ?? '' });
    const [form, setForm] = useState<{ open: boolean; user: UserRow | null }>({ open: false, user: null });
    const hapus = useConfirmAction<UserRow>();
    const buka = useConfirmAction<UserRow>();

    const columns: ColumnDef<UserRow>[] = [
        { header: 'USERNAME / NIP', cell: ({ row }) => <span className="font-mono font-bold">{row.original.username_nip}</span> },
        { header: 'NAMA LENGKAP', cell: ({ row }) => row.original.nama },
        { header: 'PERAN BAKU', cell: ({ row }) => <StatusChip label={row.original.role} tone={TONE_PERAN[row.original.role]} /> },
        { header: 'TAUTAN BILLER_ID', cell: ({ row }) => row.original.biller ?? '— (Non-biller)' },
        {
            header: 'STATUS',
            cell: ({ row: { original: u } }) =>
                u.terkunci ? <StatusChip label="Terkunci (5× gagal)" tone="danger" /> : u.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />,
        },
        { header: 'LOGIN TERAKHIR', cell: ({ row }) => <span className="whitespace-nowrap">{row.original.last_login_at ? formatTanggal(row.original.last_login_at, 'd MMM HH:mm') : 'Belum pernah'}</span> },
        {
            id: 'aksi',
            header: 'AKSI',
            cell: ({ row: { original: u } }) => (
                <div className="flex items-center gap-1">
                    <Button variant="secondary" size="sm" onClick={() => setForm({ open: true, user: u })}>
                        Edit
                    </Button>
                    <RowActions
                        label={u.nama}
                        actions={[
                            { label: 'Buka kunci', icon: LockOpen, hidden: !u.terkunci, onSelect: () => buka.ask(u) },
                            { label: 'Hapus', icon: Trash2, danger: true, hidden: u.id === auth.user.id, onSelect: () => hapus.ask(u) },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Manajemen Akun" />
            <PageHeader
                title="Manajemen Akun & Peran (users)"
                description="CRUD akun pengguna Kinerja Biller · Setiap perubahan dicatat otomatis ke Audit Trail"
                actions={<Button onClick={() => setForm({ open: true, user: null })}>+ Tambah Akun Baru</Button>}
            />
            <div className="grid gap-4">
                <Notice tone="waiting" action={<StatusChip label="3 Peran Baku" tone="waiting" className="bg-[#fef08a] text-[#854d0e]" />}>
                    Kebijakan Akses Baku: Hanya terdapat 3 peran tetap (Super Admin · Koordinator Admin · Biller). Tidak ada pengaturan izin per menu.
                </Notice>
                <div className="grid gap-3.5 sm:grid-cols-3">
                    <StatCard label="Super Admin" value={`${jumlah.super_admin ?? 0} Akun`} valueClassName="text-[#a16207]" caption="Kelola master, parameter, tutup periode" />
                    <StatCard label="Koordinator Admin" value={`${jumlah.admin ?? 0} Akun`} valueClassName="text-pln-blue" caption="Unggah data, validasi, pantau absensi" />
                    <StatCard label="Biller Lapangan" value={`${jumlah.biller ?? 0} Akun`} valueClassName="text-success" caption="Terhubung ke tabel master billers" />
                </div>
                <Kartu
                    judul={`Daftar Akun Pengguna Terdaftar (${formatNumber(users.total)} Akun)`}
                    aksi={
                        <>
                            <NativeSelect className="w-40" aria-label="Filter peran" value={f.values.role} onChange={(e) => f.set('role', e.target.value)}>
                                <option value="">Semua peran</option>
                                {roleOptions.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <SearchInput className="w-full sm:w-56" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama atau username" />
                        </>
                    }
                >
                    <DataTable columns={columns} data={users} getRowId={(u) => String(u.id)} empty="Tidak ada pengguna yang cocok dengan filter." minLebar={860} />
                </Kartu>
            </div>

            {form.open && (
                <UserFormDialog key={form.user?.id ?? 'baru'} user={form.user} roleOptions={roleOptions} billerOptions={billerOptions} onClose={() => setForm({ open: false, user: null })} />
            )}

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                chip={{ label: 'AKSI PERMANEN · SUPER ADMIN', tone: 'danger' }}
                garis="merah"
                title="Hapus pengguna?"
                description={
                    <>
                        Akun <strong className="text-ink">{hapus.target?.nama}</strong> tidak bisa dipakai masuk lagi. Data biller dan skornya tetap tersimpan.
                    </>
                }
                confirmLabel="Ya, Hapus Akun"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.users.destroy', hapus.target.id))}
            />
            <ConfirmDialog
                open={buka.open}
                onOpenChange={(v) => !v && buka.close()}
                chip={{ label: 'SUPER ADMIN · UNLOCK_USER', tone: 'info' }}
                garis="biru"
                title="Buka kunci akun?"
                description={`${buka.target?.nama ?? ''} bisa masuk lagi dan hitungan gagal login direset. Aksi ini tercatat sebagai UNLOCK_USER.`}
                confirmLabel="Ya, Buka Kunci"
                processing={buka.processing}
                onConfirm={() => buka.target && buka.run('post', route('pengaturan.users.unlock', buka.target.id))}
            />
        </>
    );
}

/** 04.5a Tambah / Edit Akun Pengguna. */
function UserFormDialog({
    user,
    roleOptions,
    billerOptions,
    onClose,
}: {
    user: UserRow | null;
    roleOptions: Option[];
    billerOptions: BillerOption[];
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        username_nip: user?.username_nip ?? '',
        nama: user?.nama ?? '',
        role: (user?.role ?? 'biller') as Role,
        biller_id: user?.biller_id ? String(user.biller_id) : '',
        password: '',
        is_active: user?.is_active ?? true,
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (user) put(route('pengaturan.users.update', user.id), opts);
        else post(route('pengaturan.users.store'), opts);
    };

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            chip={{ label: 'SUPER ADMIN · TABEL USERS', tone: 'waiting' }}
            title={user ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna'}
            submitLabel="Simpan Akun"
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="username_nip" label="Username / NIP" required error={errors.username_nip}>
                <Input
                    id="username_nip"
                    className="font-mono"
                    autoCapitalize="none"
                    value={data.username_nip}
                    onChange={(e) => setData('username_nip', e.target.value)}
                    aria-invalid={!!errors.username_nip}
                />
            </FormField>
            <FormField id="nama" label="Nama Lengkap" required error={errors.nama}>
                <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} aria-invalid={!!errors.nama} />
            </FormField>
            <FormField id="role" label="Peran Baku (3 Peran Tetap — Tanpa Izin per Menu)" required error={errors.role}>
                <NativeSelect id="role" className="[&>select]:h-[37px] [&>select]:bg-canvas" value={data.role} onChange={(e) => setData('role', e.target.value as Role)}>
                    {roleOptions.map((r) => (
                        <option key={r.value} value={r.value}>
                            {r.label} ({r.value})
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            {data.role === 'biller' && (
                <FormField id="biller_id" label="Tautan Master Biller (Khusus Peran Biller)" required error={errors.biller_id}>
                    <NativeSelect id="biller_id" className="[&>select]:h-[37px] [&>select]:bg-canvas" value={data.biller_id} onChange={(e) => setData('biller_id', e.target.value)} aria-invalid={!!errors.biller_id}>
                        <option value="">Pilih biller</option>
                        {billerOptions.map((b) => (
                            <option key={b.value} value={b.value} disabled={b.terpakai && b.value !== user?.biller_id}>
                                {b.label}
                                {b.terpakai && b.value !== user?.biller_id ? ' (sudah punya akun)' : ''}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            )}
            <FormField
                id="password"
                label={user ? 'Kata Sandi Baru (Kosongkan Bila Tidak Diganti)' : 'Kata Sandi Awal (Min. 8 karakter)'}
                required={!user}
                error={errors.password}
            >
                <PasswordInput id="password" autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} aria-invalid={!!errors.password} />
            </FormField>
            <div className="flex items-center gap-2.5">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                <Label htmlFor="is_active">Akun aktif dan boleh masuk</Label>
            </div>
        </FormDialog>
    );
}

ManajemenAkun.layout = layoutSuperAdmin;

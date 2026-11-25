import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import type { BarisHarian } from '@/components/shared/TabelPantauanHarian';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import { formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { STATUS_ABSENSI } from '@/lib/status';
import type { Option, PageProps, StatusAbsensi } from '@/types';

interface RiwayatKoreksi {
    id: number;
    tanggal: string | null;
    petugas: string | null;
    peran: string | null;
    sebelum: StatusAbsensi | 'belum';
    status: StatusAbsensi;
    alasan: string | null;
    oleh: string | null;
    waktu: string | null;
}

type Props = PageProps<{
    tanggal: string;
    hariIni: string;
    harian: BarisHarian[];
    statusOptions: Option[];
    riwayat: RiwayatKoreksi[];
}>;

/** Form koreksi: petugas, tanggal, status sebelum, status baru, alasan wajib. */
function FormKoreksi({ tanggal, hariIni, harian, statusOptions }: Pick<Props, 'tanggal' | 'hariIni' | 'harian' | 'statusOptions'>) {
    const form = useForm({ user_id: '', tanggal, status: 'hadir' as StatusAbsensi, alasan_diubah: '' });
    const target = harian.find((h) => String(h.user_id) === form.data.user_id);

    const gantiTanggal = (t: string) => {
        form.setData('tanggal', t);
        router.get(route('pengaturan.koreksi-absensi.index'), { tanggal: t }, { preserveScroll: true, preserveState: true, only: ['tanggal', 'harian'] });
    };

    return (
        <Kartu judul="Form Koreksi Catatan Absensi" className="border-[1.5px] border-sky-line">
            <form
                className="grid gap-3.5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('pengaturan.koreksi-absensi.store'), { preserveScroll: true, preserveState: true, onSuccess: () => form.reset('user_id', 'alasan_diubah') });
                }}
            >
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <FormField id="user_id" label="Petugas (Biller / Koordinator)" error={form.errors.user_id}>
                        <NativeSelect id="user_id" className="[&>select]:h-[37px] [&>select]:bg-canvas" value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)}>
                            <option value="">Pilih petugas…</option>
                            {harian.map((h) => (
                                <option key={h.user_id} value={h.user_id}>
                                    {h.nama} ({h.nip})
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField id="tanggal" label="Tanggal Absensi" error={form.errors.tanggal}>
                        <Input id="tanggal" type="date" className="font-semibold" value={form.data.tanggal} max={hariIni} onChange={(e) => e.target.value && gantiTanggal(e.target.value)} />
                    </FormField>
                    <FormField id="sebelum" label="Status Sebelum">
                        <Input id="sebelum" className="font-semibold" disabled value={target ? STATUS_ABSENSI[target.status].label : '—'} readOnly />
                    </FormField>
                    <FormField id="status" label="Status Koreksi Baru" error={form.errors.status}>
                        <NativeSelect id="status" className="[&>select]:h-[37px] [&>select]:bg-canvas" value={form.data.status} onChange={(e) => form.setData('status', e.target.value as StatusAbsensi)}>
                            {statusOptions.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                </div>
                <FormField id="alasan_diubah" label="Alasan Koreksi (WAJIB · disimpan ke attendances.alasan_diubah & audit_logs)" error={form.errors.alasan_diubah}>
                    <Input
                        id="alasan_diubah"
                        className="font-semibold placeholder:font-medium"
                        placeholder="Contoh: Kendala sinyal seluler, dikonfirmasi Koordinator Admin"
                        maxLength={300}
                        value={form.data.alasan_diubah}
                        onChange={(e) => form.setData('alasan_diubah', e.target.value)}
                    />
                </FormField>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-[11px] text-muted-ink">Koreksi absensi hanya untuk kedisiplinan dan tidak mengubah skor kinerja bulanan.</p>
                    <Button type="submit" disabled={form.processing || !target || form.data.alasan_diubah.trim().length < 5}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        Simpan Koreksi Absensi ✓
                    </Button>
                </div>
            </form>
        </Kartu>
    );
}

/** 03.5 Koreksi Absensi (Super Admin). */
export default function KoreksiAbsensi() {
    const { tanggal, hariIni, harian, statusOptions, riwayat } = usePage<Props>().props;

    const columns: ColumnDef<RiwayatKoreksi>[] = [
        { header: 'TANGGAL', cell: ({ row }) => <span className="whitespace-nowrap">{formatTanggal(row.original.tanggal, 'd MMM yyyy')}</span> },
        { header: 'PETUGAS', cell: ({ row: { original: r } }) => `${r.petugas ?? '–'}${r.peran ? ` (${r.peran})` : ''}` },
        {
            header: 'PERUBAHAN STATUS',
            cell: ({ row: { original: r } }) => <StatusChip label={`${STATUS_ABSENSI[r.sebelum].label} → ${STATUS_ABSENSI[r.status].label}`} tone={STATUS_ABSENSI[r.status].tone} />,
        },
        { header: 'ALASAN KOREKSI (WAJIB)', cell: ({ row }) => row.original.alasan },
        { header: 'DIUBAH OLEH', cell: ({ row: { original: r } }) => <span title={formatTanggalWaktu(r.waktu)}>{r.oleh}</span> },
    ];

    return (
        <>
            <Head title="Koreksi Absensi" />
            <PageHeader
                title="Koreksi Absensi (Biller & Koordinator)"
                description="Perbaiki catatan absensi yang keliru dengan alasan wajib · Ikut tampil di pantauan Koordinator & Audit Trail"
                actions={<StatusChip titik label="Alasan Koreksi Wajib Diisi" tone="waiting" />}
            />
            <div className="grid gap-4">
                <FormKoreksi tanggal={tanggal} hariIni={hariIni} harian={harian} statusOptions={statusOptions} />
                <Kartu judul="Riwayat Koreksi Absensi">
                    <DataTable columns={columns} data={riwayat} getRowId={(r) => String(r.id)} empty="Belum ada koreksi." />
                </Kartu>
            </div>
        </>
    );
}

KoreksiAbsensi.layout = layoutSuperAdmin;

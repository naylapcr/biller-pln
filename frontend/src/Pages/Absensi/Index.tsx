import { router, useForm, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { STATUS_HARI, type StatusHari } from '@/lib/absensi';
import { formatNumber, formatTanggal } from '@/lib/format';
import type { Option, PageProps, PeriodeInfo, RekapAbsensi } from '@/types';

interface Baris {
    id: number;
    nama: string;
    login_acmt: string;
    kode_petugas: string;
    rekap: RekapAbsensi | null;
    tanggal: {
        status: StatusHari | null;
        tercatat: boolean;
        keterangan: string | null;
        sumber: string | null;
        pencatat: string | null;
        waktu: string | null;
    };
}

type Props = PageProps<{
    periode: (PeriodeInfo & { absensi_mulai: string | null; absensi_selesai: string | null }) | null;
    periodeOptions: Option[];
    tanggal: string;
    hariIni: string;
    tanggalHariKerja: boolean;
    dendaPerAlpa: number;
    statusOptions: Option[];
    billers: Baris[];
}>;

/** Rekap absensi semua biller per periode, status satu tanggal, dan koreksi oleh Koordinator Admin. */
export default function AbsensiIndex() {
    const { periode, periodeOptions, tanggal, hariIni, tanggalHariKerja, dendaPerAlpa, statusOptions, billers } = usePage<Props>().props;
    const [ubah, setUbah] = useState<Baris | null>(null);

    const pindahTanggal = (t: string) => router.get(route('absensi.index'), { periode: periode?.kode, tanggal: t }, { preserveScroll: true, preserveState: true });
    const belumAbsen = billers.filter((b) => !b.tanggal.tercatat).length;

    const columns: ColumnDef<Baris>[] = [
        {
            header: 'Biller',
            cell: ({ row: { original: b } }) => (
                <div>
                    <p className="font-medium text-ink">{b.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">
                        {b.login_acmt} · {b.kode_petugas}
                    </p>
                </div>
            ),
        },
        {
            header: formatTanggal(tanggal, 'd MMM yyyy'),
            cell: ({ row: { original: b } }) => {
                const s = b.tanggal.status ?? (tanggal === hariIni ? 'belum' : 'libur');
                return (
                    <div>
                        <StatusChip {...STATUS_HARI[s]} />
                        {b.tanggal.keterangan && <p className="mt-1 text-xs text-muted-ink">{b.tanggal.keterangan}</p>}
                        {b.tanggal.tercatat && (
                            <p className="mt-0.5 text-xs text-muted-ink">
                                {b.tanggal.sumber === 'biller' ? 'diisi sendiri' : `oleh ${b.tanggal.pencatat ?? 'admin'}`}
                                {b.tanggal.waktu && `, ${formatTanggal(b.tanggal.waktu, 'HH.mm')}`}
                            </p>
                        )}
                        {!b.tanggal.tercatat && s === 'alpa' && <p className="mt-0.5 text-xs text-muted-ink">tidak absen</p>}
                    </div>
                );
            },
        },
        ...(['hadir', 'izin', 'sakit', 'alpa'] as const).map<ColumnDef<Baris>>((k) => ({
            id: k,
            header: STATUS_HARI[k].label,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <span className={`font-mono text-[13px] ${k === 'alpa' && (row.original.rekap?.alpa ?? 0) > 0 ? 'text-error-ink' : ''}`}>
                    {formatNumber(row.original.rekap?.[k] ?? null)}
                </span>
            ),
        })),
        {
            header: 'Denda',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono text-[13px] text-error-ink">{formatNumber((row.original.rekap?.alpa ?? 0) * dendaPerAlpa, 1)}</span>,
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <Button variant="secondary" size="sm" onClick={() => setUbah(row.original)} disabled={!periode?.terbuka || tanggal > hariIni}>
                    <Pencil />
                    Ubah
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Absensi biller">
            <PageHeader
                title="Absensi biller"
                description={
                    periode
                        ? `Biller mengisi absensi sendiri. Rentang yang dinilai untuk BLTH ${periode.kode}: ${formatTanggal(periode.absensi_mulai, 'd MMM')} – ${formatTanggal(periode.absensi_selesai, 'd MMM yyyy')}. Denda ${formatNumber(dendaPerAlpa, 1)} poin per hari alpa.`
                        : 'Belum ada periode.'
                }
            />

            <Toolbar>
                <PeriodeSelect value={periode?.kode} options={periodeOptions} />
                <Input type="date" className="md:w-44" aria-label="Tanggal" value={tanggal} max={hariIni} onChange={(e) => e.target.value && pindahTanggal(e.target.value)} />
                {tanggal !== hariIni && (
                    <Button variant="secondary" onClick={() => pindahTanggal(hariIni)}>
                        Hari ini
                    </Button>
                )}
            </Toolbar>

            {tanggal === hariIni && belumAbsen > 0 && (
                <Notice tone="info" className="mb-4">
                    {formatNumber(belumAbsen)} biller belum absen hari ini. Mereka baru dihitung alpa setelah hari ini berakhir.
                </Notice>
            )}
            {!tanggalHariKerja && <p className="mb-3 text-[13px] text-muted-ink">Tanggal ini bukan hari kerja atau di luar rentang absensi periode.</p>}

            <DataTable columns={columns} data={billers} getRowId={(b) => String(b.id)} empty="Belum ada biller aktif." />

            {ubah && <DialogUbah key={ubah.id} baris={ubah} tanggal={tanggal} statusOptions={statusOptions} onClose={() => setUbah(null)} />}
        </AppLayout>
    );
}

function DialogUbah({ baris, tanggal, statusOptions, onClose }: { baris: Baris; tanggal: string; statusOptions: Option[]; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        tanggal,
        status: baris.tanggal.tercatat ? (baris.tanggal.status ?? '') : '',
        keterangan: baris.tanggal.keterangan ?? '',
    });

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            title={`Absensi ${baris.nama}`}
            description={`Tanggal ${formatTanggal(tanggal, 'EEEE, d MMMM yyyy')}. Perubahan tercatat di audit trail dan skor dihitung ulang.`}
            submitLabel="Simpan"
            processing={processing}
            onSubmit={() => put(route('absensi.update', baris.id), { preserveScroll: true, onSuccess: onClose })}
        >
            <FormField id="status" label="Status" error={errors.status ?? errors.tanggal}>
                <NativeSelect id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                    <option value="">Belum absen (hapus catatan)</option>
                    {statusOptions.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField id="keterangan" label="Keterangan" error={errors.keterangan}>
                <Textarea id="keterangan" rows={2} value={data.keterangan} onChange={(e) => setData('keterangan', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}

import { useForm, usePage } from '@inertiajs/react';
import { CalendarCheck, LoaderCircle } from 'lucide-react';

import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { KELAS_HARI, STATUS_HARI, type StatusHari } from '@/lib/absensi';
import { formatNumber, formatTanggal } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option, PageProps, PeriodeInfo, RekapAbsensi } from '@/types';

type Props = PageProps<{
    hariIni: {
        tanggal: string;
        hari_kerja: boolean;
        absen: { status: StatusHari; keterangan: string | null; sumber: string; waktu: string | null } | null;
        periode: string | null;
    };
    periode: (PeriodeInfo & { absensi_mulai: string | null; absensi_selesai: string | null }) | null;
    periodeOptions: Option[];
    harian: { tanggal: string; status: StatusHari; keterangan: string | null; sumber: string | null }[];
    rekap: RekapAbsensi | null;
    dendaPerAlpa: number;
}>;

const PILIHAN: { value: 'hadir' | 'izin' | 'sakit'; label: string; ket: string }[] = [
    { value: 'hadir', label: 'Hadir', ket: 'Saya bekerja hari ini' },
    { value: 'izin', label: 'Izin', ket: 'Tidak bekerja dengan izin' },
    { value: 'sakit', label: 'Sakit', ket: 'Tidak bekerja karena sakit' },
];

/** Biller mengisi absensi hari ini dan melihat kalender kehadirannya. */
export default function SayaAbsensi() {
    const { hariIni, periode, periodeOptions, harian, rekap, dendaPerAlpa } = usePage<Props>().props;
    const absen = hariIni.absen;
    const dikunciAdmin = absen !== null && absen.sumber !== 'biller';
    const form = useForm({ status: absen?.status === 'alpa' ? 'hadir' : (absen?.status ?? 'hadir'), keterangan: absen?.keterangan ?? '' });

    const kirim = () => form.post(route('saya.absensi.store'), { preserveScroll: true });

    return (
        <AppLayout title="Absensi">
            <PageHeader
                title="Absensi"
                description="Isi absensi setiap hari kerja. Hari kerja yang terlewat tanpa absensi dihitung alpa."
                actions={<PeriodeSelect value={periode?.kode} options={periodeOptions} />}
            />

            <div className="grid gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">
                <section className="self-start rounded-14 border border-border p-5">
                    <p className="text-[13px] text-muted-ink">Hari ini</p>
                    <h2 className="text-section-title text-ink">{formatTanggal(hariIni.tanggal, 'EEEE, d MMMM yyyy')}</h2>

                    {absen && (
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-[13px]">
                            <StatusChip {...STATUS_HARI[absen.status]} />
                            <span className="text-muted-ink">
                                {absen.sumber === 'biller' ? 'diisi sendiri' : 'diisi Koordinator Admin'}
                                {absen.waktu && `, ${formatTanggal(absen.waktu, 'HH.mm')} WIB`}
                            </span>
                        </div>
                    )}

                    {!hariIni.hari_kerja && (
                        <Notice tone="info" className="mt-4">
                            Hari ini bukan hari kerja, jadi tidak absen tidak dihitung alpa. Anda tetap boleh mengisi bila bekerja.
                        </Notice>
                    )}
                    {!hariIni.periode && (
                        <Notice tone="waiting" className="mt-4">
                            Tanggal ini belum masuk rentang absensi periode terbuka mana pun. Absensi tetap tersimpan.
                        </Notice>
                    )}

                    {dikunciAdmin ? (
                        <p className="mt-4 text-sm text-muted-ink">Absensi hari ini sudah diisi Koordinator Admin. Hubungi koordinator bila perlu diubah.</p>
                    ) : (
                        <form
                            className="mt-4 grid gap-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                kirim();
                            }}
                        >
                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium text-ink">Status</legend>
                                {PILIHAN.map((p) => (
                                    <label
                                        key={p.value}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-3 rounded-10 border px-3 py-2.5 text-sm',
                                            form.data.status === p.value ? 'border-pln-blue bg-pln-blue-light' : 'border-border hover:bg-table-head',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="status"
                                            value={p.value}
                                            checked={form.data.status === p.value}
                                            onChange={() => form.setData('status', p.value)}
                                            className="accent-pln-blue"
                                        />
                                        <span>
                                            <span className="block font-medium">{p.label}</span>
                                            <span className="block text-xs text-muted-ink">{p.ket}</span>
                                        </span>
                                    </label>
                                ))}
                            </fieldset>
                            {form.data.status !== 'hadir' && (
                                <FormField id="keterangan" label="Keterangan" required error={form.errors.keterangan}>
                                    <Textarea
                                        id="keterangan"
                                        rows={2}
                                        value={form.data.keterangan}
                                        onChange={(e) => form.setData('keterangan', e.target.value)}
                                        placeholder="Contoh: kontrol ke dokter"
                                    />
                                </FormField>
                            )}
                            {form.errors.status && <p className="text-[13px] text-error-ink">{form.errors.status}</p>}
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <LoaderCircle className="animate-spin" /> : <CalendarCheck />}
                                {absen ? 'Perbarui absensi hari ini' : 'Absen hari ini'}
                            </Button>
                        </form>
                    )}
                </section>

                <section className="min-w-0">
                    {rekap && (
                        <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {(['hadir', 'izin', 'sakit', 'alpa'] as const).map((s) => (
                                <div key={s} className="rounded-14 border border-border p-4">
                                    <p className="text-[13px] text-muted-ink">{STATUS_HARI[s].label}</p>
                                    <p className={cn('mt-1 font-mono text-xl font-semibold', s === 'alpa' && rekap.alpa > 0 && 'text-error-ink')}>
                                        {formatNumber(rekap[s])}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                    {rekap && rekap.alpa > 0 && (
                        <p className="mb-4 text-[13px] text-muted-ink">
                            {dendaPerAlpa > 0
                                ? `Denda alpa: ${formatNumber(rekap.alpa)} hari × ${formatNumber(dendaPerAlpa, 1)} poin = −${formatNumber(rekap.alpa * dendaPerAlpa, 1)} poin.`
                                : 'Denda alpa periode ini 0 poin per hari, jadi alpa belum mengurangi skor.'}
                        </p>
                    )}

                    <h2 className="mb-2 text-sm font-semibold text-ink">
                        Kalender {periode ? `${formatTanggal(periode.absensi_mulai, 'd MMM')} – ${formatTanggal(periode.absensi_selesai, 'd MMM yyyy')}` : ''}
                    </h2>
                    {harian.length === 0 ? (
                        <p className="text-sm text-muted-ink">Belum ada periode.</p>
                    ) : (
                        <ol className="grid grid-cols-4 gap-2 sm:grid-cols-7">
                            {harian.map((h) => (
                                <li
                                    key={h.tanggal}
                                    title={[STATUS_HARI[h.status].label, h.keterangan].filter(Boolean).join(': ')}
                                    className={cn('rounded-10 px-2 py-2 text-center', KELAS_HARI[h.status], h.tanggal === hariIni.tanggal && 'ring-2 ring-pln-blue')}
                                >
                                    <span className="block text-xs">{formatTanggal(h.tanggal, 'EEE')}</span>
                                    <span className="block font-mono text-base font-semibold">{formatTanggal(h.tanggal, 'd')}</span>
                                    <span className="block truncate text-[11px]">{STATUS_HARI[h.status].label}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

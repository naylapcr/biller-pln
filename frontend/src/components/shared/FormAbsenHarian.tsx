import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { BarisAbsensi } from '@/types';

type Pilihan = 'hadir' | 'izin' | 'sakit';

const KETERANGAN: Record<Pilihan, string> = { hadir: 'Bertugas lapangan', izin: 'Wajib keterangan', sakit: 'Wajib keterangan' };

export interface HariIniAbsensi {
    tanggal: string;
    jam_server: string;
    hari_kerja: boolean;
    absen: BarisAbsensi | null;
}

/** Chip tanggal dan jam berjalan di header; jam dimulai dari jam server. */
export function ChipJamServer({ hariIni }: { hariIni: HariIniAbsensi }) {
    const [jam, setJam] = useState(() => new Date(hariIni.jam_server));

    useEffect(() => {
        const selisih = new Date(hariIni.jam_server).getTime() - Date.now();
        const t = setInterval(() => setJam(new Date(Date.now() + selisih)), 1000);
        return () => clearInterval(t);
    }, [hariIni.jam_server]);

    return <StatusChip titik tone="sky" label={`${formatTanggal(jam, 'EEEE, d MMM yyyy')} · ${formatTanggal(jam, 'HH:mm:ss')} WIB`} />;
}

/** Banner kuning: absensi tidak memengaruhi skor. */
export function CatatanAbsensi() {
    return (
        <Notice tone="waiting" action={<StatusChip label="1× / Hari" tone="waiting" className="bg-[#fef08a] text-[#854d0e]" />}>
            Absensi hanya untuk kedisiplinan dan TIDAK memengaruhi skor kinerja. Tidak absen sampai pukul 23:59 WIB otomatis tercatat Alpa.
        </Notice>
    );
}

/** 02.6: kartu hijau setelah absen. */
export function KartuTercatat({ absen }: { absen: BarisAbsensi }) {
    return (
        <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-[1.5px] border-success-line bg-success-row px-[22px] py-5">
            <div className="min-w-0">
                <StatusChip titik tone="success" label={`TERCATAT ${STATUS_ABSENSI[absen.status].label.toUpperCase()} HARI INI`} />
                <p className="mt-1.5 text-[17px] leading-[22px] font-extrabold text-[#065f46]">
                    {absen.sumber === 'mandiri' ? 'Anda sudah melakukan absensi' : 'Absensi dicatat sistem'}
                    {absen.absen_at && ` pada ${formatTanggal(absen.absen_at, 'HH:mm:ss')} WIB`}
                </p>
                <p className="mt-1 max-w-[520px] text-xs leading-[15px] text-success-dark">
                    Sesuai aturan sistem (1× sehari), percobaan absen kedua ditolak; koreksi hanya lewat Super Admin.
                    {absen.lat !== null && absen.lng !== null && ` Lokasi: ${absen.lat.toFixed(4)}, ${absen.lng.toFixed(4)}.`}
                    {absen.keterangan && ` Keterangan: ${absen.keterangan}.`}
                </p>
            </div>
            <span className="rounded-10 bg-[#d1fae5] px-4 py-2.5 text-xs font-bold text-success-dark">Absen Terkunci ✓</span>
        </section>
    );
}

/**
 * Form absen harian (01.2 dan 02.6): pilihan Hadir/Izin/Sakit, keterangan wajib untuk izin dan
 * sakit, dan lokasi browser opsional untuk hadir.
 */
export default function FormAbsenHarian({ hariIni, url }: { hariIni: HariIniAbsensi; url: string }) {
    const form = useForm<{ status: Pilihan; keterangan: string; lat: number | null; lng: number | null }>({
        status: 'hadir',
        keterangan: '',
        lat: null,
        lng: null,
    });
    const [lokasi, setLokasi] = useState<'meminta' | 'ada' | 'ditolak'>('meminta');

    useEffect(() => {
        if (!('geolocation' in navigator)) {
            setLokasi('ditolak');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (p) => {
                form.setData((d) => ({ ...d, lat: Number(p.coords.latitude.toFixed(7)), lng: Number(p.coords.longitude.toFixed(7)) }));
                setLokasi('ada');
            },
            () => setLokasi('ditolak'),
            { enableHighAccuracy: true, timeout: 10000 },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const status = form.data.status;

    return (
        <Kartu judul="Form Absensi Hari Ini" className="p-[22px]">
            <p className="text-xs text-muted-ink">Pilih status kehadiran Anda hari ini. Status Izin atau Sakit wajib mengisi keterangan.</p>
            {!hariIni.hari_kerja && <p className="text-xs font-semibold text-pln-yellow-ink">Hari ini bukan hari kerja. Anda tetap bisa absen bila sedang bertugas.</p>}
            <form
                className="grid gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(url, { preserveScroll: true });
                }}
                noValidate
            >
                <div>
                    <div role="radiogroup" aria-label="Status hari ini" className="grid grid-cols-3 gap-2 sm:gap-3">
                        {(['hadir', 'izin', 'sakit'] as Pilihan[]).map((s) => (
                            <button
                                key={s}
                                type="button"
                                role="radio"
                                aria-checked={status === s}
                                onClick={() => form.setData('status', s)}
                                className={cn(
                                    'min-h-[66px] rounded-xl px-3 py-3 text-left transition-colors sm:px-4',
                                    status === s ? 'bg-success-row ring-2 ring-success-line ring-inset' : 'bg-white ring-1 ring-line-strong ring-inset hover:bg-canvas',
                                )}
                            >
                                <span className={cn('block text-sm font-bold', status === s ? 'text-success' : 'text-slate-ink')}>{STATUS_ABSENSI[s].label}</span>
                                <span className="mt-0.5 block text-[11px] text-muted-ink">{KETERANGAN[s]}</span>
                            </button>
                        ))}
                    </div>
                    {form.errors.status && <p className="mt-2 text-xs text-error-ink">{form.errors.status}</p>}
                </div>

                <FormField id="keterangan" label="Keterangan (Wajib untuk Izin / Sakit · Opsional untuk Hadir)" error={form.errors.keterangan}>
                    <Textarea
                        id="keterangan"
                        rows={1}
                        placeholder="Contoh: Bertugas di Sektor Sukamaju / Surat dokter terlampir..."
                        value={form.data.keterangan}
                        onChange={(e) => form.setData('keterangan', e.target.value)}
                        maxLength={300}
                    />
                </FormField>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className={cn('text-[11px] font-medium', lokasi === 'ada' ? 'text-success' : 'text-muted-ink')}>
                        {lokasi === 'ada' && `Lokasi browser (opsional): ${form.data.lat?.toFixed(4)}, ${form.data.lng?.toFixed(4)} · Diizinkan`}
                        {lokasi === 'meminta' && 'Lokasi browser (opsional): meminta izin…'}
                        {lokasi === 'ditolak' && 'Lokasi browser (opsional): tidak dibagikan'}
                    </p>
                    <Button type="submit" variant="success" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        Kirim Absensi {STATUS_ABSENSI[status].label}
                    </Button>
                </div>
            </form>
        </Kartu>
    );
}

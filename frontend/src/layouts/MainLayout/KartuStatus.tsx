import { usePage } from '@inertiajs/react';

import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

/**
 * Kartu status sidebar (PRD 10.1): BLTH aktif dengan label Terbuka/Ditutup, lalu satu baris info
 * khas peran. Biller: absensi hari ini · wilayah. Koordinator: absensi Anda · biller aktif.
 * Super Admin: "Super Admin tidak wajib absen".
 */
export default function KartuStatus() {
    const { auth, kartuStatus } = usePage<PageProps>().props;
    if (!kartuStatus) return null;
    const { periode, absensi, wilayah, biller_aktif } = kartuStatus;
    const role = auth.user.role;

    return (
        <div className="flex flex-col gap-2 rounded-14 bg-white/90 p-3.5">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-bold text-ink">BLTH {periode?.kode ?? '–'}</p>
                {periode && <StatusChip label={periode.terbuka ? 'Terbuka' : 'Ditutup'} tone={periode.terbuka ? 'success' : 'neutral'} />}
            </div>
            {role === 'super_admin' ? (
                <p className="text-[11px] text-muted-ink">Super Admin tidak wajib absen</p>
            ) : (
                <div className="grid gap-1.5 text-[11px] text-muted-ink">
                    {absensi && (
                        <p className={cn('font-medium', role === 'admin' && absensi.status === 'hadir' ? 'text-success' : 'text-slate-ink')}>
                            {role === 'biller' ? 'Absensi hari ini' : 'Absensi Anda'}: {STATUS_ABSENSI[absensi.status].label}
                            {absensi.jam && ` (${formatTanggal(absensi.jam, 'HH:mm')} WIB)`}
                        </p>
                    )}
                    {role === 'biller' && <p>Wilayah: {wilayah ?? 'belum diisi'}</p>}
                    {role === 'admin' && biller_aktif !== null && <p>{formatNumber(biller_aktif)} Biller Aktif · Supabase Sync</p>}
                </div>
            )}
        </div>
    );
}

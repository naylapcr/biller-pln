import { Head, Link, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { useState } from 'react';

import DistribusiKategori from '@/components/shared/DistribusiKategori';
import PeriodeSelect from '@/components/shared/PeriodeSelect';
import { ChipSelisih } from '@/components/shared/TrenSkor';
import KartuKesiapanTutup, { persenSiap, type SyaratTutup } from '@/components/superadmin/KartuKesiapanTutup';
import ModalTutupPeriode from '@/components/superadmin/ModalTutupPeriode';
import DonutChart from '@/components/ui/DonutChart';
import EmptyState from '@/components/ui/EmptyState';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import TrendLine from '@/components/ui/TrendLine';
import { Button } from '@/components/ui/button';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import { toneAksi } from '@/lib/status';
import type { Option, PageProps, PeriodeInfo } from '@/types';

interface Sistem {
    akun_aktif: { super_admin: number; admin: number; biller: number };
    akun_terkunci: number;
    master_biller: number;
    jumlah_alias: number;
    belum_terpetakan: number;
    belum_baris: number;
    periode: { kode: string; status: 'terbuka' | 'ditutup'; batch_committed: string[] };
    distribusi_kategori: Record<string, number>;
    kesiapan_tutup: SyaratTutup[];
    tren_rata: { periode: string; label: string; nilai: number | null }[];
    audit_terbaru: { id: number; waktu: string; user: string; aksi: string; objek: string }[];
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    sistem: Sistem | null;
}>;

/** Jam untuk hari ini, tanggal untuk hari lain. */
const waktu = (w: string) => (formatTanggal(w, 'yyyyMMdd') === formatTanggal(new Date(), 'yyyyMMdd') ? formatTanggal(w, 'HH:mm') : formatTanggal(w, 'd MMM'));

/** 03.0 Dashboard Sistem (Super Admin). */
export default function DashboardSistem() {
    const { periode, periodeOptions, sistem } = usePage<Props>().props;
    const [tutup, setTutup] = useState(false);
    const akun = sistem?.akun_aktif;
    const totalAkun = akun ? akun.super_admin + akun.admin + akun.biller : 0;

    return (
        <>
            <Head title="Dashboard Sistem" />
            <PageHeader
                title="Dashboard Sistem — Super Admin"
                description={`Ringkasan kesehatan data, komposisi 3 peran baku, dan kesiapan penutupan periode BLTH ${periode?.kode ?? '–'}`}
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} awalan="" className="w-52" />
                        <Button variant="secondary" asChild>
                            <Link href={route('pengaturan.periode.index', { periode: periode?.kode })}>Parameter Skor</Link>
                        </Button>
                        {periode?.terbuka && sistem && (
                            <Button variant="destructive" onClick={() => setTutup(true)}>
                                Tutup Periode
                            </Button>
                        )}
                    </>
                }
            />

            {!sistem || !akun ? (
                <EmptyState
                    icon={Calendar}
                    title="Belum ada periode"
                    action={
                        <Button asChild>
                            <Link href={route('pengaturan.periode.index')}>Buka Periode</Link>
                        </Button>
                    }
                >
                    Buka periode BLTH pertama di Periode & Parameter.
                </EmptyState>
            ) : (
                <div className="grid gap-4">
                    <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Total Akun Aktif"
                            value={`${formatNumber(totalAkun)} Akun`}
                            caption={`${akun.biller} Biller · ${akun.admin} Koord · ${akun.super_admin} SA${sistem.akun_terkunci > 0 ? ` · ${sistem.akun_terkunci} terkunci` : ''}`}
                        />
                        <StatCard
                            label="Master Biller & Alias"
                            value={`${sistem.master_biller} · ${sistem.jumlah_alias}`}
                            valueClassName="text-pln-blue"
                            caption={`${sistem.master_biller} Biller · ${sistem.jumlah_alias} Alias ACMT`}
                        />
                        <StatCard
                            label="Login Belum Terpetakan"
                            value={`${formatNumber(sistem.belum_terpetakan)} Login`}
                            valueClassName={sistem.belum_terpetakan > 0 ? 'text-amber' : 'text-success'}
                            caption={
                                sistem.belum_terpetakan > 0 ? (
                                    <Link href={route('pengaturan.pemetaan.index')} className="hover:text-pln-blue hover:underline">
                                        {formatNumber(sistem.belum_baris)} baris data menunggu alias
                                    </Link>
                                ) : (
                                    'Semua login terpetakan'
                                )
                            }
                        />
                        <StatCard
                            label={`Status Periode ${sistem.periode.kode}`}
                            value={sistem.periode.status === 'terbuka' ? 'Terbuka' : 'Ditutup'}
                            valueClassName={sistem.periode.status === 'terbuka' ? 'text-success' : 'text-slate-mid'}
                            caption={`${sistem.periode.batch_committed.length} Batch Committed · ${sistem.periode.status === 'terbuka' ? `Siap ${persenSiap(sistem.kesiapan_tutup)}%` : 'Final'}`}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <Kartu judul={`Kategori ${Object.values(sistem.distribusi_kategori).reduce((a, b) => a + b, 0) || 25} Biller`} className="p-4">
                            <DistribusiKategori data={sistem.distribusi_kategori} bawah />
                        </Kartu>
                        <Kartu judul="Komposisi 3 Peran Baku" className="p-4">
                            <DonutChart
                                bawah
                                tengah={formatNumber(totalAkun)}
                                keterangan="Akun Aktif"
                                data={[
                                    { label: 'Biller', nilai: akun.biller, warna: 'stroke-success', titik: 'bg-success' },
                                    { label: 'Koordinator', nilai: akun.admin, warna: 'stroke-pln-blue', titik: 'bg-pln-blue' },
                                    { label: 'Super Admin', nilai: akun.super_admin, warna: 'stroke-amber', titik: 'bg-amber' },
                                ]}
                            />
                        </Kartu>
                        <Kartu judul="Kesiapan Tutup Periode" className="p-4">
                            <KartuKesiapanTutup syarat={sistem.kesiapan_tutup} />
                        </Kartu>
                    </div>

                    <div className="grid items-start gap-4 xl:grid-cols-2">
                        <Kartu judul="Tren Rata-Rata Skor 25 Biller (4 BLTH)" aksi={<ChipSelisih data={sistem.tren_rata} />}>
                            <TrendLine data={sistem.tren_rata.map((t) => ({ kode: t.periode, label: t.label.split(' ')[0], nilai: t.nilai }))} tinggi={150} warna="sky" />
                        </Kartu>
                        <Kartu
                            judul="Aktivitas Audit Trail Terbaru"
                            aksi={
                                <Button variant="secondary" asChild>
                                    <Link href={route('pengaturan.audit.index')}>Lihat Semua →</Link>
                                </Button>
                            }
                        >
                            <ul className="grid gap-2.5">
                                {sistem.audit_terbaru.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between gap-3 rounded-10 bg-canvas px-3 py-2">
                                        <div className="min-w-0">
                                            <StatusChip label={a.aksi} tone={toneAksi(a.aksi)} />
                                            <p className="mt-1 truncate text-[11px] font-medium text-slate-ink">
                                                {a.user}
                                                {a.objek && ` · ${a.objek}`}
                                            </p>
                                        </div>
                                        <span className="shrink-0 font-mono text-[10px] text-muted-ink">{waktu(a.waktu)}</span>
                                    </li>
                                ))}
                                {sistem.audit_terbaru.length === 0 && <li className="text-xs text-muted-ink">Belum ada aktivitas.</li>}
                            </ul>
                        </Kartu>
                    </div>
                    <ModalTutupPeriode open={tutup} onOpenChange={setTutup} kode={sistem.periode.kode} syarat={sistem.kesiapan_tutup} />
                </div>
            )}
        </>
    );
}

DashboardSistem.layout = layoutSuperAdmin;

import { Head, usePage } from '@inertiajs/react';

import FormAbsenHarian, { CatatanAbsensi, ChipJamServer, KartuTercatat, type HariIniAbsensi } from '@/components/shared/FormAbsenHarian';
import RiwayatAbsensi, { RingkasanBulan } from '@/components/shared/RiwayatAbsensi';
import PageHeader from '@/components/ui/PageHeader';
import { layoutBiller } from '@/layouts/BillerLayout/BillerLayout';
import type { BarisAbsensi, PageProps, RingkasanAbsensi } from '@/types';

type Props = PageProps<{
    hariIni: HariIniAbsensi;
    bulan: string;
    ringkasan: RingkasanAbsensi;
    riwayat: BarisAbsensi[];
}>;

/** 01.2 Absensi Saya (Biller). */
export default function AbsensiSaya() {
    const { hariIni, bulan, ringkasan, riwayat } = usePage<Props>().props;

    return (
        <>
            <Head title="Absensi Saya" />
            <PageHeader
                title="Absensi Mandiri Harian"
                description="Catat kehadiran 1× per hari · Jam otomatis dari server WIB · Tidak ada absen pulang"
                actions={<ChipJamServer hariIni={hariIni} />}
            />
            <div className="grid gap-[18px]">
                <CatatanAbsensi />
                <div className="grid items-start gap-[18px] xl:grid-cols-[1fr_310px]">
                    {hariIni.absen ? <KartuTercatat absen={hariIni.absen} /> : <FormAbsenHarian hariIni={hariIni} url={route('saya.absensi.store')} />}
                    <RingkasanBulan bulan={bulan} ringkasan={ringkasan} />
                </div>
                <RiwayatAbsensi bulan={bulan} riwayat={riwayat} />
            </div>
        </>
    );
}

AbsensiSaya.layout = layoutBiller;

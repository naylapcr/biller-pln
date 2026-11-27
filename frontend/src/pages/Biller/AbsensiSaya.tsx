import { Head, usePage } from '@inertiajs/react';

import FormAbsenHarian, { CatatanAbsensi, ChipJamServer, KartuTercatat, type AturanAbsensi, type HariIniAbsensi } from '@/components/shared/FormAbsenHarian';
import RiwayatAbsensi, { RingkasanBulan } from '@/components/shared/RiwayatAbsensi';
import PageHeader from '@/components/ui/PageHeader';
import { layoutBiller } from '@/layouts/BillerLayout/BillerLayout';
import type { BarisAbsensi, PageProps, RingkasanAbsensi } from '@/types';

type Props = PageProps<{
    hariIni: HariIniAbsensi;
    bulan: string;
    ringkasan: RingkasanAbsensi;
    riwayat: BarisAbsensi[];
    aturanAbsensi: AturanAbsensi;
}>;

/** 01.2 Absensi Saya (Biller). */
export default function AbsensiSaya() {
    const { hariIni, bulan, ringkasan, riwayat, aturanAbsensi } = usePage<Props>().props;

    return (
        <>
            <Head title="Absensi Saya" />
            <PageHeader
                title="Absensi Mandiri Harian"
                description="Absen masuk dan pulang dari kantor · Jam otomatis dari server WIB · Kehadiran ikut skor kinerja"
                actions={<ChipJamServer hariIni={hariIni} />}
            />
            <div className="grid gap-[18px]">
                <CatatanAbsensi aturan={aturanAbsensi} />
                <div className="grid items-start gap-[18px] xl:grid-cols-[1fr_310px]">
                    {hariIni.absen ? <KartuTercatat absen={hariIni.absen} aturan={aturanAbsensi} urlPulang={route('saya.absensi.pulang')} /> : <FormAbsenHarian hariIni={hariIni} url={route('saya.absensi.store')} aturan={aturanAbsensi} />}
                    <RingkasanBulan bulan={bulan} ringkasan={ringkasan} />
                </div>
                <RiwayatAbsensi bulan={bulan} riwayat={riwayat} />
            </div>
        </>
    );
}

AbsensiSaya.layout = layoutBiller;

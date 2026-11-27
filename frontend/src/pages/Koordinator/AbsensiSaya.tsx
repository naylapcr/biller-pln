import { Head, usePage } from '@inertiajs/react';

import FormAbsenHarian, { CatatanAbsensi, ChipJamServer, KartuTercatat, type AturanAbsensi, type HariIniAbsensi } from '@/components/shared/FormAbsenHarian';
import RiwayatAbsensi, { RingkasanBulan } from '@/components/shared/RiwayatAbsensi';
import PageHeader from '@/components/ui/PageHeader';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import type { BarisAbsensi, PageProps, RingkasanAbsensi } from '@/types';

type Props = PageProps<{
    hariIni: HariIniAbsensi;
    bulan: string;
    ringkasan: RingkasanAbsensi;
    riwayat: BarisAbsensi[];
    aturanAbsensi: AturanAbsensi;
}>;

/** 02.6 Absensi Saya (Koordinator): sama dengan 01.2, setelah absen tampil "Tercatat hadir hari ini". */
export default function AbsensiSayaKoordinator() {
    const { hariIni, bulan, ringkasan, riwayat, aturanAbsensi } = usePage<Props>().props;

    return (
        <>
            <Head title="Absensi Saya" />
            <PageHeader
                title="Absensi Saya (Koordinator Admin)"
                description="Koordinator Admin wajib absen masuk dan pulang dari kantor · Jam otomatis dari server WIB"
                actions={<ChipJamServer hariIni={hariIni} />}
            />
            <div className="grid gap-[18px]">
                {hariIni.absen ? (
                    <KartuTercatat absen={hariIni.absen} aturan={aturanAbsensi} urlPulang={route('absensi.saya.pulang')} />
                ) : (
                    <>
                        <CatatanAbsensi aturan={aturanAbsensi} biller={false} />
                        <div className="grid items-start gap-[18px] xl:grid-cols-[1fr_310px]">
                            <FormAbsenHarian hariIni={hariIni} url={route('absensi.saya.store')} aturan={aturanAbsensi} />
                            <RingkasanBulan bulan={bulan} ringkasan={ringkasan} />
                        </div>
                    </>
                )}
                <RiwayatAbsensi bulan={bulan} riwayat={riwayat} />
            </div>
        </>
    );
}

AbsensiSayaKoordinator.layout = layoutKoordinator;

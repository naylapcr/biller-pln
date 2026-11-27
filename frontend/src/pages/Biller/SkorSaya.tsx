import { Head, usePage } from '@inertiajs/react';
import { ChartNoAxesColumn } from 'lucide-react';

import { KartuKehadiran, KartuModulPasca, KartuModulPrabayar } from '@/components/biller/KartuModul';
import KartuSkorSaya from '@/components/biller/KartuSkorSaya';
import StatistikSkor from '@/components/biller/StatistikSkor';
import ChipPeriode from '@/components/shared/ChipPeriode';
import PeriodeSelect from '@/components/shared/PeriodeSelect';
import TabelKesalahan from '@/components/shared/TabelKesalahan';
import TrenSkor, { ChipSelisih } from '@/components/shared/TrenSkor';
import EmptyState from '@/components/ui/EmptyState';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { layoutBiller } from '@/layouts/BillerLayout/BillerLayout';
import { formatPercent } from '@/lib/format';
import type { Kesalahan, Option, PageProps, PeriodeInfo, SkorBiller, TitikTren } from '@/types';

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    skor: SkorBiller | null;
    tren: TitikTren[];
    kesalahan: Kesalahan[];
}>;

/** 01.1 Skor Saya. */
export default function SkorSaya() {
    const { periode, periodeOptions, skor, tren, kesalahan } = usePage<Props>().props;
    const pasca = skor?.nilai_per_modul.pascabayar;

    return (
        <>
            <Head title="Skor Saya" />
            <PageHeader
                title="Skor Kinerja Saya"
                description="Evaluasi otomatis dari data ACMT & AP2T · Hanya menampilkan data pribadi Anda"
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} />
                        <ChipPeriode periode={periode} />
                    </>
                }
            />

            {!skor || skor.final_score === null ? (
                <EmptyState icon={ChartNoAxesColumn} title="Skor belum tersedia">
                    Skor muncul setelah Koordinator meng-commit berkas ACMT untuk periode ini.
                </EmptyState>
            ) : (
                <div className="grid gap-[18px]">
                    <KartuSkorSaya skor={skor} />

                    <StatistikSkor skor={skor} />

                    <div className="grid items-start gap-[18px] xl:grid-cols-2">
                        <KartuModulPasca skor={skor} />
                        <KartuModulPrabayar skor={skor} />
                    </div>

                    <div className="grid items-start gap-[18px] xl:grid-cols-2">
                        <Kartu judul="Tren Skor 4 Periode (BLTH)" aksi={<ChipSelisih data={tren.map((t) => ({ label: t.label, nilai: t.final_score }))} />}>
                            <TrenSkor data={tren} />
                        </Kartu>
                        <KartuKehadiran skor={skor} />
                    </div>

                    <Kartu
                        judul="Rincian Kesalahan Saya (Baca Ulang & Koreksi AP2T)"
                        subjudul="Kesalahan Baca Ulang dibebankan ke pemilik RBM (pembaca pertama). Sisipan & belum baca tidak mengurangi skor."
                        aksi={
                            pasca && (
                                <>
                                    <StatusChip tone={pasca.baca_ulang.kasus_salah > 0 ? 'danger' : 'success'} label={`Rasio BU: ${formatPercent(pasca.baca_ulang.rasio * 100, 2)}`} />
                                    <StatusChip tone={pasca.ap2t.kasus_salah > 0 ? 'danger' : 'success'} label={`Rasio AP2T: ${formatPercent(pasca.ap2t.rasio * 100, 2)}`} />
                                </>
                            )
                        }
                    >
                        <TabelKesalahan data={kesalahan} />
                    </Kartu>
                </div>
            )}
        </>
    );
}

SkorSaya.layout = layoutBiller;

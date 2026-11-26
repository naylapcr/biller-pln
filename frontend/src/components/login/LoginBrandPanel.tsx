import LogoPln from '@/components/ui/LogoPln';

/** Panel merek di kiri halaman masuk (00.1). */
export default function LoginBrandPanel() {
    return (
        <section aria-label="Tentang aplikasi" className="flex flex-col justify-between gap-8 px-6 py-8 text-white sm:px-12 lg:w-[47%] lg:pr-4 lg:py-12 lg:pl-16">
            <div className="flex items-center gap-3.5">
                <LogoPln ukuran={44} />
                <div>
                    <p className="text-[9px] leading-[11px] font-bold tracking-[1.5px] text-[#66b2ff]">PT PLN (PERSERO) · ULP KOTA BARAT</p>
                    <p className="mt-0.5 text-[22px] leading-7 font-extrabold">Kinerja Biller</p>
                </div>
            </div>

            <div className="hidden lg:block lg:-translate-y-12">
                <p className="inline-flex items-center gap-2 rounded-full border border-[#40a6ff]/40 bg-[#146be0]/30 px-3 py-[6px] text-[10px] leading-[13px] font-bold tracking-[0.8px] text-[#ebf5ff]">
                    <span aria-hidden className="size-1.5 rounded-full bg-[#ffd91a]" />
                    PORTAL RESMI MONITORING 25 BILLER
                </p>
                <h1 className="mt-6 text-[42px] leading-[50px] font-extrabold">
                    Pantau & Evaluasi
                    <br />
                    Kinerja Biller
                    <br />
                    Secara Transparan
                </h1>
                <p className="mt-5 max-w-[460px] text-sm leading-6 text-[#adc7eb]">
                    Portal terpadu untuk absensi mandiri, validasi rekap baca meter, perhitungan otomatis skor 0–100 + bonus Hari J, hingga penetapan
                    laporan insentif resmi ULP Kota Barat.
                </p>
                <p className="mt-5 text-[17px] leading-[21px] font-bold text-[#38c2ff]">Akurat · Otomatis ACMT & AP2T · Realtime</p>
            </div>

            <p className="hidden gap-5 text-[11px] text-[#738cb2] lg:flex">
                <span>© 2026 PT PLN (Persero) ULP Kota Barat</span>
                <span className="text-[#617a9e]">Standar Kecepatan 33,3 plg/jam · v1.0</span>
            </p>
        </section>
    );
}

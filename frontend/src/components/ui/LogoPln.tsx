import emblem from '@/assets/images/logo-pln.png';

/** Emblem resmi PLN. Warna, proporsi, dan sudutnya tidak boleh diubah. */
export default function LogoPln({ ukuran = 40 }: { ukuran?: number }) {
    return <img src={emblem} alt="Logo PLN" width={ukuran} height={ukuran} className="shrink-0" />;
}

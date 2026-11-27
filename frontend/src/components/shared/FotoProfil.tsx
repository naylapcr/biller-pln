import { router } from '@inertiajs/react';
import { Camera, LoaderCircle, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

import UserAvatar from '@/components/ui/UserAvatar';
import { Button } from '@/components/ui/button';

/** Perkecil foto di browser menjadi persegi 256 px JPEG (±20–40 KB) supaya hemat penyimpanan. */
async function perkecil(berkas: File, sisi = 256): Promise<File> {
    const gambar = await createImageBitmap(berkas);
    const ukuran = Math.min(gambar.width, gambar.height);
    const kanvas = document.createElement('canvas');
    kanvas.width = sisi;
    kanvas.height = sisi;
    kanvas.getContext('2d')!.drawImage(gambar, (gambar.width - ukuran) / 2, (gambar.height - ukuran) / 2, ukuran, ukuran, 0, 0, sisi, sisi);
    const blob = await new Promise<Blob | null>((ok) => kanvas.toBlob(ok, 'image/jpeg', 0.85));
    if (!blob) throw new Error('Foto tidak bisa diproses.');
    return new File([blob], 'profil.jpg', { type: 'image/jpeg' });
}

/** Foto profil di Profil Saya: ganti (kamera/galeri) atau hapus. */
export default function FotoProfil({ nama, src, kelasAvatar }: { nama: string; src: string | null; kelasAvatar?: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [proses, setProses] = useState(false);
    const [galat, setGalat] = useState<string | null>(null);

    const unggah = async (berkas: File | undefined) => {
        if (!berkas) return;
        setGalat(null);
        if (!berkas.type.startsWith('image/')) {
            setGalat('Pilih berkas foto (JPG, PNG, atau WEBP).');
            return;
        }
        setProses(true);
        try {
            const kecil = await perkecil(berkas);
            router.post(route('profil.foto'), { foto: kecil }, {
                forceFormData: true,
                preserveScroll: true,
                onError: (e) => setGalat(e.foto ?? 'Foto gagal diunggah.'),
                onFinish: () => setProses(false),
            });
        } catch {
            setGalat('Foto tidak bisa diproses. Coba foto lain.');
            setProses(false);
        }
        if (input.current) input.current.value = '';
    };

    return (
        <div className="flex flex-col items-center gap-1.5">
            <UserAvatar name={nama} src={src} size={64} className={src ? undefined : kelasAvatar} />
            <div className="flex gap-1">
                <Button type="button" size="sm" variant="secondary" disabled={proses} onClick={() => input.current?.click()} aria-label="Ganti foto profil">
                    {proses ? <LoaderCircle className="animate-spin" /> : <Camera />}
                    {src ? 'Ganti' : 'Foto'}
                </Button>
                {src && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={proses}
                        aria-label="Hapus foto profil"
                        onClick={() => router.delete(route('profil.foto.hapus'), { preserveScroll: true })}
                    >
                        <Trash2 />
                    </Button>
                )}
            </div>
            <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(e) => unggah(e.target.files?.[0])} />
            {galat && <p className="max-w-40 text-center text-[11px] text-error-ink">{galat}</p>}
        </div>
    );
}

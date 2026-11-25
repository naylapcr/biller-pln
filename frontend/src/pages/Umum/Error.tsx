import { Head, Link, usePage } from '@inertiajs/react';

import petir from '@/assets/images/petir.svg';
import { Button } from '@/components/ui/button';
import type { SharedProps } from '@/types';

const PESAN: Record<number, { title: string; description: string }> = {
    403: { title: 'Akses ditolak', description: 'Anda tidak memiliki akses ke halaman ini.' },
    404: { title: 'Halaman tidak ditemukan', description: 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.' },
    419: { title: 'Sesi berakhir', description: 'Sesi Anda sudah berakhir. Silakan masuk lagi.' },
    500: { title: 'Terjadi kesalahan', description: 'Server mengalami kendala. Coba lagi beberapa saat lagi.' },
    503: { title: 'Sedang pemeliharaan', description: 'Aplikasi sedang diperbarui. Coba lagi beberapa saat lagi.' },
};

export default function ErrorPage({ status, message }: { status: number; message?: string | null }) {
    const { auth } = usePage<SharedProps>().props;
    const pesan = PESAN[status] ?? PESAN[500];

    return (
        <>
            <Head title={pesan.title} />
            <div className="flex min-h-dvh items-center justify-center bg-sky-side p-4">
                <div className="flex w-full max-w-md flex-col items-center rounded-2xl border border-line bg-white p-8 text-center shadow-kartu">
                    <span className="inline-flex size-12 items-center justify-center rounded-xl bg-pln-blue">
                        <img src={petir} alt="" width={20} height={20} />
                    </span>
                    <p className="mt-5 font-mono text-sm font-bold text-sky-ink">{status}</p>
                    <h1 className="mt-1 text-page-title text-ink">{pesan.title}</h1>
                    <p className="mt-2 text-[13px] text-muted-ink">{message || pesan.description}</p>
                    <Button asChild className="mt-6">
                        <Link href={auth.user ? route('home') : route('login')}>{auth.user ? 'Kembali ke beranda' : 'Ke halaman masuk'}</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

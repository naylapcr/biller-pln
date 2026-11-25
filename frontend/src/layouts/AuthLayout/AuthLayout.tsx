import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import garis from '@/assets/images/login-garis.svg';
import latar from '@/assets/images/login-latar.svg';
import miring from '@/assets/images/login-miring.svg';
import LoginBrandPanel from '@/components/login/LoginBrandPanel';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToast } from '@/hooks/useFlashToast';

/**
 * Halaman masuk layar penuh (00.1): latar navy, bidang biru miring di kanan dengan garis
 * bercahaya, panel merek di kiri, dan formulir 440 px di kanan. Di bawah 1024 px menumpuk.
 */
export default function AuthLayout({ title, children }: { title: string; children: ReactNode }) {
    useFlashToast();

    return (
        <>
            <Head title={title} />
            <div className="relative isolate flex min-h-dvh flex-col overflow-hidden bg-[#0f172a] lg:flex-row">
                <img src={latar} alt="" className="absolute inset-0 -z-10 size-full object-fill" />
                <img src={miring} alt="" className="absolute top-0 left-[44.5%] -z-10 hidden h-full w-[55.6%] max-w-none object-fill lg:block" />
                <img src={garis} alt="" className="absolute top-0 left-[44.42%] -z-10 hidden h-full w-[13.51%] max-w-none object-fill lg:block" />

                <LoginBrandPanel />
                <main className="flex flex-1 flex-col justify-center bg-gradient-to-br from-[#146be0] to-[#0f47b3] px-6 py-10 sm:px-12 lg:w-[53%] lg:flex-none lg:items-end lg:bg-none lg:px-0 lg:pr-[5.47%]">
                    <div className="mx-auto w-full max-w-[440px] lg:mx-0 lg:max-w-[min(440px,42vw)]">{children}</div>
                </main>
            </div>
            <Toaster />
        </>
    );
}

import { CircleAlert, Lock } from 'lucide-react';

/**
 * Pesan galat masuk (00.2): "NIP atau kata sandi tidak cocok. Sisa percobaan: 3/5", atau pesan akun
 * terkunci setelah 5 kali gagal.
 */
export default function LoginErrorNotice({ pesan }: { pesan?: string }) {
    if (!pesan) return null;
    const Icon = pesan.startsWith('Akun terkunci') ? Lock : CircleAlert;

    return (
        <div role="alert" className="mb-5 flex items-center gap-2.5 rounded-lg border-[1.5px] border-[#ff6666]/80 bg-[#f22626]/25 px-4 py-[11px] text-xs font-bold text-[#ffe5e5]">
            <Icon className="size-[18px] shrink-0 text-[#ff9999]" aria-hidden />
            {pesan}
        </div>
    );
}

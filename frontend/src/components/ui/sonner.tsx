import { Toaster as Sonner, type ToasterProps } from 'sonner';

const Ikon = ({ kelas, teks }: { kelas: string; teks: string }) => (
    <span aria-hidden className={`inline-flex size-7 items-center justify-center rounded-full text-[13px] font-extrabold ${kelas}`}>
        {teks}
    </span>
);

/** Toast 04.6: garis 1,5 px berwarna, ikon bulat 28 px, judul 12 px. */
function Toaster(props: ToasterProps) {
    return (
        <Sonner
            position="top-right"
            closeButton
            icons={{
                success: <Ikon kelas="bg-success-tint text-success-ink" teks="✓" />,
                info: <Ikon kelas="bg-pln-blue-light text-pln-blue" teks="✓" />,
                warning: <Ikon kelas="bg-pln-yellow-light text-pln-yellow-ink" teks="!" />,
                error: <Ikon kelas="bg-error-tint text-error-ink" teks="✕" />,
            }}
            toastOptions={{
                classNames: {
                    toast: 'rounded-xl! border-[1.5px]! bg-white! font-sans! text-xs! font-bold! text-ink! gap-4! py-3! pl-4! shadow-[0_4px_12px_rgb(15_23_42/0.06)]!',
                    icon: 'size-7! m-0!',
                    title: 'font-bold!',
                    success: 'border-[#86efac]!',
                    info: 'border-[#93c5fd]!',
                    warning: 'border-pln-yellow-line!',
                    error: 'border-error-line!',
                    description: 'text-slate-mid! text-[11px]! font-normal!',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };

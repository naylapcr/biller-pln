import StatusChip from '@/components/ui/StatusChip';
import type { PeriodeInfo } from '@/types';

/** Chip status periode di header halaman. */
export default function ChipPeriode({ periode }: { periode: PeriodeInfo | null }) {
    if (!periode) return null;

    return <StatusChip titik label={periode.terbuka ? 'Periode Terbuka' : 'Periode Ditutup (Final)'} tone={periode.terbuka ? 'success' : 'neutral'} />;
}

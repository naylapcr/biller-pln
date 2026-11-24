<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Period;
use Illuminate\Http\Request;

trait PilihPeriode
{
    /** Periode dari ?periode=202611, atau periode terbuka terbaru. */
    protected function periode(Request $request): ?Period
    {
        $kode = $request->string('periode')->toString();

        return ($kode !== '' ? Period::kode($kode) : null) ?? Period::bawaan();
    }

    /** @return list<array{value: string, label: string}> */
    protected function opsiPeriode(): array
    {
        return Period::query()->orderByDesc('kode')->get()
            ->map(fn (Period $p) => [
                'value' => $p->kode,
                'label' => $p->nama().($p->terbuka() ? '' : ' (ditutup)'),
            ])->all();
    }

    protected function infoPeriode(?Period $p): ?array
    {
        return $p ? [
            'kode' => $p->kode,
            'nama' => $p->nama(),
            'status' => $p->status->value,
            'terbuka' => $p->terbuka(),
        ] : null;
    }
}

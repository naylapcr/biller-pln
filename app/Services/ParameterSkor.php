<?php

namespace App\Services;

use App\Models\Period;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parameter skor per periode (tabel scoring_rules). Periode baru menyalin dari periode
 * sebelumnya; periode pertama memakai nilai bawaan di config/sipermon.php.
 */
class ParameterSkor
{
    /** @return array<string, mixed> */
    public static function bawaan(): array
    {
        return json_decode(json_encode(config('sipermon.parameter')), true);
    }

    /** @return array<string, mixed> parameter lengkap periode ini */
    public function untuk(Period $periode): array
    {
        $tersimpan = ScoringRule::query()->where('period_id', $periode->id)->pluck('nilai', 'kunci')->all();
        $hasil = [];
        foreach (self::bawaan() as $kunci => $nilai) {
            $hasil[$kunci] = array_key_exists($kunci, $tersimpan) ? $tersimpan[$kunci] : $nilai;
        }

        return $hasil;
    }

    /** Isi scoring_rules periode baru dari periode sebelumnya (atau nilai bawaan). */
    public function siapkan(Period $periode): void
    {
        $sebelum = Period::query()->where('kode', '<', $periode->kode)->orderByDesc('kode')->first();
        $nilai = $sebelum ? $this->untuk($sebelum) : self::bawaan();

        foreach ($nilai as $kunci => $v) {
            ScoringRule::updateOrCreate(['period_id' => $periode->id, 'kunci' => $kunci], ['nilai' => $v]);
        }
    }

    /** @return list<string> parameter wajib yang masih kosong */
    public function kosongWajib(Period $periode): array
    {
        $nilai = $this->untuk($periode);

        return array_values(array_filter(
            config('sipermon.parameter_wajib'),
            fn (string $k) => $nilai[$k] === null || $nilai[$k] === '' || $nilai[$k] === [],
        ));
    }

    /**
     * @param  array<string, mixed>  $baru
     *
     * @throws ValidationException
     */
    public function ubah(Period $periode, array $baru, User $oleh): void
    {
        if (! $periode->terbuka()) {
            throw ValidationException::withMessages(['periode' => "Periode {$periode->kode} sudah ditutup; parameter terkunci."]);
        }

        $sebelum = $this->untuk($periode);
        $berubah = [];

        DB::transaction(function () use ($periode, $baru, $oleh, $sebelum, &$berubah) {
            foreach ($baru as $kunci => $nilai) {
                if (! array_key_exists($kunci, $sebelum) || self::sama($sebelum[$kunci], $nilai)) {
                    continue;
                }
                ScoringRule::updateOrCreate(['period_id' => $periode->id, 'kunci' => $kunci], ['nilai' => $nilai, 'diubah_oleh' => $oleh->id]);
                $berubah[$kunci] = $nilai;
            }
        });

        if ($berubah) {
            Audit::catat('ubah_parameter', $periode, array_intersect_key($sebelum, $berubah), $berubah, $oleh->id);
            app(PenghitungSkor::class)->hitung($periode);
        }
    }

    /** Pembandingan ketat (null ≠ 0), tetapi 4 dan 4.0 dianggap sama. */
    private static function sama(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return json_encode($a) === json_encode($b);
    }
}

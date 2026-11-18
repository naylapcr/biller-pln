<?php

namespace App\Services;

use App\Models\Biller;
use App\Models\BillerAlias;
use App\Models\Period;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Login petugas ACMT yang belum terpetakan ke biller, dan pemetaannya lewat alias.
 * Setelah alias ditambahkan, biller_id diisi di tabel rincian periode terbuka dan skornya dihitung ulang.
 */
class LayananPemetaan
{
    private const TABEL = ['reading_logs' => 'Pascabayar', 'reread_cases' => 'Baca Ulang', 'prepaid_inspections' => 'Prabayar'];

    public function __construct(private readonly PenghitungSkor $skor) {}

    /** @return Collection<int, array{login: string, baris: int, per_modul: array<string, int>}> */
    public function belumTerpetakan(Period $periode): Collection
    {
        $hasil = [];
        foreach (self::TABEL as $tabel => $label) {
            $baris = DB::table($tabel)->where('period_id', $periode->id)->whereNull('biller_id')
                ->groupBy('petugas_login')->selectRaw('petugas_login, COUNT(*) as n')->pluck('n', 'petugas_login');
            foreach ($baris as $login => $n) {
                $hasil[$login]['login'] = $login;
                $hasil[$login]['baris'] = ($hasil[$login]['baris'] ?? 0) + (int) $n;
                $hasil[$login]['per_modul'][$label] = (int) $n;
            }
        }

        return collect($hasil)->sortByDesc('baris')->values();
    }

    public function tambahAlias(Biller $biller, string $login, ?string $berlakuMulai, User $oleh): BillerAlias
    {
        $alias = BillerAlias::create(['biller_id' => $biller->id, 'login_acmt' => $login, 'berlaku_mulai' => $berlakuMulai]);
        $diperbarui = $this->isiBiller($alias->login_acmt, $biller->id);

        Audit::catat('alias_baru', $alias, null, [
            'login_acmt' => $alias->login_acmt, 'biller' => $biller->nama, 'baris_diperbarui' => $diperbarui,
        ], $oleh->id);

        return $alias;
    }

    /**
     * Isi biller_id untuk login ini di periode terbuka, lalu hitung ulang skor periode yang berubah.
     *
     * @return int jumlah baris yang diperbarui
     */
    public function isiBiller(string $login, ?int $billerId): int
    {
        $login = strtolower(trim($login));
        $terbuka = Period::query()->terbuka()->get();
        $total = 0;

        foreach ($terbuka as $periode) {
            $n = 0;
            DB::transaction(function () use ($periode, $login, $billerId, &$n) {
                foreach (array_keys(self::TABEL) as $tabel) {
                    $n += DB::table($tabel)->where('period_id', $periode->id)->whereRaw('LOWER(petugas_login) = ?', [$login])
                        ->update(['biller_id' => $billerId]);
                }
                DB::table('corrections')->where('period_id', $periode->id)->whereRaw('LOWER(petugas_login) = ?', [$login])
                    ->update(['biller_id' => $billerId]);
            });
            if ($n > 0) {
                $this->skor->hitung($periode);
            }
            $total += $n;
        }

        return $total;
    }
}

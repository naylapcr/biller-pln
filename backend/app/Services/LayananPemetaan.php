<?php

namespace App\Services;

use App\Enums\SumberKoreksi;
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

    /**
     * Login ACMT di periode ini yang belum terpetakan ke biller, beserta batch sumbernya.
     *
     * @return Collection<int, array{login: string, baris: int, per_modul: array<string, int>, batch: list<array{id: string, file_name: string, modul: string|null}>}>
     */
    public function belumTerpetakan(Period $periode): Collection
    {
        $hasil = [];
        foreach (self::TABEL as $tabel => $label) {
            $baris = DB::table($tabel.' as t')->join('upload_batches as b', 'b.id', '=', 't.batch_id')
                ->where('t.period_id', $periode->id)->whereNull('t.biller_id')
                ->groupBy('t.petugas_login', 't.batch_id', 'b.file_name', 'b.modul')
                ->selectRaw('t.petugas_login as login, t.batch_id, b.file_name, b.modul, COUNT(*) as n')
                ->get();
            foreach ($baris as $r) {
                $login = strtolower($r->login);
                $hasil[$login]['login'] = $login;
                $hasil[$login]['baris'] = ($hasil[$login]['baris'] ?? 0) + (int) $r->n;
                $hasil[$login]['per_modul'][$label] = ($hasil[$login]['per_modul'][$label] ?? 0) + (int) $r->n;
                $hasil[$login]['batch'][$r->batch_id] = ['id' => $r->batch_id, 'file_name' => $r->file_name, 'modul' => $r->modul];
            }
        }

        return collect($hasil)->map(fn ($h) => ['batch' => array_values($h['batch'])] + $h)->sortByDesc('baris')->values();
    }

    /** @return array{terdeteksi: int, terpetakan: int, belum: int, belum_baris: int} login ACMT unik di data periode ini */
    public function statistik(Period $periode): array
    {
        $semua = collect(array_keys(self::TABEL))
            ->flatMap(fn (string $t) => DB::table($t)->where('period_id', $periode->id)->distinct()->pluck('petugas_login'))
            ->map(fn ($l) => strtolower((string) $l))->unique()->count();
        $belum = $this->belumTerpetakan($periode);

        return [
            'terdeteksi' => $semua,
            'terpetakan' => $semua - $belum->count(),
            'belum' => $belum->count(),
            'belum_baris' => (int) $belum->sum('baris'),
        ];
    }

    public function tambahAlias(Biller $biller, string $login, ?string $berlakuMulai, User $oleh): BillerAlias
    {
        $alias = BillerAlias::create(['biller_id' => $biller->id, 'login_acmt' => $login, 'berlaku_mulai' => $berlakuMulai]);
        $diperbarui = $this->isiBiller($alias->login_acmt, $biller->id);

        Audit::catat(Audit::ADD_ALIAS, $alias, null, [
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
                foreach (['reading_logs', 'prepaid_inspections'] as $tabel) {
                    $n += DB::table($tabel)->where('period_id', $periode->id)->whereRaw('LOWER(petugas_login) = ?', [$login])
                        ->update(['biller_id' => $billerId]);
                }
                DB::table('corrections')->where('period_id', $periode->id)->where('sumber', SumberKoreksi::Ap2t->value)
                    ->whereRaw('LOWER(petugas_login) = ?', [$login])->update(['biller_id' => $billerId]);
                $n += $this->petakanBacaUlang($periode);
            });
            if ($n > 0) {
                $this->skor->hitung($periode);
            }
            $total += $n;
        }

        return $total;
    }

    /**
     * Tentukan ulang pemilik kasus Baca Ulang. Kasus selalu milik pemilik KODE RBM (PRD 6.2); login
     * PETUGAS hanya dipakai bila kode petugas di KODE RBM belum terdaftar. Alias untuk petugas
     * verifikasi karena itu tidak boleh memindahkan kasus dari pemilik RBM.
     *
     * @return int jumlah kasus yang berpindah pemilik
     */
    private function petakanBacaUlang(Period $periode): int
    {
        $petugas = PemetaanPetugas::muat();
        $n = 0;
        $kelompok = DB::table('reread_cases')->where('period_id', $periode->id)
            ->distinct()->get(['kode_rbm', 'petugas_login', 'biller_id']);

        foreach ($kelompok as $k) {
            $pemilik = $petugas->pemilikRbm($k->kode_rbm, $k->petugas_login);
            if ($pemilik === ($k->biller_id === null ? null : (int) $k->biller_id)) {
                continue;
            }
            $ids = DB::table('reread_cases')->where('period_id', $periode->id)
                ->where('kode_rbm', $k->kode_rbm)->where('petugas_login', $k->petugas_login)->pluck('id');
            foreach ($ids->chunk(500) as $potongan) {
                DB::table('reread_cases')->whereIn('id', $potongan)->update(['biller_id' => $pemilik]);
                DB::table('corrections')->whereIn('reread_case_id', $potongan)->update(['biller_id' => $pemilik]);
            }
            $n += $ids->count();
        }

        return $n;
    }
}

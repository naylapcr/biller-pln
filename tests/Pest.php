<?php

use App\Enums\Role;
use App\Models\Biller;
use App\Models\Period;
use App\Models\User;
use App\Services\LayananPeriode;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\ContohBerkasAcmt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Bantuan bersama untuk tes SIPERMON.
*/

/** Master biller dari daftar petugas ACMT (default: 25 petugas ULP Kota Barat). */
function buatBillers(?array $petugas = null): void
{
    foreach ($petugas ?? DatabaseSeeder::PETUGAS as [$login, $kode]) {
        Biller::create(['nama' => ucfirst(strtolower(substr($login, 6))), 'login_acmt' => $login, 'kode_petugas_acmt' => $kode]);
    }
}

function superAdmin(): User
{
    return User::query()->where('role', Role::SuperAdmin->value)->first() ?? User::factory()->superAdmin()->create();
}

function bukaPeriode(string $kode = '202611'): Period
{
    return Period::kode($kode) ?? app(LayananPeriode::class)->buka($kode, superAdmin());
}

/** Tulis berkas contoh ACMT ke folder sementara dan bungkus sebagai UploadedFile. */
function berkasAcmt(callable $isi, ?array $petugas = null, string $blth = '202611', string $unitup = '18111', string $nama = 'DATA REKAP WBM.xlsx', int $seed = 202611): UploadedFile
{
    $path = sys_get_temp_dir().'/sipermon-'.uniqid().'.xlsx';
    $isi(new ContohBerkasAcmt($petugas ?? DatabaseSeeder::PETUGAS, $blth, $unitup, $seed))->simpan($path);

    return new UploadedFile($path, $nama, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/** Petugas pendek untuk tes yang butuh angka pasti. */
function petugasKecil(): array
{
    return [['18111.AKMAL', 'AWA'], ['18111.joni', 'ARA'], ['18111.deni', 'ALA']];
}

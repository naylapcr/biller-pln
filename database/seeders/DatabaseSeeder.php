<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Models\Attendance;
use App\Models\Biller;
use App\Models\Period;
use App\Models\User;
use App\Services\LayananAbsensi;
use App\Services\LayananPeriode;
use App\Services\PengelolaBatch;
use App\Services\PenghitungSkor;
use Database\Seeders\Support\ContohBerkasAcmt;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Data awal SIPERMON:
 *  - 25 biller ULP Kota Barat dari sheet PASCA TUNGGAL (login ACMT + kode petugas). Nama dan NIP
 *    sebenarnya belum diterima (pertanyaan terbuka no. 8), jadi nama sementara diambil dari login.
 *  - Akun Super Admin, Koordinator Admin, dan satu akun per biller (username = login ACMT).
 *  - Periode BLTH 202611 (terbuka) berisi data demo sintetis yang diunggah dan di-commit lewat
 *    jalur unggah biasa. Untuk memakai data asli, jalankan:
 *      php artisan sipermon:unggah "DATA REKAP WBM.xlsx" 202611 --commit
 *    setelah membatalkan/rollback batch demo, atau seed tanpa demo: SIPERMON_DEMO=false.
 */
class DatabaseSeeder extends Seeder
{
    /** [login ACMT, kode petugas] persis seperti di ekspor ACMT BLTH 202611. */
    public const PETUGAS = [
        ['18111.AKMAL', 'AWA'], ['18111.alhafiz', 'AAA'], ['18111.anhar', 'AKA'], ['18111.ARIS', 'AGA'],
        ['18111.Arsyad', 'ACA'], ['18111.asikin', 'ABA'], ['18111.deni', 'ALA'], ['18111.denni', 'AMA'],
        ['18111.DODI', 'AUA'], ['18111.dwi', 'ANA'], ['18111.ENTAN', 'ASA'], ['18111.FIRMAN', 'AVA'],
        ['18111.Hardimas', 'AOA'], ['18111.hendriadi', 'ADA'], ['18111.ibnu', 'AQA'], ['18111.jeni', 'AEA'],
        ['18111.joni', 'ARA'], ['18111.junizan', 'AFA'], ['18111.LUTFI', 'AXA'], ['18111.MIQBAL', 'APA'],
        ['18111.safriyon', 'AHA'], ['18111.safrizal', 'AIA'], ['18111.yongki', 'ATA'], ['18111.zainal', 'AJA'],
        ['18111.ZULKARNAIN', 'AYA'],
    ];

    public function run(): void
    {
        $sandi = Hash::make('password');

        $super = User::updateOrCreate(['username_nip' => 'superadmin'], [
            'nama' => 'Super Admin SIPERMON', 'password' => $sandi, 'role' => Role::SuperAdmin, 'is_active' => true,
        ]);
        User::updateOrCreate(['username_nip' => 'koordinator'], [
            'nama' => 'Koordinator Admin ULP Kota Barat', 'password' => $sandi, 'role' => Role::Admin, 'is_active' => true,
        ]);

        foreach (self::PETUGAS as [$login, $kode]) {
            $biller = Biller::updateOrCreate(['login_acmt' => strtolower($login)], [
                'nama' => ucwords(strtolower(substr($login, 6))),
                'kode_petugas_acmt' => $kode,
                'zona_wilayah' => 'ULP Kota Barat',
                'is_active' => true,
            ]);
            User::updateOrCreate(['username_nip' => $biller->login_acmt], [
                'nama' => $biller->nama, 'password' => $sandi, 'role' => Role::Biller, 'biller_id' => $biller->id, 'is_active' => true,
            ]);
        }

        $periode = Period::kode('202611') ?? app(LayananPeriode::class)->buka('202611', $super);

        if (filter_var(env('SIPERMON_DEMO', true), FILTER_VALIDATE_BOOL) && ! $periode->batches()->exists()) {
            $this->dataDemo($periode, $super);
            $this->absensiDemo($periode);
        }
    }

    /**
     * Absensi demo dari awal rentang periode sampai kemarin: sebagian besar hadir, beberapa
     * izin/sakit, dan beberapa hari sengaja kosong (menjadi alpa).
     */
    private function absensiDemo(Period $periode): void
    {
        mt_srand(17);
        $kerja = app(LayananAbsensi::class)->hariKerja($periode, null, LayananAbsensi::hariIni()->subDay());
        $baris = [];
        foreach (Biller::query()->with('user:id,biller_id')->get() as $b) {
            foreach ($kerja as $tanggal) {
                $acak = mt_rand(1, 100);
                if ($acak <= 3) {
                    continue; // tidak absen → alpa
                }
                $status = match (true) {
                    $acak <= 6 => StatusAbsensi::Izin,
                    $acak <= 9 => StatusAbsensi::Sakit,
                    default => StatusAbsensi::Hadir,
                };
                $baris[] = [
                    'biller_id' => $b->id,
                    'tanggal' => $tanggal,
                    'status' => $status->value,
                    'keterangan' => match ($status) {
                        StatusAbsensi::Izin => 'Keperluan keluarga',
                        StatusAbsensi::Sakit => 'Demam',
                        default => null,
                    },
                    'sumber' => 'biller',
                    'dicatat_oleh' => $b->user?->id,
                    'created_at' => $tanggal.' 07:'.str_pad((string) mt_rand(0, 59), 2, '0', STR_PAD_LEFT).':00',
                    'updated_at' => $tanggal.' 07:30:00',
                ];
            }
        }
        foreach (array_chunk($baris, 500) as $potongan) {
            Attendance::insert($potongan);
        }
        app(PenghitungSkor::class)->hitung($periode);
    }

    /** Unggah dan commit berkas contoh sintetis lewat jalur yang sama dengan unggahan sungguhan. */
    private function dataDemo(Period $periode, User $oleh): void
    {
        $path = storage_path('app/tmp/contoh-acmt-202611.xlsx');
        (new ContohBerkasAcmt(self::PETUGAS))->tunggal()->ganda()->bacaUlang()->prabayar(12)->simpan($path);

        $pengelola = app(PengelolaBatch::class);
        $batch = $pengelola->unggah(new UploadedFile($path, 'CONTOH DATA REKAP WBM (demo).xlsx', null, null, true), $periode, null, $oleh);
        $batch->refresh();
        if ($batch->status->value === 'diproses') {
            $pengelola->proses($batch);
        }
        foreach ($batch->saudara()->get() as $b) {
            if ($b->status->value === 'siap_commit') {
                $pengelola->commit($b, $oleh);
            }
        }
        @unlink($path);
    }
}

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
use Database\Seeders\Support\ContohBerkasAcmt;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'nama' => 'Super Admin SIPERMON', 'password' => $sandi, 'wajib_ganti_sandi' => true, 'role' => Role::SuperAdmin, 'is_active' => true,
        ]);
        $koordinator = User::updateOrCreate(['username_nip' => 'koordinator'], [
            'nama' => 'Koordinator Admin ULP Kota Barat', 'password' => $sandi, 'wajib_ganti_sandi' => true, 'role' => Role::Admin, 'is_active' => true,
        ]);

        foreach (self::PETUGAS as [$login, $kode]) {
            $biller = Biller::updateOrCreate(['login_acmt' => strtolower($login)], [
                'nama' => ucwords(strtolower(substr($login, 6))),
                'kode_petugas_acmt' => $kode,
                'zona_wilayah' => 'ULP Kota Barat',
                'is_active' => true,
            ]);
            User::updateOrCreate(['username_nip' => $biller->login_acmt], [
                'nama' => $biller->nama, 'password' => $sandi, 'wajib_ganti_sandi' => true, 'role' => Role::Biller, 'biller_id' => $biller->id, 'is_active' => true,
            ]);
        }

        $periode = Period::kode('202611') ?? app(LayananPeriode::class)->buka('202611', $super);

        if (filter_var(env('SIPERMON_DEMO', true), FILTER_VALIDATE_BOOL) && ! $periode->batches()->exists()) {
            $this->absensiDemo(); // lebih dulu agar skor demo memuat nilai kehadiran
            $this->dataDemo($periode, $koordinator); // unggah dan commit khusus Koordinator
        }
    }

    /**
     * Absensi demo Biller dan Koordinator dari awal bulan lalu (bulan kerja BLTH ini) sampai kemarin (hari kerja saja):
     * sebagian besar hadir (sesekali terlambat atau pulang cepat), beberapa izin/sakit berlampiran, dan beberapa
     * alpa dari sistem. Lampiran demo berupa satu PDF contoh di disk unggahan.
     */
    private function absensiDemo(): void
    {
        mt_srand(17);
        $hariIni = LayananAbsensi::hariIni();
        $baris = [];
        $lampiran = 'absensi/demo/surat-contoh.pdf';
        Storage::disk(LayananAbsensi::disk())->put($lampiran, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        $orang = User::query()->whereIn('role', [Role::Biller->value, Role::Admin->value])->pluck('id');
        for ($t = $hariIni->startOfMonth()->subMonthNoOverflow(); $t->lessThan($hariIni); $t = $t->addDay()) {
            if (! LayananAbsensi::hariKerja($t)) {
                continue;
            }
            foreach ($orang as $id) {
                $acak = mt_rand(1, 100);
                $status = match (true) {
                    $acak <= 3 => StatusAbsensi::Alpa,
                    $acak <= 6 => StatusAbsensi::Izin,
                    $acak <= 9 => StatusAbsensi::Sakit,
                    default => StatusAbsensi::Hadir,
                };
                $hadir = $status === StatusAbsensi::Hadir;
                $jam = $t->setTime(7, mt_rand(5, 40));
                $pulang = $t->setTime(16, mt_rand(20, 59));
                $baris[] = [
                    'user_id' => $id,
                    'tanggal' => $t->toDateString(),
                    'status' => $status->value,
                    'absen_at' => $status === StatusAbsensi::Alpa ? null : $jam->toDateTimeString(),
                    'terlambat_menit' => $hadir ? max(0, $jam->minute - 30) : null,
                    'pulang_at' => $hadir ? $pulang->toDateTimeString() : null,
                    'pulang_cepat_menit' => $hadir ? max(0, 30 - $pulang->minute) : null,
                    'lat' => $hadir ? 0.5 + mt_rand(0, 9999) / 100000 : null,
                    'lng' => $hadir ? 101.4 + mt_rand(0, 9999) / 100000 : null,
                    'jarak_m' => $hadir ? mt_rand(80, 950) / 10 : null,
                    'pulang_jarak_m' => $hadir ? mt_rand(80, 950) / 10 : null,
                    'ip' => $status === StatusAbsensi::Alpa ? null : '10.18.111.'.mt_rand(10, 250),
                    'perangkat' => $status === StatusAbsensi::Alpa ? null : 'Chrome · Android',
                    'foto_path' => in_array($status, [StatusAbsensi::Izin, StatusAbsensi::Sakit], true) ? $lampiran : null,
                    'keterangan' => match ($status) {
                        StatusAbsensi::Izin => 'Keperluan keluarga',
                        StatusAbsensi::Sakit => 'Demam',
                        default => null,
                    },
                    'sumber' => $status === StatusAbsensi::Alpa ? 'sistem' : 'mandiri',
                    'created_at' => $jam->toDateTimeString(),
                    'updated_at' => $jam->toDateTimeString(),
                ];
            }
        }
        foreach (array_chunk($baris, 500) as $potongan) {
            Attendance::insert($potongan);
        }
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

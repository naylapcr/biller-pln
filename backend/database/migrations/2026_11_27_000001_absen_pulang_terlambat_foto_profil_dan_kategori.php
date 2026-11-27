<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Arahan mentor 26 Nov 2026:
 *  - Absen masuk dan pulang, menit terlambat dan pulang cepat, IP dan perangkat setiap absen.
 *  - Foto profil, dan wajib ganti kata sandi bila masih memakai kata sandi bawaan.
 *  - Nama kategori: Grade A → Baik, Perlu Evaluasi → Cukup, Kritis → Perlu Pendampingan.
 *  - Radius absen bawaan 100 m untuk periode yang masih terbuka.
 */
return new class extends Migration
{
    private const KATEGORI = ['Grade A' => 'Baik', 'Perlu Evaluasi' => 'Cukup', 'Kritis' => 'Perlu Pendampingan'];

    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('terlambat_menit')->nullable()->after('absen_at');
            $table->string('ip', 45)->nullable()->after('akurasi_m');
            $table->string('perangkat', 160)->nullable()->after('ip');
            $table->timestampTz('pulang_at')->nullable()->after('perangkat');
            $table->unsignedSmallInteger('pulang_cepat_menit')->nullable()->after('pulang_at');
            $table->decimal('pulang_lat', 10, 7)->nullable()->after('pulang_cepat_menit');
            $table->decimal('pulang_lng', 10, 7)->nullable()->after('pulang_lat');
            $table->decimal('pulang_jarak_m', 9, 1)->nullable()->after('pulang_lng');
            $table->string('pulang_ip', 45)->nullable()->after('pulang_jarak_m');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('nama');
            $table->boolean('wajib_ganti_sandi')->default(false)->after('password');
        });

        foreach (self::KATEGORI as $lama => $baru) {
            DB::table('monthly_scores')->where('kategori', $lama)->update(['kategori' => $baru]);
        }

        $terbuka = DB::table('periods')->where('status', 'terbuka')->pluck('id');
        DB::table('scoring_rules')->whereIn('period_id', $terbuka)->where('kunci', 'radius_absen_m')
            ->update(['nilai' => json_encode(100)]);

        // Akun yang masih memakai kata sandi bawaan seeder wajib menggantinya saat login berikutnya.
        DB::table('users')->select(['id', 'password'])->orderBy('id')->each(function ($u) {
            if (Hash::check('password', $u->password)) {
                DB::table('users')->where('id', $u->id)->update(['wajib_ganti_sandi' => true]);
            }
        });
    }

    public function down(): void
    {
        foreach (self::KATEGORI as $lama => $baru) {
            DB::table('monthly_scores')->where('kategori', $baru)->update(['kategori' => $lama]);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['foto_path', 'wajib_ganti_sandi']);
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['terlambat_menit', 'ip', 'perangkat', 'pulang_at', 'pulang_cepat_menit', 'pulang_lat', 'pulang_lng', 'pulang_jarak_m', 'pulang_ip']);
        });
    }
};

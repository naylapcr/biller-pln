<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi memengaruhi skor (bobot kehadiran), bukti foto untuk izin/sakit, dan jarak ke kantor saat
 * absen hadir (radius). Bonus Hari J bawaan turun dari 5 ke 2 poin untuk periode yang masih terbuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('keterangan'); // bukti izin/sakit
            $table->decimal('jarak_m', 9, 1)->nullable()->after('lng');   // jarak ke kantor saat absen hadir
            $table->decimal('akurasi_m', 9, 1)->nullable()->after('jarak_m');
        });

        Schema::table('monthly_scores', function (Blueprint $table) {
            $table->decimal('nilai_kehadiran', 6, 2)->nullable()->after('rata_rata_modul');
            $table->json('kehadiran')->nullable()->after('nilai_kehadiran');
        });

        $terbuka = DB::table('periods')->where('status', 'terbuka')->pluck('id');
        DB::table('scoring_rules')->whereIn('period_id', $terbuka)->where('kunci', 'bobot_bonus_j')
            ->get(['id', 'nilai'])
            ->filter(fn ($r) => (float) json_decode((string) $r->nilai, true) === 5.0)
            ->each(fn ($r) => DB::table('scoring_rules')->where('id', $r->id)->update(['nilai' => json_encode(2.0)]));
    }

    public function down(): void
    {
        Schema::table('monthly_scores', function (Blueprint $table) {
            $table->dropColumn(['nilai_kehadiran', 'kehadiran']);
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['foto_path', 'jarak_m', 'akurasi_m']);
        });
    }
};

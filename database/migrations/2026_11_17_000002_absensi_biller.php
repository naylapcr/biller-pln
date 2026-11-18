<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi diisi biller sendiri setiap hari (hadir, izin, sakit). Hari kerja tanpa absensi
 * dihitung alpa dan didenda per hari sesuai parameter periode. Koordinator Admin bisa mengoreksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rentang tanggal absensi yang dinilai di periode ini (bawaan: bulan kalender BLTH).
        Schema::table('periods', function (Blueprint $table) {
            $table->date('absensi_mulai')->nullable()->after('status');
            $table->date('absensi_selesai')->nullable()->after('absensi_mulai');
        });

        DB::table('periods')->orderBy('id')->get(['id', 'kode'])->each(function ($p) {
            $awal = CarbonImmutable::createFromFormat('Ym', $p->kode)->startOfMonth();
            DB::table('periods')->where('id', $p->id)->update([
                'absensi_mulai' => $awal->toDateString(),
                'absensi_selesai' => $awal->endOfMonth()->toDateString(),
            ]);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('status');
            $table->string('sumber', 10)->default('biller')->after('keterangan'); // biller, admin, berkas
            $table->foreignId('dicatat_oleh')->nullable()->after('sumber')->constrained('users')->nullOnDelete();
            $table->index('tanggal');
        });

        Schema::table('monthly_scores', function (Blueprint $table) {
            $table->decimal('denda_absensi', 10, 2)->default(0)->after('denda_ap2t');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_scores', fn (Blueprint $table) => $table->dropColumn('denda_absensi'));
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicatat_oleh');
            $table->dropIndex(['tanggal']);
            $table->dropColumn(['keterangan', 'sumber']);
        });
        Schema::table('periods', fn (Blueprint $table) => $table->dropColumn(['absensi_mulai', 'absensi_selesai']));
    }
};

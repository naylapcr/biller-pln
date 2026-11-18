<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema SIPERMON (PRD Backend, bagian 7). Ditulis agar jalan di PostgreSQL (Supabase)
 * maupun MySQL; kolom json menjadi jsonb di PostgreSQL. Setiap baris fakta menyimpan
 * batch_id, sehingga rollback cukup menghapus baris dengan batch_id itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biller_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biller_id')->constrained('billers')->cascadeOnDelete();
            $table->string('login_acmt', 60)->unique(); // huruf kecil
            $table->date('berlaku_mulai')->nullable();
            $table->timestamps();
        });

        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->char('kode', 6)->unique(); // BLTH, contoh 202611
            $table->string('status', 10)->default('terbuka');
            $table->timestamp('ditutup_at')->nullable();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('upload_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unggahan_id')->index(); // satu berkas bisa menghasilkan beberapa batch (satu per modul)
            $table->foreignId('period_id')->constrained('periods')->restrictOnDelete();
            $table->string('modul', 30)->nullable(); // kosong selama sheet belum terdeteksi
            $table->string('modul_diminta', 30)->nullable(); // null = deteksi otomatis
            $table->string('sheet')->nullable();
            $table->string('file_name');
            $table->string('storage_path');
            $table->char('checksum_sha256', 64)->index();
            $table->string('status', 20)->default('diproses');
            $table->json('ringkasan')->nullable();
            $table->foreignId('diunggah_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->uuid('superseded_by')->nullable()->index();
            $table->timestamps();

            $table->index(['period_id', 'modul', 'status']);
        });

        Schema::create('upload_row_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('upload_batches')->cascadeOnDelete();
            $table->string('sheet')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('kode', 40);
            $table->string('tingkat', 10); // error, warning, info
            $table->text('pesan');
            $table->json('nilai')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['batch_id', 'tingkat']);
            $table->index(['batch_id', 'row_number']);
        });

        Schema::create('staging_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('upload_batches')->cascadeOnDelete();
            $table->string('sheet')->nullable();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->timestamp('created_at')->nullable();

            $table->index(['batch_id', 'row_number']);
        });

        Schema::create('reading_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->restrictOnDelete();
            $table->foreignId('biller_id')->nullable()->constrained('billers')->nullOnDelete();
            $table->string('petugas_login', 60);
            $table->string('kode_petugas', 10)->nullable();
            $table->string('kode_rbm', 20);
            $table->char('hari_baca', 1);
            $table->string('jenis_meter', 20); // tunggal, ganda_industri
            $table->unsignedInteger('jml_plg');
            $table->unsignedInteger('terbaca');
            $table->unsignedInteger('belum_baca');
            $table->unsignedInteger('foto_berhasil');
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->unsignedInteger('durasi_detik')->nullable();
            $table->foreignUuid('batch_id')->constrained('upload_batches')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['period_id', 'jenis_meter', 'kode_rbm', 'petugas_login']);
            $table->index(['period_id', 'biller_id']);
            $table->index('petugas_login');
        });

        Schema::create('reread_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->restrictOnDelete();
            $table->foreignId('biller_id')->nullable()->constrained('billers')->nullOnDelete();
            $table->string('petugas_login', 60);
            $table->string('idpel', 12);
            $table->string('tarif', 10)->nullable();
            $table->unsignedInteger('daya_va')->nullable();
            $table->string('kode_rbm', 20)->nullable();
            $table->char('hari_baca', 1)->nullable();
            $table->timestamp('tgl_baca')->nullable();
            $table->decimal('stan_awal', 14, 2)->nullable();
            $table->decimal('pemkwh_awal', 14, 2)->nullable();
            $table->timestamp('tgl_baca_bu')->nullable();
            $table->decimal('stan_bu', 14, 2)->nullable();
            $table->decimal('pemkwh_bu', 14, 2)->nullable();
            $table->string('status_bu', 20)->nullable(); // SUDAH BACA / BELUM BACA
            $table->decimal('batas_kwh', 14, 2)->nullable();
            $table->string('hasil', 20); // salah_turun, salah_lebih, wajar, tidak_dinilai
            $table->foreignUuid('batch_id')->constrained('upload_batches')->restrictOnDelete();
            $table->timestamps();

            // PRD menyebut IDPEL + TGLBACA, tetapi contoh berkas punya 3 pasang baris dengan IDPEL dan
            // TGLBACA sama yang berbeda TGLBACA BU, jadi TGLBACA BU ikut menjadi kunci unik.
            $table->unique(['period_id', 'idpel', 'tgl_baca', 'tgl_baca_bu']);
            $table->index(['period_id', 'biller_id', 'hasil']);
            $table->index('petugas_login');
        });

        Schema::create('prepaid_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->restrictOnDelete();
            $table->foreignId('biller_id')->nullable()->constrained('billers')->nullOnDelete();
            $table->string('petugas_login', 60);
            $table->string('idpel', 12);
            $table->string('no_meter', 20)->nullable();
            $table->string('tarif', 10)->nullable();
            $table->unsignedInteger('daya_va')->nullable();
            $table->string('kdrbm', 20)->nullable();
            $table->string('kd_kelompok', 5)->nullable();
            $table->timestamp('tgl_baca')->nullable();
            $table->string('kd_baca', 5)->nullable();
            $table->string('segel', 30)->nullable();
            $table->string('lcd', 30)->nullable();
            $table->string('keypad', 30)->nullable();
            $table->string('relay', 30)->nullable();
            $table->string('status_temper', 30)->nullable();
            $table->decimal('kwh_sisa', 14, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('koordinat_valid')->default(false);
            $table->json('raw')->nullable();
            $table->foreignUuid('batch_id')->constrained('upload_batches')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['period_id', 'idpel']);
            $table->index(['period_id', 'biller_id']);
            $table->index('petugas_login');
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biller_id')->constrained('billers')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->string('modul_type', 20); // prabayar, pascabayar, invoice, pdil
            $table->unsignedInteger('target_wo')->default(0);
            $table->unsignedInteger('realisasi')->default(0);
            $table->unsignedInteger('eviden')->default(0);
            $table->unsignedBigInteger('durasi_detik')->default(0);
            $table->timestamps();

            $table->unique(['biller_id', 'period_id', 'modul_type']);
        });

        Schema::create('corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biller_id')->nullable()->constrained('billers')->nullOnDelete();
            $table->foreignId('period_id')->constrained('periods')->restrictOnDelete();
            $table->string('petugas_login', 60)->nullable();
            $table->string('idpel', 12);
            $table->string('sumber', 5); // AP2T, BU
            $table->decimal('stan_awal', 14, 2)->nullable();
            $table->decimal('stan_benar', 14, 2)->nullable();
            $table->string('tingkat', 20)->nullable();
            $table->decimal('denda_poin', 10, 2)->default(0);
            $table->foreignId('reread_case_id')->nullable()->constrained('reread_cases')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('upload_batches')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['period_id', 'biller_id', 'sumber']);
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biller_id')->constrained('billers')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status', 10); // hadir, izin, sakit, alpa
            $table->foreignUuid('batch_id')->nullable()->constrained('upload_batches')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['biller_id', 'tanggal']);
        });

        Schema::create('scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->string('kunci', 40);
            $table->json('nilai')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_id', 'kunci']);
        });

        Schema::create('monthly_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biller_id')->constrained('billers')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->decimal('poin_tugas', 12, 2)->default(0);
            $table->json('poin_per_modul');
            $table->decimal('poin_hari_j', 10, 2)->default(0);
            $table->decimal('denda_bu', 10, 2)->default(0);
            $table->decimal('denda_ap2t', 10, 2)->default(0);
            $table->decimal('total_denda', 10, 2)->default(0);
            $table->decimal('final_score', 12, 2)->default(0);
            $table->unsignedInteger('stan_valid')->default(0); // pemecah seri peringkat
            $table->unsignedSmallInteger('peringkat')->nullable();
            $table->string('kategori', 20)->nullable();
            $table->json('indikator')->nullable(); // rasio kelalaian & kecepatan, tidak masuk skor
            $table->json('rules_snapshot');
            $table->boolean('is_final')->default(false);
            $table->timestamp('dihitung_at')->nullable();
            $table->timestamps();

            $table->unique(['biller_id', 'period_id']);
            $table->index(['period_id', 'peringkat']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 40);
            $table->string('objek_type', 40)->nullable();
            $table->string('objek_id', 40)->nullable();
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['aksi', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Satu batch committed per (periode, modul).
            DB::statement("CREATE UNIQUE INDEX upload_batches_satu_committed ON upload_batches (period_id, modul) WHERE status = 'committed'");

            // Supabase: aktifkan Row Level Security tanpa kebijakan apa pun, sehingga peran anon dan
            // authenticated (kunci publik) ditolak. Backend memakai koneksi database langsung sebagai
            // pemilik tabel, yang tidak terkena RLS.
            $tabel = ['billers', 'users', 'sessions', 'biller_aliases', 'periods', 'upload_batches', 'upload_row_issues',
                'staging_rows', 'reading_logs', 'reread_cases', 'prepaid_inspections', 'work_orders', 'corrections',
                'attendance', 'scoring_rules', 'monthly_scores', 'audit_logs', 'cache', 'cache_locks', 'jobs',
                'job_batches', 'failed_jobs', 'migrations'];
            foreach ($tabel as $t) {
                DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            }
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'monthly_scores', 'scoring_rules', 'attendance', 'corrections', 'work_orders',
            'prepaid_inspections', 'reread_cases', 'reading_logs', 'staging_rows', 'upload_row_issues',
            'upload_batches', 'periods', 'biller_aliases'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

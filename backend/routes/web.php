<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AbsensiFotoController;
use App\Http\Controllers\AbsensiSayaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirektoriBillerController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PemetaanController;
use App\Http\Controllers\Pengaturan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SayaController;
use App\Http\Controllers\UnggahController;
use Illuminate\Support\Facades\Route;

/*
 * Rute per peran mengikuti desain (PRD 10.1–10.2). Super Admin tidak membuka halaman
 * operasional Koordinator: /biller, /unggah, /pemetaan, /absensi, dan /laporan membalas 403.
 */

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route(request()->user()->role->homeRoute()))->name('home');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profil.password');
    Route::post('/profil/foto', [ProfileController::class, 'updateFoto'])->middleware('throttle:10,1')->name('profil.foto');
    Route::delete('/profil/foto', [ProfileController::class, 'hapusFoto'])->name('profil.foto.hapus');
    Route::get('/pengguna/{user}/foto', [ProfileController::class, 'foto'])->name('pengguna.foto');
    Route::get('/absensi/foto/{attendance}', AbsensiFotoController::class)->name('absensi.foto');

    // Biller: hanya membaca skor, rincian, histori, dan peringkat miliknya; absen harian sendiri.
    Route::middleware('role:biller')->prefix('saya')->name('saya.')->group(function () {
        Route::redirect('/', '/saya/skor');
        Route::get('/skor', [SayaController::class, 'skor'])->name('skor');
        Route::get('/absensi', [AbsensiSayaController::class, 'index'])->name('absensi');
        Route::post('/absensi', [AbsensiSayaController::class, 'store'])->middleware('throttle:20,1')->name('absensi.store');
        Route::post('/absensi/pulang', [AbsensiSayaController::class, 'pulang'])->middleware('throttle:20,1')->name('absensi.pulang');
        Route::get('/histori-wo', [SayaController::class, 'historiWo'])->name('histori-wo');
        Route::get('/leaderboard', [SayaController::class, 'leaderboard'])->name('leaderboard');
    });

    // Dashboard: Koordinator → Dashboard Monitoring, Super Admin → Dashboard Sistem.
    Route::get('/dashboard', DashboardController::class)->middleware('role:admin,super_admin')->name('dashboard');

    // Koordinator Admin: unggah, commit, rollback, pemetaan (baca), absensi, dan laporan.
    Route::middleware('role:admin')->group(function () {
        Route::get('/biller', DirektoriBillerController::class)->name('biller.index');

        Route::get('/unggah', [UnggahController::class, 'index'])->name('unggah.index');
        Route::post('/unggah', [UnggahController::class, 'store'])->middleware('throttle:20,1')->name('unggah.store');
        Route::get('/unggah/template', [UnggahController::class, 'template'])->name('unggah.template');
        Route::get('/unggah/{batch}', [UnggahController::class, 'show'])->whereUuid('batch')->name('unggah.show');
        Route::get('/unggah/{batch}/status', [UnggahController::class, 'status'])->whereUuid('batch')->name('unggah.status');
        Route::get('/unggah/{batch}/issues', [UnggahController::class, 'issues'])->whereUuid('batch')->name('unggah.issues');
        Route::get('/unggah/{batch}/berkas', [UnggahController::class, 'berkas'])->whereUuid('batch')->name('unggah.berkas');
        Route::post('/unggah/{batch}/commit', [UnggahController::class, 'commit'])->whereUuid('batch')->name('unggah.commit');
        Route::post('/unggah/{batch}/rollback', [UnggahController::class, 'rollback'])->whereUuid('batch')->name('unggah.rollback');
        Route::delete('/unggah/{batch}', [UnggahController::class, 'destroy'])->whereUuid('batch')->name('unggah.destroy');

        Route::get('/pemetaan', [PemetaanController::class, 'index'])->name('pemetaan.index');

        Route::get('/absensi/saya', [AbsensiSayaController::class, 'index'])->name('absensi.saya');
        Route::post('/absensi/saya', [AbsensiSayaController::class, 'store'])->middleware('throttle:20,1')->name('absensi.saya.store');
        Route::post('/absensi/saya/pulang', [AbsensiSayaController::class, 'pulang'])->middleware('throttle:20,1')->name('absensi.saya.pulang');
        Route::get('/absensi', [AbsensiController::class, 'index'])->name('absensi.index');

        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/{kode}.{format}', [LaporanController::class, 'unduh'])
            ->where(['kode' => '\d{6}', 'format' => 'xlsx|pdf'])->name('laporan.unduh');
    });

    // Super Admin: akun, master biller, pemetaan alias, periode & parameter, koreksi absensi, audit.
    Route::middleware('role:super_admin')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::resource('users', Pengaturan\UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('users/{user}/unlock', [Pengaturan\UserController::class, 'unlock'])->name('users.unlock');

        Route::resource('billers', Pengaturan\BillerController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('alias/{alias}', [Pengaturan\BillerController::class, 'hapusAlias'])->name('alias.destroy');

        Route::get('pemetaan', [PemetaanController::class, 'alias'])->name('pemetaan.index');
        Route::post('pemetaan', [PemetaanController::class, 'store'])->name('pemetaan.store');

        Route::get('periode', [Pengaturan\PeriodeController::class, 'index'])->name('periode.index');
        Route::post('periode', [Pengaturan\PeriodeController::class, 'store'])->name('periode.store');
        Route::put('periode/{kode}/rules', [Pengaturan\PeriodeController::class, 'updateRules'])->where('kode', '\d{6}')->name('periode.rules');
        Route::post('periode/{kode}/recalculate', [Pengaturan\PeriodeController::class, 'recalculate'])->where('kode', '\d{6}')->name('periode.recalculate');
        Route::post('periode/{kode}/close', [Pengaturan\PeriodeController::class, 'close'])->where('kode', '\d{6}')->name('periode.close');

        Route::get('koreksi-absensi', [Pengaturan\KoreksiAbsensiController::class, 'index'])->name('koreksi-absensi.index');
        Route::post('koreksi-absensi', [Pengaturan\KoreksiAbsensiController::class, 'store'])->name('koreksi-absensi.store');

        // Arsip resmi periode final (04.2).
        Route::get('periode/{kode}/laporan.{format}', [LaporanController::class, 'unduhFinal'])
            ->where(['kode' => '\d{6}', 'format' => 'xlsx|pdf'])->name('periode.unduh');

        Route::get('audit', [Pengaturan\AuditController::class, 'index'])->name('audit.index');
        Route::get('audit/csv', [Pengaturan\AuditController::class, 'csv'])->name('audit.csv');
    });
});

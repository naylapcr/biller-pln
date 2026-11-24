<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AbsensiSayaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KinerjaBillerController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PemetaanController;
use App\Http\Controllers\Pengaturan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SayaController;
use App\Http\Controllers\UnggahController;
use Illuminate\Support\Facades\Route;

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

    // Biller: hanya membaca skor, rincian, denda, dan peringkat miliknya.
    Route::middleware('role:biller')->group(function () {
        Route::redirect('/saya', '/saya/skor');
        Route::get('/saya/skor', SayaController::class)->name('saya.skor');
        Route::get('/saya/absensi', [AbsensiSayaController::class, 'index'])->name('saya.absensi');
        Route::post('/saya/absensi', [AbsensiSayaController::class, 'store'])->middleware('throttle:20,1')->name('saya.absensi.store');
    });

    // Koordinator Admin & Super Admin.
    Route::middleware('role:admin,super_admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/biller', KinerjaBillerController::class)->name('biller.index');

        Route::get('/unggah', [UnggahController::class, 'index'])->name('unggah.index');
        Route::post('/unggah', [UnggahController::class, 'store'])->middleware('throttle:20,1')->name('unggah.store');
        Route::get('/unggah/{batch}', [UnggahController::class, 'show'])->whereUuid('batch')->name('unggah.show');
        Route::get('/unggah/{batch}/status', [UnggahController::class, 'status'])->whereUuid('batch')->name('unggah.status');
        Route::post('/unggah/{batch}/commit', [UnggahController::class, 'commit'])->whereUuid('batch')->name('unggah.commit');
        Route::post('/unggah/{batch}/rollback', [UnggahController::class, 'rollback'])->whereUuid('batch')->name('unggah.rollback');
        Route::delete('/unggah/{batch}', [UnggahController::class, 'destroy'])->whereUuid('batch')->name('unggah.destroy');

        Route::get('/pemetaan', [PemetaanController::class, 'index'])->name('pemetaan.index');

        Route::get('/absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::put('/absensi/{biller}', [AbsensiController::class, 'update'])->whereNumber('biller')->name('absensi.update');

        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/{kode}.{format}', [LaporanController::class, 'unduh'])
            ->where(['kode' => '\d{6}', 'format' => 'xlsx|pdf'])->name('laporan.unduh');
    });

    // Super Admin: akun, master biller, alias, periode & parameter, audit.
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/pemetaan', [PemetaanController::class, 'store'])->name('pemetaan.store');

        Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
            Route::resource('users', Pengaturan\UserController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::resource('billers', Pengaturan\BillerController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('alias/{alias}', [Pengaturan\BillerController::class, 'hapusAlias'])->name('alias.destroy');

            Route::get('periode', [Pengaturan\PeriodeController::class, 'index'])->name('periode.index');
            Route::post('periode', [Pengaturan\PeriodeController::class, 'store'])->name('periode.store');
            Route::get('periode/{kode}', [Pengaturan\PeriodeController::class, 'show'])->where('kode', '\d{6}')->name('periode.show');
            Route::put('periode/{kode}/rules', [Pengaturan\PeriodeController::class, 'updateRules'])->where('kode', '\d{6}')->name('periode.rules');
            Route::post('periode/{kode}/recalculate', [Pengaturan\PeriodeController::class, 'recalculate'])->where('kode', '\d{6}')->name('periode.recalculate');
            Route::post('periode/{kode}/close', [Pengaturan\PeriodeController::class, 'close'])->where('kode', '\d{6}')->name('periode.close');

            Route::get('audit', Pengaturan\AuditController::class)->name('audit.index');
        });
    });
});

<?php

use App\Models\User;

beforeEach(fn () => $this->withoutVite());

test('semua halaman dan unduhan dialihkan ke login bila belum login', function () {
    $user = User::factory()->biller()->create();

    foreach (['/', '/dashboard', '/saya/skor', '/saya/absensi', '/saya/histori-wo', '/saya/leaderboard', '/profil',
        '/biller', '/unggah', '/unggah/template', '/pemetaan', '/absensi', '/laporan',
        '/pengaturan/users', '/pengaturan/billers', '/pengaturan/periode', '/pengaturan/koreksi-absensi', '/pengaturan/audit',
        '/pengaturan/audit/csv', "/pengguna/{$user->id}/foto", '/absensi/foto/1'] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    $this->post('/saya/absensi', ['status' => 'hadir'])->assertRedirect(route('login'));
});

test('tidak ada rute publik untuk berkas penyimpanan', function () {
    $this->get('/storage/unggahan/berkas.xlsx')->assertNotFound();
    $this->get('/storage/absensi/2026-11/surat.pdf')->assertNotFound();
});

test('halaman pengguna yang login tidak disimpan di cache browser dan memakai header keamanan', function () {
    $user = User::factory()->biller()->create();

    $r = $this->actingAs($user)->get('/profil')->assertOk();
    expect($r->headers->get('Cache-Control'))->toContain('no-store')
        ->and($r->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('setelah logout halaman tidak bisa dibuka lagi dan sesi berakhir saat browser ditutup', function () {
    $user = User::factory()->biller()->create();

    $this->actingAs($user)->get('/saya/skor')->assertOk();
    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
    $this->get('/saya/skor')->assertRedirect(route('login'));
    expect(config('session.expire_on_close'))->toBeTrue();
});

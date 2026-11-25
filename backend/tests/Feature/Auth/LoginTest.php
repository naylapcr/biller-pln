<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('halaman masuk tampil', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('tamu diarahkan ke halaman masuk', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/saya/skor')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/login');
});

test('tiap peran diarahkan ke halaman yang benar setelah login dengan username_nip', function (Role $role, string $tujuan) {
    $user = $role === Role::Biller ? User::factory()->biller()->create() : User::factory()->role($role)->create();

    $this->post('/login', ['login' => $user->username_nip, 'password' => 'password'])
        ->assertRedirect($tujuan);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::where('aksi', 'LOGIN')->where('user_id', $user->id)->exists())->toBeTrue();
})->with([
    'biller' => [Role::Biller, '/saya/skor'],
    'koordinator admin' => [Role::Admin, '/dashboard'],
    'super admin' => [Role::SuperAdmin, '/dashboard'],
]);

test('kata sandi salah ditolak dengan sisa percobaan', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->username_nip, 'password' => 'salah'])
        ->assertSessionHasErrors(['login' => 'NIP atau kata sandi tidak cocok. Sisa percobaan: 4/5']);
    $this->post('/login', ['login' => $user->username_nip, 'password' => 'salah'])
        ->assertSessionHasErrors(['login' => 'NIP atau kata sandi tidak cocok. Sisa percobaan: 3/5']);

    $this->assertGuest();
    expect($user->fresh()->gagal_login)->toBe(2);
});

test('akun nonaktif tidak bisa masuk', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['login' => $user->username_nip, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('login keenam setelah 5 kali gagal ditolak walaupun kata sandi benar, sampai dibuka Super Admin', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['login' => $user->username_nip, 'password' => 'salah']);
    }
    expect($user->fresh()->terkunci())->toBeTrue();
    RateLimiter::clear(strtolower($user->username_nip).'|127.0.0.1');

    $this->post('/login', ['login' => $user->username_nip, 'password' => 'password'])
        ->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('Akun terkunci');
    $this->assertGuest();

    $this->actingAs(superAdmin())->post("/pengaturan/users/{$user->id}/unlock")->assertSessionHas('success');
    expect($user->fresh())->terkunci()->toBeFalse()->gagal_login->toBe(0)
        ->and(AuditLog::where('aksi', 'UNLOCK_USER')->exists())->toBeTrue();

    auth()->logout();
    $this->post('/login', ['login' => $user->username_nip, 'password' => 'password'])->assertRedirect('/dashboard');
});

test('login berhasil mengosongkan hitungan gagal', function () {
    $user = User::factory()->create();
    $this->post('/login', ['login' => $user->username_nip, 'password' => 'salah']);

    $this->post('/login', ['login' => $user->username_nip, 'password' => 'password']);

    expect($user->fresh()->gagal_login)->toBe(0);
});

test('pengguna yang sudah masuk diarahkan dari /login ke beranda perannya', function () {
    $biller = User::factory()->biller()->create();

    $this->actingAs($biller)->get('/login')->assertRedirect('/');
    $this->actingAs($biller)->get('/')->assertRedirect('/saya/skor');
});

test('logout mengakhiri sesi', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

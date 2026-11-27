<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
});

test('akun dengan kata sandi bawaan diarahkan ke Profil sampai kata sandinya diganti', function () {
    $user = User::factory()->create(['wajib_ganti_sandi' => true]);

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect(route('profil.edit'))
        ->assertSessionHas('error', 'Demi keamanan, ganti kata sandi bawaan Anda terlebih dahulu.');
    $this->actingAs($user)->get('/profil')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('auth.user.wajib_ganti_sandi', true));

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'password', 'password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->wajib_ganti_sandi)->toBeFalse();
    $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
});

test('kata sandi baru tidak boleh sama dengan NIP/username atau memuat kata password', function () {
    $user = User::factory()->create(['username_nip' => 'Koordinator01']);

    foreach (['Koordinator01', 'Password2026'] as $sandi) {
        $this->actingAs($user)->put('/profil/password', [
            'current_password' => 'password', 'password' => $sandi, 'password_confirmation' => $sandi,
        ])->assertSessionHasErrors('password');
    }
});

test('akun baru dan reset kata sandi oleh Super Admin wajib ganti kata sandi saat login', function () {
    $this->actingAs(superAdmin())->post('/pengaturan/users', [
        'username_nip' => 'koor2', 'nama' => 'Koordinator Dua', 'role' => 'admin', 'password' => 'Sementara123', 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $baru = User::where('username_nip', 'koor2')->sole();
    expect($baru->wajib_ganti_sandi)->toBeTrue();

    $baru->forceFill(['wajib_ganti_sandi' => false])->save();
    $this->actingAs(superAdmin())->put("/pengaturan/users/{$baru->id}", [
        'username_nip' => 'koor2', 'nama' => 'Koordinator Dua', 'role' => 'admin', 'password' => 'ResetLagi123', 'is_active' => true,
    ])->assertSessionHasNoErrors();
    expect($baru->fresh()->wajib_ganti_sandi)->toBeTrue();

    // Ubah data tanpa kata sandi tidak memaksa ganti kata sandi.
    $baru->refresh()->forceFill(['wajib_ganti_sandi' => false])->save();
    $this->actingAs(superAdmin())->put("/pengaturan/users/{$baru->id}", [
        'username_nip' => 'koor2', 'nama' => 'Koordinator Dua Baru', 'role' => 'admin', 'is_active' => true,
    ])->assertSessionHasNoErrors();
    expect($baru->fresh()->wajib_ganti_sandi)->toBeFalse();
});

test('foto profil bisa diunggah, dilihat pengguna yang login, diganti, dan dihapus', function () {
    $user = User::factory()->biller()->create();

    $this->actingAs($user)->post('/profil/foto', ['foto' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('foto');
    $this->actingAs($user)->post('/profil/foto', ['foto' => UploadedFile::fake()->image('saya.jpg', 256, 256)])
        ->assertSessionHas('success', 'Foto profil diperbarui.');

    $lama = $user->fresh()->foto_path;
    expect($lama)->toStartWith('profil/');
    Storage::disk('unggahan')->assertExists($lama);
    $this->actingAs($user)->get('/profil')
        ->assertInertia(fn (Assert $p) => $p->where('auth.user.foto_url', fn ($url) => str_contains((string) $url, "/pengguna/{$user->id}/foto")));
    $this->actingAs(User::factory()->create())->get(route('pengguna.foto', $user))->assertOk();
    $this->app['auth']->forgetGuards();
    $this->get(route('pengguna.foto', $user))->assertRedirect(route('login'));

    $this->actingAs($user)->post('/profil/foto', ['foto' => UploadedFile::fake()->image('baru.png', 256, 256)]);
    Storage::disk('unggahan')->assertMissing($lama);

    $this->actingAs($user)->delete('/profil/foto')->assertSessionHas('success', 'Foto profil dihapus.');
    expect($user->fresh()->foto_path)->toBeNull();
});

<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('halaman profil tampil untuk semua peran beserta sesi dan aktivitas', function () {
    foreach ([User::factory()->superAdmin()->create(), User::factory()->create(), User::factory()->biller()->create()] as $user) {
        $this->actingAs($user)->get('/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Umum/Profil')
                ->where('profil.username_nip', $user->username_nip)
                ->where('sesi.lama_sesi_jam', 8)
                ->has('aktivitas'));
    }
});

test('nama bisa diperbarui', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profil', ['nama' => 'Rina Koordinator'])->assertSessionHasNoErrors();

    expect($user->fresh()->nama)->toBe('Rina Koordinator');
});

test('kata sandi baru wajib minimal 8 karakter dengan huruf besar, huruf kecil, dan angka', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'password',
        'password' => 'sandibaru',
        'password_confirmation' => 'sandibaru',
    ])->assertSessionHasErrors('password');

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'password',
        'password' => 'SandiBaru123',
        'password_confirmation' => 'SandiBaru123',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('SandiBaru123', $user->fresh()->password))->toBeTrue()
        ->and(AuditLog::where('aksi', 'CHANGE_PASSWORD')->exists())->toBeTrue();
});

test('ubah kata sandi gagal bila kata sandi lama salah', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'salah',
        'password' => 'SandiBaru123',
        'password_confirmation' => 'SandiBaru123',
    ])->assertSessionHasErrors('current_password');
});

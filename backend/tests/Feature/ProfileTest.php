<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('halaman profil tampil untuk semua peran', function () {
    foreach ([User::factory()->superAdmin()->create(), User::factory()->create(), User::factory()->biller()->create()] as $user) {
        $this->actingAs($user)->get('/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Profile/Edit')->where('profil.username_nip', $user->username_nip));
    }
});

test('nama bisa diperbarui', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profil', ['nama' => 'Rina Koordinator'])->assertSessionHasNoErrors();

    expect($user->fresh()->nama)->toBe('Rina Koordinator');
});

test('kata sandi bisa diubah dengan kata sandi lama yang benar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'password',
        'password' => 'sandi-baru-123',
        'password_confirmation' => 'sandi-baru-123',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('sandi-baru-123', $user->fresh()->password))->toBeTrue();
});

test('ubah kata sandi gagal bila kata sandi lama salah', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'salah',
        'password' => 'sandi-baru-123',
        'password_confirmation' => 'sandi-baru-123',
    ])->assertSessionHasErrors('current_password');
});

<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Biller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username_nip' => fake()->unique()->numerify('##################'),
            'nama' => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Admin,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function superAdmin(): static
    {
        return $this->role(Role::SuperAdmin);
    }

    /** Akun biller yang ditautkan ke baris master biller (dibuat bila tidak diberikan). */
    public function biller(?Biller $biller = null): static
    {
        return $this->state(fn () => [
            'role' => Role::Biller,
            'biller_id' => $biller?->id ?? Biller::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

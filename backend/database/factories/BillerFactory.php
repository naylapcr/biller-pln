<?php

namespace Database\Factories;

use App\Models\Biller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Biller>
 */
class BillerFactory extends Factory
{
    protected $model = Biller::class;

    public function definition(): array
    {
        $nama = fake()->unique()->firstName();

        return [
            'nama' => $nama,
            'login_acmt' => '18111.'.strtolower($nama).fake()->unique()->numberBetween(1, 9999),
            'kode_petugas_acmt' => 'Z'.strtoupper(fake()->unique()->bothify('??')),
            'zona_wilayah' => null,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $kode = fake()->unique()->numerify('3########0');
        $nup = fake()->numberBetween(1, 999);

        return [
            'asset_code' => "{$kode}-{$nup}",
            'kode_barang' => $kode,
            'nup' => $nup,
            'name' => fake()->randomElement(['Proyektor', 'AC Split', 'Kursi Kuliah', 'Komputer PC']).' '.fake()->numerify('##'),
            'brand' => fake()->randomElement(['Epson', 'Daikin', 'Lenovo', null]),
            'type' => null,
            'jenis_bmn' => 'MESIN PERALATAN NON TIK',
            'golongan' => 'NON-IT',
            'condition' => 'Baik',
            'acquired_at' => fake()->date(),
            'acquisition_value' => fake()->numberBetween(500_000, 20_000_000),
            'kode_register' => fake()->uuid(),
            'category_id' => Category::factory(),
            'bmn_data' => [],
        ];
    }
}

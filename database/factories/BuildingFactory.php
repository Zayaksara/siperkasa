<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Building> */
class BuildingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('G??')),
            'name' => 'Gedung '.fake()->unique()->lastName(),
        ];
    }

    public function synced(): static
    {
        return $this->state(fn () => [
            'sibermap_id' => fake()->unique()->numberBetween(1000, 999999),
            'campus_name' => 'Kampus '.fake()->numberBetween(1, 5),
            'synced_at' => now(),
        ]);
    }
}

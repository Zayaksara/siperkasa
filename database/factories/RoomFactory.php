<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'code' => fake()->unique()->numerify('R-###'),
            'name' => 'Ruang '.fake()->unique()->numerify('###'),
            'floor' => fake()->numberBetween(1, 4),
        ];
    }
}

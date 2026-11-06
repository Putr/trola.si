<?php

namespace Database\Factories;

use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'code' => (string) fake()->unique()->numberBetween(100000, 899999),
            'name' => fake()->unique()->city(),
            'routes' => ['1'],
            'opposite_station_id' => null,
            'is_direction_to_center' => null,
        ];
    }
}

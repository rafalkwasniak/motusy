<?php

namespace Database\Factories;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Motorcycle>
 */
class MotorcycleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand' => fake()->randomElement(['Yamaha', 'Honda', 'Suzuki', 'Kawasaki', 'BMW']),
            'model' => fake()->bothify('??-##'),
            'production_year' => fake()->numberBetween(1990, 2026),
            'color' => fake()->randomElement(['czarny', 'czerwony', 'niebieski', 'srebrny']),
        ];
    }
}

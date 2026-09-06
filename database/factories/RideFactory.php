<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    protected $model = Ride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => Str::lower(Str::random(12)),
            'seq' => $this->faker->unique()->numberBetween(1, 10000),
            'duration_s' => $this->faker->numberBetween(120, 7200),

            // Bez GPS-a urządzenie nie zna czasu ani prędkości — to jest
            // stan domyślny, nie wyjątek.
            'recorded_at' => null,
            'speed_kmh' => null,

            // Tak samo hałas: mikrofon doszedł do urządzenia we wrześniu 2026,
            // więc brak pomiaru jest stanem domyślnym. Liczniki jadą zawsze —
            // to one odróżniają ciszę od awarii.
            'max_noise_db' => null,
            'noise_at_speed_kmh' => null,
            'noise_clipped' => 0,
            'noise_dropped' => 0,
            'noise_cal' => 0,

            'lean_left_deg' => $this->faker->randomFloat(1, 5, 52),
            'lean_right_deg' => $this->faker->randomFloat(1, 5, 52),
            'accel_g' => $this->faker->randomFloat(2, 0.1, 1.2),
            'brake_g' => $this->faker->randomFloat(2, 0.1, 1.2),
            'fw' => '1.0.0',
            'calibrated' => true,
        ];
    }

    /**
     * Wariant z GPS-em: znany czas zakończenia i prędkość maksymalna.
     */
    public function withGps(): static
    {
        return $this->state(fn () => [
            'recorded_at' => now()->subDay()->getTimestamp(),
            'speed_kmh' => $this->faker->randomFloat(1, 40, 220),
        ]);
    }

    /**
     * Wariant z mikrofonem: zmierzony poziom hałasu i prędkość, przy której
     * padł rekord. Realny zakres to 50–126 dB(A).
     */
    public function withNoise(): static
    {
        return $this->state(fn () => [
            'max_noise_db' => $this->faker->randomFloat(1, 70, 118),
            'noise_at_speed_kmh' => $this->faker->numberBetween(30, 160),
            'noise_cal' => 1,
        ]);
    }
}

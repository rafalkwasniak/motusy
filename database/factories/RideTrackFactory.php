<?php

namespace Database\Factories;

use App\Models\RideTrack;
use App\Models\User;
use App\Services\TrackParser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RideTrack>
 */
class RideTrackFactory extends Factory
{
    protected $model = RideTrack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deviceId = Str::lower(Str::random(12));
        $seq = $this->faker->unique()->numberBetween(1, 10000);

        return [
            'user_id' => User::factory(),
            'device_id' => $deviceId,
            'seq' => $seq,

            // Ślad bywa szybszy niż wynik przejazdu — to jest stan normalny,
            // nie wyjątek (docs/api-slad-trasy.md §2).
            'ride_id' => null,

            'path' => "1/{$deviceId}/{$seq}.mmbt",
            'bytes' => 1024,
            'format' => TrackParser::FORMAT,
            'fw' => '1.0.0',
            'corridor_m' => 8,

            'point_count' => 4,
            'segment_count' => 2,
            'distance_m' => 1234,
            'started_at' => null,
            'ended_at' => null,
            'min_lat' => 51.33343,
            'max_lat' => 51.33528,
            'min_lon' => 19.57648,
            'max_lon' => 19.58127,
            'max_lean_deg' => -31,
        ];
    }
}

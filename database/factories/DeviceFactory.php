<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => Str::lower(Str::random(12)),
            'name' => null,
            'type' => 'motobox',
            'fw' => '1.0.0',
            'calibrated' => true,
            'last_seen_at' => now(),
        ];
    }

    /**
     * Identyfikator układu to 12 znaków hex — taki przychodzi z eFuse MAC.
     */
    public function withDeviceId(string $deviceId): static
    {
        return $this->state(fn () => ['device_id' => $deviceId]);
    }
}

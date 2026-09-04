<?php

namespace App\Models;

use Database\Factories\RideTrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Ślad trasy jednego przejazdu — docs/api-slad-trasy.md.
 *
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property int $seq
 * @property int|null $ride_id
 * @property string $path
 * @property int $bytes
 * @property string $format
 * @property string $fw
 * @property int $corridor_m
 * @property int $point_count
 * @property int $segment_count
 * @property int $distance_m
 * @property int|null $started_at
 * @property int|null $ended_at
 * @property float|null $min_lat
 * @property float|null $max_lat
 * @property float|null $min_lon
 * @property float|null $max_lon
 * @property int|null $max_lean_deg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id',
    'device_id',
    'seq',
    'ride_id',
    'path',
    'bytes',
    'format',
    'fw',
    'corridor_m',
    'point_count',
    'segment_count',
    'distance_m',
    'started_at',
    'ended_at',
    'min_lat',
    'max_lat',
    'min_lon',
    'max_lon',
    'max_lean_deg',
])]
class RideTrack extends Model
{
    /** @use HasFactory<RideTrackFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'bytes' => 'integer',
            'corridor_m' => 'integer',
            'point_count' => 'integer',
            'segment_count' => 'integer',
            'distance_m' => 'integer',
            'started_at' => 'integer',
            'ended_at' => 'integer',
            'min_lat' => 'float',
            'max_lat' => 'float',
            'min_lon' => 'float',
            'max_lon' => 'float',
            'max_lean_deg' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Surowa treść śladu, bajt w bajt taka, jaka przyszła z urządzenia.
     */
    public function contents(): string
    {
        return (string) Storage::disk('tracks')->get($this->path);
    }

    /**
     * Dystans w kilometrach, z sumy odcinków między punktami.
     *
     * Wynik jest zaniżony na zakrętach (cięciwa zamiast łuku); przy korytarzu
     * ε=8 m błąd jest rzędu 1% (kontrakt §4), więc nadaje się na licznik
     * kilometrów, ale nie na „długość przejazdu co do metra".
     */
    public function distanceKm(): float
    {
        return $this->distance_m / 1000;
    }
}

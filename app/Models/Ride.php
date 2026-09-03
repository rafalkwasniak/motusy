<?php

namespace App\Models;

use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property int $seq
 * @property int $duration_s
 * @property int|null $recorded_at
 * @property float $lean_left_deg
 * @property float $lean_right_deg
 * @property float $accel_g
 * @property float $brake_g
 * @property float|null $speed_kmh
 * @property string $fw
 * @property bool $calibrated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
// Komplet pól z przesyłki (kontrakt telemetrii §3) plus właściciel.
// Bez tego `updateOrCreate` z kontraktu §7 nie ma czego zapisać.
#[Fillable([
    'user_id',
    'device_id',
    'seq',
    'duration_s',
    'recorded_at',
    'lean_left_deg',
    'lean_right_deg',
    'accel_g',
    'brake_g',
    'speed_kmh',
    'fw',
    'calibrated',
])]
class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'duration_s' => 'integer',
            'recorded_at' => 'integer',
            'lean_left_deg' => 'float',
            'lean_right_deg' => 'float',
            'accel_g' => 'float',
            'brake_g' => 'float',
            'speed_kmh' => 'float',
            'calibrated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    /**
     * Nazwa urządzenia, które zapisało ten przejazd.
     *
     * Przejazd wiąże z urządzeniem `device_id` z przesyłki, a nie klucz obcy,
     * więc wpis w `devices` teoretycznie może nie istnieć — wtedy pokazujemy
     * sam fabryczny identyfikator.
     */
    public function deviceName(): string
    {
        return $this->device?->displayName() ?? $this->device_id;
    }

    /**
     * Moment zakończenia przejazdu, przeliczony na strefę prezentacji.
     *
     * GPS podaje czas w UTC i taki uniksowy znacznik trafia do bazy, więc bez
     * przeliczenia panel pokazywałby jazdę o dwie godziny wcześniej, niż była.
     * Gdy urządzenie nie złapało pozycji, `recorded_at` przychodzi puste
     * i daty po prostu nie pokazujemy.
     */
    public function recordedAt(): ?Carbon
    {
        return $this->recorded_at !== null
            ? Carbon::createFromTimestamp($this->recorded_at, config('app.display_timezone'))
            : null;
    }

    /**
     * Brak pomiaru prędkości to nie zero. Bez GPS-a urządzenie przysyła
     * `null` i ekran pokazuje „---" — portal ma robić to samo.
     */
    public function hasSpeed(): bool
    {
        return $this->speed_kmh !== null;
    }

    /**
     * Czas trwania w formacie „1 h 12 min" / „8 min".
     */
    public function durationForHumans(): string
    {
        $minutes = intdiv($this->duration_s, 60);

        if ($minutes < 60) {
            return __(':count min', ['count' => $minutes]);
        }

        return __(':hours h :minutes min', [
            'hours' => intdiv($minutes, 60),
            'minutes' => $minutes % 60,
        ]);
    }
}

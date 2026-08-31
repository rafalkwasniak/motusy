<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property string|null $name
 * @property string $type
 * @property string|null $fw
 * @property bool|null $calibrated
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// `name` nadaje właściciel w panelu, resztę przysyła samo urządzenie —
// kontroler telemetrii zapisuje je przez updateOrCreate.
#[Fillable(['user_id', 'device_id', 'name', 'type', 'fw', 'calibrated', 'last_seen_at'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'calibrated' => 'boolean',
            'last_seen_at' => 'datetime',
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
     * Przejazdy wiąże `device_id` z przesyłki, nie klucz obcy — bo to
     * urządzenie decyduje, czym się przedstawia.
     *
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class, 'device_id', 'device_id');
    }

    /**
     * Nazwa do pokazania: nadana przez właściciela, a gdy jej nie ma —
     * fabryczny identyfikator układu.
     */
    public function displayName(): string
    {
        return filled($this->name) ? $this->name : $this->device_id;
    }
}

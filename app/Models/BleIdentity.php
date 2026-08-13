<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['token', 'active', 'expires_at'])]
class BleIdentity extends Model
{
    use HasFactory;

    protected $table = 'ble_identities';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A token can be turned back into a person while it is the one being broadcast,
     * and for a grace period after it was retired — otherwise a meeting recorded
     * offline and sent a day later would resolve to nobody.
     */
    public function scopeResolvable(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('active', true)
                ->orWhere('expires_at', '>', now());
        });
    }
}

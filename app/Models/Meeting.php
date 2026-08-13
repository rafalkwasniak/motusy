<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['met_user_id', 'latitude', 'longitude', 'detected_at', 'confirmed', 'event_id'])]
class Meeting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'detected_at' => 'datetime',
            'confirmed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'met_user_id');
    }

    /**
     * What the owner of this record sees. The card is rendered from their point of
     * view, so the other person's hidden fields stay hidden.
     */
    public function card(User $viewer): array
    {
        return [
            'id' => $this->id,
            'detected_at' => $this->detected_at->toIso8601String(),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'user' => $this->metUser->card($viewer),
        ];
    }
}

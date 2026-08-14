<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_a_id', 'user_b_id', 'detected_at', 'latitude', 'longitude'])]
class Meeting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'detected_at' => 'datetime',
        ];
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MeetingReport::class);
    }

    /**
     * The pair is stored unordered, so both columns have to be checked. Callers never
     * need to know which side of the row they landed on.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id);
        });
    }

    /**
     * Both participants, in the order the pair is stored. Ordering the ids is what
     * makes a lookup by pair a single condition instead of two.
     *
     * @return array{0: int, 1: int}
     */
    public static function pair(int $first, int $second): array
    {
        return $first < $second ? [$first, $second] : [$second, $first];
    }

    public function otherParty(User $viewer): User
    {
        return $this->user_a_id === $viewer->id ? $this->userB : $this->userA;
    }

    /**
     * Rendered from the asking rider's point of view, so the other person's hidden
     * fields stay hidden. The shape is the same whichever side of the row they are on.
     */
    public function card(User $viewer): array
    {
        return [
            'id' => $this->id,
            'detected_at' => $this->detected_at->toIso8601String(),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'user' => $this->otherParty($viewer)->card($viewer),
        ];
    }
}

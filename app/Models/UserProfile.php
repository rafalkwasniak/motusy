<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nickname',
    'gender',
    'first_name',
    'last_name',
    'avatar',
    'bio',
    'phone',
    'phone_visible',
    'email_visible',
    'first_name_visible',
    'last_name_visible',
])]
class UserProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'phone_visible' => 'boolean',
            'email_visible' => 'boolean',
            'first_name_visible' => 'boolean',
            'last_name_visible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

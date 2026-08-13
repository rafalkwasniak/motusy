<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['email', 'password', 'incognito'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function motorcycle(): HasOne
    {
        return $this->hasOne(Motorcycle::class);
    }

    public function bleIdentities(): HasMany
    {
        return $this->hasMany(BleIdentity::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * How this user looks to somebody else. The key set never changes: fields the
     * owner keeps private come back as null rather than disappearing, so the client
     * never has to tell "hidden" apart from "not in this response".
     *
     * Null-safe on purpose — a freshly registered account has no profile yet.
     */
    public function card(?self $viewer = null): array
    {
        $profile = $this->profile;
        $isSelf = $viewer !== null && $viewer->id === $this->id;

        $visible = fn (?string $value, bool $flag) => $isSelf || $flag ? $value : null;

        return [
            'id' => $this->id,
            'nickname' => $profile?->nickname,
            'gender' => $profile?->gender,
            'first_name' => $visible($profile?->first_name, (bool) $profile?->first_name_visible),
            'last_name' => $visible($profile?->last_name, (bool) $profile?->last_name_visible),
            'avatar' => $this->fileUrl($profile?->avatar),
            'bio' => $profile?->bio,
            'phone' => $visible($profile?->phone, (bool) $profile?->phone_visible),
            'email' => $visible($this->email, (bool) $profile?->email_visible),
            'motorcycle' => $this->motorcycleCard(),
        ];
    }

    /**
     * The owner's own account view: everything from the card plus account-level state
     * the app needs to decide what screen to show first.
     */
    public function account(): array
    {
        return [
            ...$this->card($this),
            'incognito' => $this->incognito,
            'profile_complete' => $this->hasCompleteProfile(),
            'visibility' => [
                'first_name_visible' => (bool) $this->profile?->first_name_visible,
                'last_name_visible' => (bool) $this->profile?->last_name_visible,
                'phone_visible' => (bool) $this->profile?->phone_visible,
                'email_visible' => (bool) $this->profile?->email_visible,
            ],
        ];
    }

    /**
     * Onboarding is finished once the mandatory profile and motorcycle fields exist.
     */
    public function hasCompleteProfile(): bool
    {
        return $this->profile !== null && $this->motorcycle !== null;
    }

    private function motorcycleCard(): array
    {
        $motorcycle = $this->motorcycle;

        return [
            'brand' => $motorcycle?->brand,
            'model' => $motorcycle?->model,
            'production_year' => $motorcycle?->production_year,
            'color' => $motorcycle?->color,
            'description' => $motorcycle?->description,
            'photo' => $this->fileUrl($motorcycle?->photo),
        ];
    }

    /**
     * The database stores a relative path; clients get an absolute URL. Keeping the
     * path in the column means moving domain or disk does not require a data fix.
     */
    private function fileUrl(?string $path): ?string
    {
        return $path === null
            ? null
            : Storage::disk(config('motusy.uploads.disk'))->url($path);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'incognito' => 'boolean',
        ];
    }
}

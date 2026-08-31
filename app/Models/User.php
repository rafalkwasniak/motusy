<?php

namespace App\Models;

use App\Support\AccountToken;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $nickname
 * @property string|null $name
 * @property string $email
 * @property string|null $api_token
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nickname', 'name', 'email', 'password'])]
#[Hidden(['password', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
        ];
    }

    /**
     * Urządzenia przypisane do konta. Jedno konto obsługuje wiele pudełek,
     * także różnych rodzajów — patrz CLAUDE.md §1.
     *
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * Wydaje nowy token konta i unieważnia poprzedni.
     *
     * Pudełka wpisane starym tokenem dostaną 401 i — zgodnie z kontraktem
     * telemetrii §3 — przestaną próbować, aż ktoś przepisze im nowy.
     */
    public function regenerateApiToken(): string
    {
        $this->forceFill(['api_token' => AccountToken::generate()])->save();

        return $this->api_token;
    }

    /**
     * Nazwa pokazywana w portalu. Imię i nazwisko są prywatne i nie
     * wychodzą na zewnątrz — publicznie widać wyłącznie nick.
     */
    public function displayName(): string
    {
        return $this->nickname ?? Str::before($this->email, '@');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->displayName(), true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}

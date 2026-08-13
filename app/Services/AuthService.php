<?php

namespace App\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(string $email, string $password, ?string $deviceName): array
    {
        $user = User::create([
            'email' => $email,
            'password' => $password,
        ]);

        return $this->issueToken($user, $deviceName);
    }

    /**
     * @throws InvalidCredentialsException
     */
    public function login(string $email, string $password, ?string $deviceName): array
    {
        $user = User::where('email', $email)->first();

        // One indistinguishable failure for both a missing account and a wrong
        // password, so the response cannot be used to check whether an email is
        // registered.
        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        return $this->issueToken($user, $deviceName);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function issueToken(User $user, ?string $deviceName): array
    {
        $name = $deviceName ?: config('motusy.auth.default_token_name');

        return [
            'token' => $user->createToken($name)->plainTextToken,
            'user' => $user->fresh()->account(),
        ];
    }
}

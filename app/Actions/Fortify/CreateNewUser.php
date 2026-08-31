<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->registrationRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'nickname' => $input['nickname'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Token konta powstaje razem z kontem i jest widoczny w panelu
        // od pierwszego zalogowania. Nadawany poza `create()`, bo celowo
        // nie jest polem masowo przypisywalnym.
        $user->regenerateApiToken();

        return $user;
    }
}

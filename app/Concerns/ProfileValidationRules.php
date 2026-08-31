<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Reguły formularza rejestracji.
     *
     * Nie pytamy o imię i nazwisko — to dane prywatne, uzupełniane
     * dobrowolnie w profilu. Przy zakładaniu konta potrzebny jest tylko
     * nick, którym użytkownik będzie się pokazywał.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function registrationRules(): array
    {
        return [
            'nickname' => $this->nicknameRules(),
            'email' => $this->emailRules(),
        ];
    }

    /**
     * Reguły edycji profilu — tu imię i nazwisko wolno podać, ale nie trzeba.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'nickname' => $this->nicknameRules($userId),
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Nick jest widoczny publicznie, więc musi być unikalny i bez spacji.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nicknameRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:30',
            'regex:/^[\pL\pN._-]+$/u',
            $userId === null
                ? Rule::unique(User::class, 'nickname')
                : Rule::unique(User::class, 'nickname')->ignore($userId),
        ];
    }

    /**
     * Imię i nazwisko — dane prywatne, pole opcjonalne.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}

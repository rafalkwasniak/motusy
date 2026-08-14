<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncognitoRequest extends FormRequest
{
    /**
     * Deliberately its own endpoint rather than a field on the profile: saving the
     * profile requires a nickname and a gender, so a switch in the interface would
     * have to send the whole form and would fail for anyone mid-onboarding.
     */
    public function rules(): array
    {
        return [
            'incognito' => ['required', 'boolean'],
        ];
    }
}

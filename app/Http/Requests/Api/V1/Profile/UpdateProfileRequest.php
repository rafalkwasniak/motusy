<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * The endpoint doubles as create and update, so the mandatory fields are always
     * required: a profile cannot exist without a nickname and a gender.
     */
    public function rules(): array
    {
        return [
            'nickname' => [
                'required', 'string',
                'min:'.config('motusy.profile.nickname_min_length'),
                'max:'.config('motusy.profile.nickname_max_length'),
            ],
            'gender' => ['required', 'string', Rule::in(config('motusy.profile.genders'))],

            'first_name' => ['nullable', 'string', 'max:'.config('motusy.profile.name_max_length')],
            'last_name' => ['nullable', 'string', 'max:'.config('motusy.profile.name_max_length')],
            'bio' => ['nullable', 'string', 'max:'.config('motusy.profile.bio_max_length')],
            'phone' => ['nullable', 'string', 'max:'.config('motusy.profile.phone_max_length')],

            'first_name_visible' => ['sometimes', 'boolean'],
            'last_name_visible' => ['sometimes', 'boolean'],
            'phone_visible' => ['sometimes', 'boolean'],
            'email_visible' => ['sometimes', 'boolean'],
        ];
    }
}

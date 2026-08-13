<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                // "image" is the one that matters: it inspects the file itself rather
                // than trusting the declared type or the name.
                'image',
                'mimes:'.implode(',', config('motusy.uploads.mimes')),
                'max:'.config('motusy.uploads.max_kilobytes'),
            ],
        ];
    }
}

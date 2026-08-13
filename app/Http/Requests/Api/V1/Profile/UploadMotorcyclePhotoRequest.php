<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadMotorcyclePhotoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:'.implode(',', config('motusy.uploads.mimes')),
                'max:'.config('motusy.uploads.max_kilobytes'),
            ],
        ];
    }
}

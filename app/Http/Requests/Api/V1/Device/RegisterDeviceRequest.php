<?php

namespace App\Http\Requests\Api\V1\Device;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['required', 'string', Rule::in(['ios', 'android'])],
            'app_version' => ['nullable', 'string', 'max:30'],
            'push_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}

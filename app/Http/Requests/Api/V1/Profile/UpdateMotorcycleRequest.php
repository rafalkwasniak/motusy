<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMotorcycleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:'.config('motusy.motorcycle.brand_max_length')],
            'model' => ['required', 'string', 'max:'.config('motusy.motorcycle.model_max_length')],
            'production_year' => [
                'required', 'integer',
                'min:'.config('motusy.motorcycle.min_production_year'),
                // Next year's models reach dealers before the year turns.
                'max:'.((int) date('Y') + 1),
            ],
            'color' => ['required', 'string', 'max:'.config('motusy.motorcycle.color_max_length')],
            'description' => ['nullable', 'string', 'max:'.config('motusy.motorcycle.description_max_length')],
        ];
    }
}

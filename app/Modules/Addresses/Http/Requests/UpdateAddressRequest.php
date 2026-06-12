<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:50'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'city_code' => ['nullable', 'string', 'max:30'],
            'state' => ['required', 'string', 'max:100'],
            'zipcode' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'state_registration' => ['nullable', 'string', 'max:80'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}

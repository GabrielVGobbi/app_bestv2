<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'name' => ['required', 'string', 'max:255'],
            #'slug' => ['required', 'string', 'max:255', 'unique:roles,slug,'.$this->route('role')?->id],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

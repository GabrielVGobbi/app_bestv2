<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteNotificationActionRequest extends FormRequest
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
            'action_type' => ['required', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
            'payload.*' => ['nullable'],
        ];
    }
}

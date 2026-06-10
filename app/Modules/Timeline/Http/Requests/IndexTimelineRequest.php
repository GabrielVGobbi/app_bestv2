<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTimelineRequest extends FormRequest
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
            'cursor' => ['nullable', 'string', 'max:255'],
            'before' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'types' => ['nullable', 'array'],
            'types.*' => ['required', Rule::in(['activity', 'comment', 'system'])],
        ];
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 50);
    }
}

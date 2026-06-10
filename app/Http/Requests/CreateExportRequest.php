<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExportRequest extends FormRequest
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
            'model_class' => ['required', 'string', Rule::in(config('exports.allowed_models'))],
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx', 'tsv'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
            'filters' => ['nullable', 'array'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:100'],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

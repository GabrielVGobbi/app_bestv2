<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUploadRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:50'],
            'collection' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}

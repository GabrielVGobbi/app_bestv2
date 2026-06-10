<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUploadRequest extends FormRequest
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
        $aliases = array_keys((array) config('uploads.aliases', []));

        return [
            'parentable_type' => ['required', 'string', Rule::in($aliases)],
            'parentable_id' => ['required'],
            'tag' => ['nullable', 'string', 'max:50'],
            'collection' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUploadRequest extends FormRequest
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
        $maxKb = (int) config('uploads.max_size_mb', 50) * 1024;

        return [
            'parentable_type' => ['required', 'string', Rule::in($aliases)],
            'parentable_id' => ['required'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', "max:{$maxKb}"],
            'name' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:50'],
            'collection' => ['nullable', 'string', 'max:100'],
            'is_cover' => ['nullable', 'boolean'],
        ];
    }
}

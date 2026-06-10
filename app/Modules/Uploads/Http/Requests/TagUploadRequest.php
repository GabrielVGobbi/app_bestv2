<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagUploadRequest extends FormRequest
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
        ];
    }
}

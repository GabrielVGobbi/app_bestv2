<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyUploadRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'exists:uploadeds,id'],
        ];
    }
}

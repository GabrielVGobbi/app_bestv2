<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreZipRequestRequest extends FormRequest
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
            'ids' => ['required_without:all_matching', 'prohibits:all_matching', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'exists:uploadeds,id'],
            'all_matching' => ['required_without:ids', 'prohibits:ids', 'array:parentable_type,parentable_id,tag,collection,search', 'min:1'],
            'all_matching.parentable_type' => ['required_with:all_matching', 'string', Rule::in(array_keys((array) config('uploads.aliases', [])))],
            'all_matching.parentable_id' => ['required_with:all_matching', function (string $attribute, mixed $value, Closure $fail): void {
                if (! ((is_int($value) && $value > 0) || (is_string($value) && (Str::isUuid($value) || (ctype_digit($value) && (int) $value > 0))))) {
                    $fail('O registro de destino deve ser um identificador válido.');
                }
            }],
            'all_matching.tag' => ['nullable', 'string', 'max:50'],
            'all_matching.collection' => ['nullable', 'string', 'max:100'],
            'all_matching.search' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}

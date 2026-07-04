<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Requests\Company;

use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Company::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:255'],
            'cnpj' => [
                'required',
                'string',
                'size:14',
                Rule::unique('companies', 'cnpj')->whereNull('deleted_at'),
            ],
            'fiscal_metadata' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $cnpj = preg_replace('/\D+/', '', (string) $this->input('cnpj', '')) ?: '';
        $metadata = $this->normalizeFiscalMetadata($this->input('fiscal_metadata'), $cnpj);

        $this->merge([
            'cnpj' => $cnpj,
            'fiscal_metadata' => $metadata,
        ]);
    }

    private function normalizeFiscalMetadata(mixed $metadata, string $cnpj): mixed
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $metadata;
            }

            $metadata = $decoded;
        }

        if (! is_array($metadata)) {
            return $metadata;
        }

        $metadata['cpfCnpj'] = $cnpj;

        return $metadata;
    }
}

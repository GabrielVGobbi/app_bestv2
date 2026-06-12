<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests\Service;

use App\Modules\Services\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Service::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', Rule::in(['BRL'])],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'metadata.requires_period' => ['sometimes', 'boolean'],
            'metadata.requires_asset_reference' => ['sometimes', 'boolean'],
            'metadata.delivery_mode' => ['nullable', 'string', Rule::in(['manual', 'digital', 'operational', 'external'])],
            'metadata.default_notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_price' => $this->normalizeBasePrice($this->input('base_price', 0)),
            'currency' => $this->input('currency', 'BRL') ?: 'BRL',
            'is_active' => $this->boolean('is_active', true),
            'metadata' => $this->normalizeMetadata($this->input('metadata')),
        ]);
    }

    private function normalizeBasePrice(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value * 100);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        $normalized = str_replace(['R$', ' '], '', $value);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return -1;
        }

        return (int) round(((float) $normalized) * 100);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['delivery_mode' => '__invalid__'];
            }

            $metadata = $decoded;
        }

        if (! is_array($metadata)) {
            return null;
        }

        return [
            'requires_period' => filter_var($metadata['requires_period'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'requires_asset_reference' => filter_var($metadata['requires_asset_reference'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'delivery_mode' => $metadata['delivery_mode'] ?? 'manual',
            'default_notes' => $metadata['default_notes'] ?? null,
        ];
    }
}

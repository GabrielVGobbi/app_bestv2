<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\PlaylistTop;

use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlaylistTopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attach', PlaylistTop::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'top_id' => ['required', 'integer', Rule::exists('tops', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'slots_quantity' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', Rule::in(['BRL'])],
            'metrics' => ['nullable', 'array'],
            'metrics.estimated_streams' => ['nullable', 'integer', 'min:0'],
            'metrics.estimated_reach' => ['nullable', 'integer', 'min:0'],
            'metrics.average_retention_days' => ['nullable', 'integer', 'min:0'],
            'metrics.commercial_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $playlist = $this->route('playlist');

                if (! $playlist instanceof Playlist || $this->integer('top_id') <= 0) {
                    return;
                }

                $exists = PlaylistTop::query()
                    ->whereBelongsTo($playlist)
                    ->where('top_id', $this->integer('top_id'))
                    ->where('is_active', true)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('top_id', 'Esta playlist já possui este TOP ativo.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $validated = $this->validated();
        $top = Top::query()->findOrFail($validated['top_id']);

        return [
            'top_id' => $top->id,
            'slots_quantity' => $validated['slots_quantity'] ?? $top->default_slots_quantity,
            'occupied_slots_count' => 0,
            'price' => $validated['price'],
            'currency' => 'BRL',
            'metrics' => $validated['metrics'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price' => $this->normalizePrice($this->input('price', 0)),
            'currency' => 'BRL',
            'metrics' => $this->normalizeMetrics($this->input('metrics')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    protected function normalizePrice(mixed $value): int
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

    /** @return array<string, int>|null */
    protected function normalizeMetrics(mixed $metrics): ?array
    {
        if ($metrics === null || $metrics === '') {
            return null;
        }

        if (is_string($metrics)) {
            $decoded = json_decode($metrics, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            $metrics = $decoded;
        }

        if (! is_array($metrics)) {
            return null;
        }

        return collect([
            'estimated_streams',
            'estimated_reach',
            'average_retention_days',
            'commercial_score',
        ])
            ->mapWithKeys(fn (string $key): array => [$key => $metrics[$key] ?? null])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }
}

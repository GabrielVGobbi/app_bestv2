<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\Top;

use App\Modules\Playlists\Models\Top;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Top::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'default_slots_quantity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'default_slots_quantity' => 'quantidade padrão de vagas',
            'description' => 'descrição',
            'sort_order' => 'ordem',
            'is_active' => 'ativo',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return collect($this->validated())
            ->only(['name', 'default_slots_quantity', 'description', 'sort_order', 'is_active'])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'default_slots_quantity' => (int) $this->input('default_slots_quantity', 0),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}

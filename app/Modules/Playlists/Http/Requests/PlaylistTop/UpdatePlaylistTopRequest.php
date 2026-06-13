<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\PlaylistTop;

use App\Modules\Playlists\Models\PlaylistTop;
use Illuminate\Validation\Validator;

class UpdatePlaylistTopRequest extends StorePlaylistTopRequest
{
    public function authorize(): bool
    {
        $playlistTop = $this->route('playlistTop');

        return $playlistTop instanceof PlaylistTop
            && ($this->user()?->can('configure', $playlistTop) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['top_id']);
        $rules['slots_quantity'] = ['required', 'integer', 'min:1'];

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $playlistTop = $this->route('playlistTop');

                if (! $playlistTop instanceof PlaylistTop) {
                    return;
                }

                if ($this->integer('slots_quantity') < $playlistTop->occupied_slots_count) {
                    $validator->errors()->add(
                        'slots_quantity',
                        'A capacidade não pode ser menor que a ocupação atual.',
                    );
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'slots_quantity' => $validated['slots_quantity'],
            'price' => $validated['price'],
            'currency' => 'BRL',
            'metrics' => $validated['metrics'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }
}

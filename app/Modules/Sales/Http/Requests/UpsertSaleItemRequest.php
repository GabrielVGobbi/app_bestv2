<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertSaleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');
        $saleItem = $this->route('saleItem');
        $permission = $saleItem instanceof SaleItem ? 'sales.items.update' : 'sales.items.create';
        $user = $this->user();

        return $sale instanceof Sale
            && $sale->isEditable()
            && $user instanceof User
            && $user->can('update', $sale)
            && $this->hasPermission($user, $permission);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['service', 'playlist_slot'])],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'playlist_top_id' => ['nullable', 'integer', Rule::exists('playlist_tops', 'id')],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit_amount' => ['nullable', 'integer', 'min:0'],
            'discount_amount' => ['sometimes', 'integer', 'min:0'],
            'track_spotify_url' => ['nullable', 'string', 'max:500'],
            'track_spotify_id' => ['nullable', 'string', 'max:100'],
            'track_name' => ['nullable', 'string', 'max:255'],
            'track_artists' => ['nullable', 'array'],
            'track_artists.*' => ['string', 'max:255'],
            'track_album_name' => ['nullable', 'string', 'max:255'],
            'track_duration_ms' => ['nullable', 'integer', 'min:0'],
            'track_isrc' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === 'service' && ! $this->filled('service_id')) {
                    $validator->errors()->add('service_id', __('Informe o serviço vendido.'));
                }

                if ($this->input('type') === 'playlist_slot') {
                    if (! $this->filled('playlist_top_id')) {
                        $validator->errors()->add('playlist_top_id', __('Informe o TOP da playlist.'));
                    }

                    if (! $this->filled('track_name')) {
                        $validator->errors()->add('track_name', __('Informe a música da vaga de playlist.'));
                    }

                    if (! $this->filled('track_spotify_url') && ! $this->filled('track_spotify_id')) {
                        $validator->errors()->add('track_spotify_url', __('Informe uma referência Spotify.'));
                    }
                }
            },
        ];
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}

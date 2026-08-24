<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Support\SpotifyTrackInputParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale && ($this->user()?->can('update', $sale) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'discount_amount' => ['sometimes', 'integer', 'min:0'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*' => ['array:uuid,type,service_id,playlist_top_id,quantity,unit_amount,starts_on,ends_on,track_spotify_url,track_name,track_artists,track_album_name,track_duration_ms,track_isrc,track_image_url,track_metadata'],
            'items.*.uuid' => ['nullable', 'string'],
            'items.*.type' => ['required_with:items', Rule::in(['service', 'playlist_slot'])],
            'items.*.service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'items.*.playlist_top_id' => [
                'nullable',
                'integer',
                Rule::exists('playlist_tops', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.starts_on' => ['nullable', 'date'],
            'items.*.ends_on' => ['nullable', 'date', 'after_or_equal:items.*.starts_on'],
            'items.*.track_spotify_url' => ['nullable', 'string', 'max:500'],
            'items.*.track_name' => ['nullable', 'string', 'max:255'],
            'items.*.track_artists' => ['nullable', 'array'],
            'items.*.track_artists.*' => ['string', 'max:255'],
            'items.*.track_album_name' => ['nullable', 'string', 'max:255'],
            'items.*.track_duration_ms' => ['nullable', 'integer', 'min:0'],
            'items.*.track_isrc' => ['nullable', 'string', 'max:50'],
            'items.*.track_image_url' => ['nullable', 'url', 'max:500'],
            'items.*.track_metadata' => ['nullable', 'array'],
            'items.*.track_metadata.image_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('discount_amount')) {
            $this->merge([
                'discount_amount' => $this->normalizeAmount($this->input('discount_amount')),
            ]);
        }

        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item) || blank($item['track_image_url'] ?? null)) {
                continue;
            }

            $items[$index]['track_metadata'] = [
                ...(is_array($item['track_metadata'] ?? null) ? $item['track_metadata'] : []),
                'image_url' => $item['track_image_url'],
            ];
        }

        $this->merge(['items' => $items]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sale = $this->route('sale');

                if (! $sale instanceof Sale) {
                    return;
                }

                if (! $sale->isEditable()) {
                    $validator->errors()->add('status', __('A venda não pode ser editada neste status.'));
                }

                if ($this->has('discount_amount') && $this->integer('discount_amount') > $sale->subtotal_amount) {
                    $validator->errors()->add('discount_amount', __('O desconto não pode ser maior que o subtotal da venda.'));
                }
                $this->validateItems($validator, $sale);
            },
        ];
    }

    private function validateItems(Validator $validator, Sale $sale): void
    {
        if (! $this->has('items')) {
            return;
        }

        $spotifyTrackInputParser = app(SpotifyTrackInputParser::class);

        foreach ($this->input('items', []) as $index => $item) {
            if (($item['type'] ?? null) === 'service' && blank($item['service_id'] ?? null)) {
                $validator->errors()->add("items.{$index}.service_id", __('Informe o serviço vendido.'));
            }

            if (blank($item['starts_on'] ?? null) !== blank($item['ends_on'] ?? null)) {
                $validator->errors()->add("items.{$index}.ends_on", __('Informe o período completo (início e fim) ou deixe as duas datas vazias.'));
            }

            if (($item['type'] ?? null) !== 'playlist_slot') {
                continue;
            }

            if (blank($item['playlist_top_id'] ?? null)) {
                $validator->errors()->add("items.{$index}.playlist_top_id", __('Informe o TOP da playlist.'));
            }

            if (blank($item['track_name'] ?? null)) {
                $validator->errors()->add("items.{$index}.track_name", __('Informe a música da vaga de playlist.'));
            }

            if (blank($item['track_spotify_url'] ?? null)) {
                $validator->errors()->add("items.{$index}.track_spotify_url", __('Informe uma referência Spotify.'));

                continue;
            }

            if ($spotifyTrackInputParser->parse($item['track_spotify_url']) === null) {
                $validator->errors()->add("items.{$index}.track_spotify_url", __('Informe um link, URI ou ID válido de música do Spotify.'));
            }
        }

        $this->validateItemPermissions($validator, $sale);
    }

    private function validateItemPermissions(Validator $validator, Sale $sale): void
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return;
        }

        $items = $this->input('items', []);
        $itemUuids = collect($items)
            ->pluck('uuid')
            ->filter()
            ->map(fn (mixed $uuid): string => (string) $uuid)
            ->values();
        $currentItemUuids = $sale->saleItems()->pluck('uuid');

        if ($itemUuids->count() < count($items) && ! $this->hasPermission($user, 'sales.items.create')) {
            $validator->errors()->add('items', __('Você não tem permissão para adicionar itens nesta venda.'));
        }

        if ($itemUuids->isNotEmpty() && ! $this->hasPermission($user, 'sales.items.update')) {
            $validator->errors()->add('items', __('Você não tem permissão para atualizar itens nesta venda.'));
        }

        if ($currentItemUuids->diff($itemUuids)->isNotEmpty() && ! $this->hasPermission($user, 'sales.items.delete')) {
            $validator->errors()->add('items', __('Você não tem permissão para remover itens desta venda.'));
        }
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }

    private function normalizeAmount(mixed $value): int
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

        return preg_match('/^\d+$/', $value) === 1 ? (int) $value : -1;
    }
}

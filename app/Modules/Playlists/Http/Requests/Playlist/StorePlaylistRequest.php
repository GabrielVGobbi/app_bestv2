<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\Playlist;

use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Support\SpotifyPlaylistInputParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StorePlaylistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Playlist::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'status' => ['required', Rule::enum(PlaylistStatus::class)],
            'music_genre_id' => ['required', 'integer', Rule::exists('music_genres', 'id')],
            'subgenre_ids' => ['nullable', 'array'],
            'subgenre_ids.*' => ['integer', 'distinct', Rule::exists('music_subgenres', 'id')],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'spotify_playlist_id' => [
                'nullable',
                'string',
                'max:255',
                $this->spotifyPlaylistUniqueRule(),
            ],
            'spotify_url' => ['nullable', 'url', 'max:255'],
            'spotify_name' => ['nullable', 'string', 'max:255'],
            'spotify_description' => ['nullable', 'string'],
            'spotify_public' => ['nullable', 'boolean'],
            'spotify_image_url' => ['nullable', 'url', 'max:255'],
            'spotify_cover_upload_id' => ['nullable', 'integer', Rule::exists('uploadeds', 'id')],
            'spotify_snapshot_id' => ['nullable', 'string', 'max:255'],
            'spotify_owner_id' => ['nullable', 'string', 'max:255'],
            'spotify_owner_name' => ['nullable', 'string', 'max:255'],
            'spotify_tracks_total' => ['nullable', 'integer', 'min:0'],
            'spotify_last_synced_at' => ['nullable', 'date'],
            'spotify_sync_priority' => ['nullable', 'string', Rule::in(['high', 'normal', 'low', 'paused'])],
            'spotify_sync_interval_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'next_spotify_sync_at' => ['nullable', 'date'],
            'spotify_sync_failures_count' => ['nullable', 'integer', 'min:0'],
            'spotify_sync_paused_at' => ['nullable', 'date'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'estimated_reach' => ['nullable', 'integer', 'min:0'],
            'external_metadata' => ['nullable', 'array'],
            'external_metadata.legacy_spotify_id' => ['nullable', 'string', 'max:255'],
            'external_metadata.legacy_spotify_key' => ['nullable', 'string', 'max:255'],
            'external_metadata.source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $subgenreIds = $this->array('subgenre_ids');

                if ($subgenreIds === []) {
                    return;
                }

                $invalidCount = MusicSubgenre::query()
                    ->whereIn('id', $subgenreIds)
                    ->whereDoesntHave(
                        'genres',
                        fn ($query) => $query->whereKey($this->integer('music_genre_id'))
                    )
                    ->count();

                if ($invalidCount > 0) {
                    $validator->errors()->add(
                        'subgenre_ids',
                        'Os subgêneros selecionados devem pertencer ao gênero musical principal.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'music_genre_id.required' => 'Selecione um gênero musical.',
            'spotify_playlist_id.unique' => 'Este ID público do Spotify já está cadastrado.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'status' => 'status',
            'music_genre_id' => 'gênero musical',
            'subgenre_ids' => 'subgêneros',
            'subgenre_ids.*' => 'subgênero',
            'partner_name' => 'parceiro',
            'spotify_playlist_id' => 'ID público do Spotify',
            'spotify_url' => 'link do Spotify',
            'spotify_name' => 'nome no Spotify',
            'spotify_description' => 'descrição no Spotify',
            'spotify_public' => 'visibilidade no Spotify',
            'spotify_image_url' => 'imagem no Spotify',
            'spotify_cover_upload_id' => 'capa do Spotify',
            'spotify_snapshot_id' => 'snapshot do Spotify',
            'spotify_owner_id' => 'ID do proprietário no Spotify',
            'spotify_owner_name' => 'proprietário no Spotify',
            'spotify_tracks_total' => 'total de faixas no Spotify',
            'spotify_last_synced_at' => 'última sincronização do Spotify',
            'youtube_url' => 'link do YouTube',
            'followers_count' => 'seguidores',
            'estimated_reach' => 'alcance estimado',
            'external_metadata' => 'metadados externos',
            'external_metadata.legacy_spotify_id' => 'Spotify ID legado',
            'external_metadata.legacy_spotify_key' => 'Spotify Key legado',
            'external_metadata.source' => 'origem',
            'notes' => 'observações',
        ];
    }

    /** @return array<string, mixed> */
    public function playlistPayload(): array
    {
        return collect($this->validated())
            ->except('subgenre_ids')
            ->all();
    }

    /** @return array<int, int> */
    public function subgenreIds(): array
    {
        return collect($this->validated('subgenre_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $spotifyUrl = $this->input('spotify_url');
        $spotifyPlaylistId = $this->normalizeSpotifyPlaylistId($this->input('spotify_playlist_id'), $spotifyUrl);
        $playlist = $this->route('playlist');
        $status = $this->input('status');

        if (($status === null || $status === '') && $playlist instanceof Playlist) {
            $status = $playlist->status->value;
        }

        $this->merge([
            'status' => $status ?: PlaylistStatus::Active->value,
            'subgenre_ids' => $this->normalizeSubgenres($this->input('subgenre_ids', [])),
            'spotify_playlist_id' => $spotifyPlaylistId ?: null,
            'spotify_public' => $this->normalizeNullableBoolean($this->input('spotify_public')),
            'spotify_tracks_total' => $this->normalizeNullableInteger($this->input('spotify_tracks_total')),
            'spotify_sync_priority' => $this->normalizeSyncPriority($this->input('spotify_sync_priority')),
            'spotify_sync_interval_hours' => $this->normalizeSyncIntervalHours($this->input('spotify_sync_interval_hours')),
            'spotify_sync_failures_count' => $this->normalizeNullableInteger($this->input('spotify_sync_failures_count')) ?? 0,
            'followers_count' => $this->normalizeNullableInteger($this->input('followers_count')),
            'estimated_reach' => $this->normalizeNullableInteger($this->input('estimated_reach')),
            'external_metadata' => $this->normalizeMetadata($this->input('external_metadata')),
        ]);
    }

    private function normalizeSpotifyPlaylistId(mixed $spotifyPlaylistId, mixed $spotifyUrl): ?string
    {
        $parser = app(SpotifyPlaylistInputParser::class);

        if (is_string($spotifyPlaylistId) && trim($spotifyPlaylistId) !== '') {
            $spotifyPlaylistId = trim($spotifyPlaylistId);

            return $parser->parse($spotifyPlaylistId) ?? $spotifyPlaylistId;
        }

        return $parser->parse($spotifyUrl);
    }

    /** @return array<int, mixed> */
    private function normalizeSubgenres(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_array($value) ? array_values(array_filter($value, fn (mixed $id): bool => $id !== null && $id !== '')) : [$value];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function normalizeSyncPriority(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return 'normal';
        }

        return trim($value);
    }

    private function normalizeSyncIntervalHours(mixed $value): int
    {
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return match ($this->normalizeSyncPriority($this->input('spotify_sync_priority'))) {
            'high' => 6,
            'low' => 168,
            default => 24,
        };
    }

    private function spotifyPlaylistUniqueRule(): Unique
    {
        $rule = Rule::unique('playlists', 'spotify_playlist_id');
        $playlist = $this->route('playlist');

        if ($playlist instanceof Playlist) {
            $rule->ignore($playlist);
        }

        return $rule;
    }

    /** @return array<string, mixed>|null */
    private function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            $metadata = $decoded;
        }

        if (! is_array($metadata)) {
            return null;
        }

        return array_filter([
            'legacy_spotify_id' => $metadata['legacy_spotify_id'] ?? null,
            'legacy_spotify_key' => $metadata['legacy_spotify_key'] ?? null,
            'source' => $metadata['source'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Playlists\Database\Seeders\MusicCatalogSeeder;
use App\Modules\Playlists\Database\Seeders\TopSeeder;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlaylistDeliveryTaskDemoSeeder extends Seeder
{
    private const string PREFIX = 'Fila Playlists Seeder - ';

    private const string USER_EMAIL = 'gestor.playlists.seed@bestplay.local';

    private const string CLIENT_EMAIL = 'cliente.fila.playlists.seed@bestplay.local';

    private const string PLAYLIST_SPOTIFY_ID = '6aBcDeFgHiJkLmNoPqRsTu';

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            CompanySeeder::class,
            MusicCatalogSeeder::class,
            TopSeeder::class,
        ]);

        DB::transaction(function (): void {
            $this->clearPreviousScenario();

            $user = $this->user();
            $company = Company::query()->where('nickname', 'Best Play')->firstOrFail();
            $client = $this->client($company, $user);
            $playlistTop = $this->playlistTop();

            $this->addPendingScenario($company, $client, $user, $playlistTop);
            $this->addCompletedScenario($company, $client, $user, $playlistTop);
            $this->removePendingScenario($company, $client, $user, $playlistTop);
            $this->replacePendingScenario($company, $client, $user, $playlistTop);
            $this->canceledScenario($company, $client, $user, $playlistTop);
        });

        $this->command?->info('Cenário criado para testar a Fila de playlists.');
        $this->command?->line('Login demo: '.self::USER_EMAIL.' / password');
    }

    private function clearPreviousScenario(): void
    {
        $saleIds = Sale::query()
            ->withTrashed()
            ->where('order_number', 'like', 'PLTASK-DEMO-%')
            ->pluck('id');

        if ($saleIds->isNotEmpty()) {
            PlaylistDeliveryTask::query()
                ->withTrashed()
                ->whereIn('sale_id', $saleIds)
                ->forceDelete();

            PlaylistSlot::query()
                ->withTrashed()
                ->whereIn('sale_item_id', SaleItem::query()->withTrashed()->whereIn('sale_id', $saleIds)->select('id'))
                ->forceDelete();

            SaleItem::query()->withTrashed()->whereIn('sale_id', $saleIds)->forceDelete();
            Sale::query()->withTrashed()->whereIn('id', $saleIds)->forceDelete();
        }

        $playlistIds = Playlist::query()
            ->withTrashed()
            ->where('name', 'like', self::PREFIX.'%')
            ->pluck('id');

        if ($playlistIds->isNotEmpty()) {
            PlaylistTop::query()->withTrashed()->whereIn('playlist_id', $playlistIds)->forceDelete();
            DB::table('playlist_music_subgenre')->whereIn('playlist_id', $playlistIds)->delete();
            Playlist::query()->withTrashed()->whereIn('id', $playlistIds)->forceDelete();
        }
    }

    private function user(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'Gestor de Playlists Seeder',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'must_change_password' => false,
            ],
        );

        $operation = Role::query()->where('slug', 'operation')->first();

        if ($operation instanceof Role) {
            $user->roles()->syncWithoutDetaching([$operation->id]);
        }

        return $user->load(['roles', 'permissions']);
    }

    private function client(Company $company, User $user): Client
    {
        return Client::query()->updateOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'company_id' => $company->id,
                'responsible_user_id' => $user->id,
                'name' => 'Cliente Fila Playlists Seeder',
                'company_name' => 'Cliente Fila Playlists Seeder Ltda',
                'phone' => '11990000002',
                'document' => '20000000000100',
                'musical_genre' => 'Pop',
                'is_agent' => false,
                'classification' => 'Classe A',
                'status' => 'active',
            ],
        );
    }

    private function playlistTop(): PlaylistTop
    {
        $genre = MusicGenre::query()->where('slug', 'pop')->first()
            ?? MusicGenre::query()->firstOrFail();
        $subgenre = MusicSubgenre::query()->where('slug', 'pop-nacional')->first()
            ?? $genre->subgenres()->first();
        $top = Top::query()->where('slug', 'top-10')->firstOrFail();

        $playlist = Playlist::query()
            ->withTrashed()
            ->updateOrCreate(
                ['slug' => 'fila-playlists-seeder-demo'],
                [
                    'music_genre_id' => $genre->id,
                    'name' => self::PREFIX.'Demo Operacional',
                    'status' => PlaylistStatus::Active,
                    'partner_name' => 'Curadoria BestPlay',
                    'spotify_playlist_id' => self::PLAYLIST_SPOTIFY_ID,
                    'spotify_url' => 'https://open.spotify.com/playlist/'.self::PLAYLIST_SPOTIFY_ID,
                    'followers_count' => 25000,
                    'estimated_reach' => 45000,
                    'external_metadata' => ['source' => 'playlist_delivery_task_demo_seeder'],
                    'notes' => 'Playlist demo para testar a fila operacional de playlists.',
                ],
            );

        if ($playlist->trashed()) {
            $playlist->restore();
        }

        if ($subgenre instanceof MusicSubgenre) {
            $playlist->subgenres()->sync([$subgenre->id]);
        }

        $playlistTop = PlaylistTop::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'playlist_id' => $playlist->id,
                    'top_id' => $top->id,
                ],
                [
                    'slots_quantity' => 10,
                    'occupied_slots_count' => 3,
                    'price' => 99900,
                    'currency' => 'BRL',
                    'metrics' => [
                        'estimated_streams' => 12000,
                        'estimated_reach' => 24000,
                        'average_retention_days' => 14,
                        'commercial_score' => 82,
                    ],
                    'notes' => 'TOP demo para a Fila de playlists.',
                    'is_active' => true,
                ],
            );

        if ($playlistTop->trashed()) {
            $playlistTop->restore();
        }

        return $playlistTop;
    }

    private function addPendingScenario(Company $company, Client $client, User $user, PlaylistTop $playlistTop): void
    {
        $slot = $this->saleSlot(
            orderNumber: 'PLTASK-DEMO-001',
            company: $company,
            client: $client,
            user: $user,
            playlistTop: $playlistTop,
            saleStatus: SaleStatus::CampaignStarted,
            itemStatus: SaleItemStatus::Active,
            slotStatus: PlaylistSlotStatus::Active,
            track: $this->track('4kwbbhuemSkRWvtN5dkWmb', 'Adicionar pendente', 'Artista Add'),
            startsOn: now()->toDateString(),
        );

        $this->task($slot, PlaylistDeliveryTaskType::AddTrack, PlaylistDeliveryTaskStatus::Pending);
    }

    private function addCompletedScenario(Company $company, Client $client, User $user, PlaylistTop $playlistTop): void
    {
        $slot = $this->saleSlot(
            orderNumber: 'PLTASK-DEMO-002',
            company: $company,
            client: $client,
            user: $user,
            playlistTop: $playlistTop,
            saleStatus: SaleStatus::CampaignStarted,
            itemStatus: SaleItemStatus::Active,
            slotStatus: PlaylistSlotStatus::Active,
            track: $this->track('3n3Ppam7vgaVa1iaRUc9Lp', 'Adicionar concluida', 'Artista OK'),
            startsOn: now()->subDays(3)->toDateString(),
        );

        $this->task($slot, PlaylistDeliveryTaskType::AddTrack, PlaylistDeliveryTaskStatus::Completed, completedBy: $user);
    }

    private function removePendingScenario(Company $company, Client $client, User $user, PlaylistTop $playlistTop): void
    {
        $slot = $this->saleSlot(
            orderNumber: 'PLTASK-DEMO-003',
            company: $company,
            client: $client,
            user: $user,
            playlistTop: $playlistTop,
            saleStatus: SaleStatus::CampaignFinished,
            itemStatus: SaleItemStatus::Finished,
            slotStatus: PlaylistSlotStatus::Finished,
            track: $this->track('0VjIjW4GlUZAMYd2vXMi3b', 'Remover pendente', 'Artista Remove'),
            startsOn: now()->subMonth()->toDateString(),
        );

        $this->task($slot, PlaylistDeliveryTaskType::RemoveTrack, PlaylistDeliveryTaskStatus::Pending);
    }

    private function replacePendingScenario(Company $company, Client $client, User $user, PlaylistTop $playlistTop): void
    {
        $oldTrack = $this->track('7ouMYWpwJ422jRcDASZB7P', 'Musica antiga para trocar', 'Artista Antigo');
        $newTrack = $this->track('2takcwOaAZWiXQijPHIx7B', 'Musica nova para trocar', 'Artista Novo');
        $slot = $this->saleSlot(
            orderNumber: 'PLTASK-DEMO-004',
            company: $company,
            client: $client,
            user: $user,
            playlistTop: $playlistTop,
            saleStatus: SaleStatus::CampaignStarted,
            itemStatus: SaleItemStatus::Active,
            slotStatus: PlaylistSlotStatus::Active,
            track: $newTrack,
            startsOn: now()->subDays(10)->toDateString(),
        );

        $this->task($slot, PlaylistDeliveryTaskType::AddTrack, PlaylistDeliveryTaskStatus::Completed, completedBy: $user, toTrack: $oldTrack);
        $this->task($slot, PlaylistDeliveryTaskType::ReplaceTrack, PlaylistDeliveryTaskStatus::Pending, fromTrack: $oldTrack, toTrack: $newTrack);
    }

    private function canceledScenario(Company $company, Client $client, User $user, PlaylistTop $playlistTop): void
    {
        $slot = $this->saleSlot(
            orderNumber: 'PLTASK-DEMO-005',
            company: $company,
            client: $client,
            user: $user,
            playlistTop: $playlistTop,
            saleStatus: SaleStatus::CampaignFinished,
            itemStatus: SaleItemStatus::Finished,
            slotStatus: PlaylistSlotStatus::Finished,
            track: $this->track('1r9xUipOqoNwggBpENDsvJ', 'Adicao cancelada', 'Artista Cancelado'),
            startsOn: now()->subWeeks(2)->toDateString(),
        );

        $this->task(
            $slot,
            PlaylistDeliveryTaskType::AddTrack,
            PlaylistDeliveryTaskStatus::Canceled,
            canceledBy: $user,
            cancellationReason: 'Campanha finalizada antes da tarefa ser concluida.',
        );
    }

    /**
     * @param  array{name: string, spotify_id: string, spotify_url: string, artists: array<int, array{name: string}>, album_name: string, duration_ms: int, isrc: string, image_url: string}  $track
     */
    private function saleSlot(
        string $orderNumber,
        Company $company,
        Client $client,
        User $user,
        PlaylistTop $playlistTop,
        SaleStatus $saleStatus,
        SaleItemStatus $itemStatus,
        PlaylistSlotStatus $slotStatus,
        array $track,
        string $startsOn,
    ): PlaylistSlot {
        $endsOn = Carbon::parse($startsOn)->addDays(30)->toDateString();
        $now = now();

        $sale = Sale::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'seller_id' => $user->id,
            'created_by_id' => $user->id,
            'status' => $saleStatus,
            'order_number' => $orderNumber,
            'title' => self::PREFIX.$track['name'],
            'notes' => 'Venda criada pelo PlaylistDeliveryTaskDemoSeeder.',
            'subtotal_amount' => $playlistTop->price,
            'discount_amount' => 0,
            'total_amount' => $playlistTop->price,
            'currency' => 'BRL',
            'confirmed_at' => $now,
            'confirmed_by_id' => $user->id,
            'finished_at' => $saleStatus === SaleStatus::CampaignFinished ? $now : null,
            'finished_by_id' => $saleStatus === SaleStatus::CampaignFinished ? $user->id : null,
        ]);

        $item = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'type' => SaleItemType::PlaylistSlot,
            'description' => $playlistTop->playlist?->name.' - '.$playlistTop->top?->name,
            'quantity' => 1,
            'unit_amount' => $playlistTop->price,
            'discount_amount' => 0,
            'total_amount' => $playlistTop->price,
            'currency' => 'BRL',
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $itemStatus,
            'playlist_top_id' => $playlistTop->id,
            'track_spotify_id' => $track['spotify_id'],
            'track_spotify_url' => $track['spotify_url'],
            'track_name' => $track['name'],
            'track_artists' => $track['artists'],
            'track_album_name' => $track['album_name'],
            'track_duration_ms' => $track['duration_ms'],
            'track_isrc' => $track['isrc'],
            'track_metadata' => [
                'image_url' => $track['image_url'],
                'source' => 'playlist_delivery_task_demo_seeder',
            ],
            'metadata' => ['playlist_delivery_task_demo' => true],
            'created_by_id' => $user->id,
        ]);

        $slot = PlaylistSlot::query()->create([
            'playlist_top_id' => $playlistTop->id,
            'sale_item_id' => $item->id,
            'status' => $slotStatus,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'quantity' => 1,
            'track_spotify_id' => $track['spotify_id'],
            'track_spotify_url' => $track['spotify_url'],
            'track_name' => $track['name'],
            'track_artists' => $track['artists'],
            'track_album_name' => $track['album_name'],
            'track_duration_ms' => $track['duration_ms'],
            'track_isrc' => $track['isrc'],
            'track_metadata' => [
                'image_url' => $track['image_url'],
                'source' => 'playlist_delivery_task_demo_seeder',
            ],
            'notes' => 'Slot criado para testar a Fila de playlists.',
            'created_by_id' => $user->id,
        ]);

        $item->forceFill([
            'sellable_type' => $slot->getMorphClass(),
            'sellable_id' => $slot->id,
        ])->save();

        return $slot->load(['saleItem.sale.client', 'playlistTop.playlist', 'playlistTop.top']);
    }

    /**
     * @param  array{name: string, spotify_id: string, spotify_url: string, artists: array<int, array{name: string}>, album_name: string, duration_ms: int, isrc: string, image_url: string}|null  $fromTrack
     * @param  array{name: string, spotify_id: string, spotify_url: string, artists: array<int, array{name: string}>, album_name: string, duration_ms: int, isrc: string, image_url: string}|null  $toTrack
     */
    private function task(
        PlaylistSlot $slot,
        PlaylistDeliveryTaskType $type,
        PlaylistDeliveryTaskStatus $status,
        ?User $completedBy = null,
        ?User $canceledBy = null,
        ?string $cancellationReason = null,
        ?array $fromTrack = null,
        ?array $toTrack = null,
    ): PlaylistDeliveryTask {
        $slot->loadMissing(['saleItem.sale.client', 'playlistTop.playlist', 'playlistTop.top']);
        $sale = $slot->saleItem->sale;
        $playlistTop = $slot->playlistTop;

        $from = $type === PlaylistDeliveryTaskType::AddTrack ? null : ($fromTrack ?? $this->trackFromSlot($slot));
        $to = $type === PlaylistDeliveryTaskType::RemoveTrack ? null : ($toTrack ?? $this->trackFromSlot($slot));

        return PlaylistDeliveryTask::query()->create([
            'playlist_slot_id' => $slot->id,
            'sale_item_id' => $slot->sale_item_id,
            'sale_id' => $sale->id,
            'playlist_top_id' => $playlistTop->id,
            'playlist_id' => $playlistTop->playlist_id,
            'type' => $type,
            'status' => $status,
            'sale_order_number' => $sale->order_number,
            'client_name' => $sale->client?->name,
            'playlist_name' => $playlistTop->playlist?->name,
            'top_name' => $playlistTop->top?->name,
            ...$this->trackPayload('from', $from),
            ...$this->trackPayload('to', $to),
            'completed_at' => $status === PlaylistDeliveryTaskStatus::Completed ? now()->subDay() : null,
            'completed_by_id' => $status === PlaylistDeliveryTaskStatus::Completed ? $completedBy?->id : null,
            'canceled_at' => $status === PlaylistDeliveryTaskStatus::Canceled ? now()->subHours(6) : null,
            'canceled_by_id' => $status === PlaylistDeliveryTaskStatus::Canceled ? $canceledBy?->id : null,
            'cancellation_reason' => $status === PlaylistDeliveryTaskStatus::Canceled ? $cancellationReason : null,
            'metadata' => [
                'source' => 'playlist_delivery_task_demo_seeder',
                'demo' => true,
            ],
        ]);
    }

    /**
     * @return array{name: string, spotify_id: string, spotify_url: string, artists: array<int, array{name: string}>, album_name: string, duration_ms: int, isrc: string, image_url: string}
     */
    private function track(string $spotifyId, string $name, string $artist): array
    {
        return [
            'name' => self::PREFIX.$name,
            'spotify_id' => $spotifyId,
            'spotify_url' => "https://open.spotify.com/track/{$spotifyId}",
            'artists' => [['name' => $artist]],
            'album_name' => 'Album Demo Fila',
            'duration_ms' => 180000,
            'isrc' => Str::upper(Str::random(12)),
            'image_url' => 'https://i.scdn.co/image/ab67616d0000b273demo',
        ];
    }

    /**
     * @return array{name: string, spotify_id: string|null, spotify_url: string, artists: array<int, array{name: string}>|null, album_name: string|null, duration_ms: int|null, isrc: string|null, image_url: string|null}
     */
    private function trackFromSlot(PlaylistSlot $slot): array
    {
        return [
            'name' => $slot->track_name,
            'spotify_id' => $slot->track_spotify_id,
            'spotify_url' => $slot->track_spotify_url,
            'artists' => $slot->track_artists,
            'album_name' => $slot->track_album_name,
            'duration_ms' => $slot->track_duration_ms,
            'isrc' => $slot->track_isrc,
            'image_url' => is_array($slot->track_metadata) ? ($slot->track_metadata['image_url'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $track
     * @return array<string, mixed>
     */
    private function trackPayload(string $prefix, ?array $track): array
    {
        return [
            "{$prefix}_track_spotify_id" => $track['spotify_id'] ?? null,
            "{$prefix}_track_spotify_url" => $track['spotify_url'] ?? null,
            "{$prefix}_track_name" => $track['name'] ?? null,
            "{$prefix}_track_artists" => $track['artists'] ?? null,
            "{$prefix}_track_album_name" => $track['album_name'] ?? null,
            "{$prefix}_track_duration_ms" => $track['duration_ms'] ?? null,
            "{$prefix}_track_isrc" => $track['isrc'] ?? null,
            "{$prefix}_track_image_url" => $track['image_url'] ?? null,
        ];
    }
}

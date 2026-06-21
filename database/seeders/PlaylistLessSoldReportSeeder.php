<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Playlists\Database\Seeders\MusicCatalogSeeder;
use App\Modules\Playlists\Database\Seeders\TopSeeder;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
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

class PlaylistLessSoldReportSeeder extends Seeder
{
    private const PREFIX = 'RPT Seeder - ';

    private const USER_EMAIL = 'relatorio.playlists.seed@bestplay.local';

    private const CLIENT_EMAIL = 'cliente.relatorio.playlists.seed@bestplay.local';

    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            MusicCatalogSeeder::class,
            TopSeeder::class,
        ]);

        DB::transaction(function (): void {
            $this->clearPreviousScenario();

            $user = $this->user();
            $company = Company::query()->where('nickname', 'Best Play')->firstOrFail();
            $bestBranch = Company::query()->where('nickname', 'Best Branch')->firstOrFail();
            $client = $this->client($company, $user);
            $top = Top::query()->where('slug', 'top-10')->firstOrFail();

            $playlists = [
                'zero' => $this->playlist('Sem vendas no periodo'),
                'canceled' => $this->playlist('Venda cancelada'),
                'old' => $this->playlist('Venda antiga'),
                'low' => $this->playlist('Baixa venda'),
                'high' => $this->playlist('Alta venda'),
            ];

            $playlistTops = collect($playlists)
                ->map(fn (Playlist $playlist, string $key): PlaylistTop => $this->playlistTop($playlist, $top, $key));

            $this->saleWithPlaylistItem(
                orderNumber: 'RPT-PL-LESS-001',
                company: $company,
                client: $client,
                user: $user,
                playlistTop: $playlistTops['low'],
                amount: 5000,
                saleStatus: SaleStatus::CampaignStarted,
                itemStatus: SaleItemStatus::Active,
                slotStatus: PlaylistSlotStatus::Active,
                startsOn: now()->subMonth()->toDateString(),
                trackName: 'RPT Seeder - Musica baixa venda',
            );

            $this->saleWithPlaylistItem(
                orderNumber: 'RPT-PL-LESS-002',
                company: $company,
                client: $client,
                user: $user,
                playlistTop: $playlistTops['high'],
                amount: 30000,
                saleStatus: SaleStatus::CampaignStarted,
                itemStatus: SaleItemStatus::Active,
                slotStatus: PlaylistSlotStatus::Active,
                startsOn: now()->subDays(20)->toDateString(),
                trackName: 'RPT Seeder - Musica alta venda 1',
            );

            $this->saleWithPlaylistItem(
                orderNumber: 'RPT-PL-LESS-003',
                company: $company,
                client: $client,
                user: $user,
                playlistTop: $playlistTops['high'],
                amount: 12000,
                saleStatus: SaleStatus::CampaignFinished,
                itemStatus: SaleItemStatus::Finished,
                slotStatus: PlaylistSlotStatus::Finished,
                startsOn: now()->subDays(10)->toDateString(),
                trackName: 'RPT Seeder - Musica alta venda 2',
            );

            $this->saleWithPlaylistItem(
                orderNumber: 'RPT-PL-LESS-004',
                company: $company,
                client: $client,
                user: $user,
                playlistTop: $playlistTops['canceled'],
                amount: 99900,
                saleStatus: SaleStatus::Canceled,
                itemStatus: SaleItemStatus::Canceled,
                slotStatus: PlaylistSlotStatus::Canceled,
                startsOn: now()->subDays(15)->toDateString(),
                trackName: 'RPT Seeder - Musica cancelada',
            );

            $this->saleWithPlaylistItem(
                orderNumber: 'RPT-PL-LESS-005',
                company: $company,
                client: $client,
                user: $user,
                playlistTop: $playlistTops['old'],
                amount: 70000,
                saleStatus: SaleStatus::CampaignStarted,
                itemStatus: SaleItemStatus::Active,
                slotStatus: PlaylistSlotStatus::Active,
                startsOn: now()->subMonths(5)->toDateString(),
                trackName: 'RPT Seeder - Musica antiga',
            );

            $this->saleWithPlaylistItem(
                orderNumber: 'RPT-PL-LESS-006',
                company: $bestBranch,
                client: $client,
                user: $user,
                playlistTop: $playlistTops['low'],
                amount: 8000,
                saleStatus: SaleStatus::CampaignStarted,
                itemStatus: SaleItemStatus::Active,
                slotStatus: PlaylistSlotStatus::Active,
                startsOn: now()->subDays(5)->toDateString(),
                trackName: 'RPT Seeder - Musica outra empresa',
            );
        });

        $this->command?->info('Cenario criado para Playlists Menos Vendidas no app2_best_play.');
    }

    private function clearPreviousScenario(): void
    {
        $saleIds = Sale::query()
            ->where('order_number', 'like', 'RPT-PL-LESS-%')
            ->pluck('id');

        if ($saleIds->isNotEmpty()) {
            PlaylistSlot::query()
                ->whereIn('sale_item_id', SaleItem::query()->whereIn('sale_id', $saleIds)->select('id'))
                ->forceDelete();

            SaleItem::query()->whereIn('sale_id', $saleIds)->forceDelete();
            Sale::query()->whereIn('id', $saleIds)->forceDelete();
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
        return User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'Relatorio Playlists Seeder',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'must_change_password' => false,
            ],
        );
    }

    private function client(Company $company, User $user): Client
    {
        return Client::query()->updateOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'company_id' => $company->id,
                'responsible_user_id' => $user->id,
                'name' => 'Cliente Relatorio Playlists Seeder',
                'company_name' => 'Cliente Relatorio Playlists Seeder Ltda',
                'phone' => '11990000001',
                'document' => '10000000000100',
                'musical_genre' => 'Pop',
                'is_agent' => false,
                'classification' => 'Classe B',
                'status' => 'active',
            ],
        );
    }

    private function playlist(string $name): Playlist
    {
        $genre = MusicGenre::query()->where('slug', 'pop')->first()
            ?? MusicGenre::query()->firstOrFail();

        $subgenre = $genre->subgenres()->first();

        $playlist = Playlist::query()
            ->withTrashed()
            ->updateOrCreate(
                ['slug' => Str::slug(self::PREFIX.$name)],
                [
                    'music_genre_id' => $genre->id,
                    'name' => self::PREFIX.$name,
                    'status' => PlaylistStatus::Active,
                    'partner_name' => 'Parceiro Seeder',
                    'spotify_playlist_id' => 'seed_'.Str::slug($name, '_'),
                    'spotify_url' => 'https://open.spotify.com/playlist/seed_'.Str::slug($name, '_'),
                    'followers_count' => 1000,
                    'estimated_reach' => 2500,
                    'external_metadata' => ['source' => 'playlist_less_sold_report_seeder'],
                    'notes' => 'Registro criado para validar o relatorio de playlists menos vendidas.',
                ],
            );

        if ($playlist->trashed()) {
            $playlist->restore();
        }

        if ($subgenre !== null) {
            $playlist->subgenres()->sync([$subgenre->id]);
        }

        return $playlist;
    }

    private function playlistTop(Playlist $playlist, Top $top, string $key): PlaylistTop
    {
        $playlistTop = PlaylistTop::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'playlist_id' => $playlist->id,
                    'top_id' => $top->id,
                ],
                [
                    'slots_quantity' => 10,
                    'occupied_slots_count' => 0,
                    'price' => 10000,
                    'currency' => 'BRL',
                    'metrics' => [
                        'estimated_streams' => 10000,
                        'estimated_reach' => 20000,
                        'commercial_score' => 75,
                    ],
                    'notes' => 'TOP 10 criado para o cenario '.$key,
                    'is_active' => true,
                ],
            );

        if ($playlistTop->trashed()) {
            $playlistTop->restore();
        }

        return $playlistTop;
    }

    private function saleWithPlaylistItem(
        string $orderNumber,
        Company $company,
        Client $client,
        User $user,
        PlaylistTop $playlistTop,
        int $amount,
        SaleStatus $saleStatus,
        SaleItemStatus $itemStatus,
        PlaylistSlotStatus $slotStatus,
        string $startsOn,
        string $trackName,
    ): void {
        $endsOn = Carbon::parse($startsOn)->addDays(30)->toDateString();
        $now = now();

        $sale = Sale::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'seller_id' => $user->id,
            'created_by_id' => $user->id,
            'status' => $saleStatus,
            'order_number' => $orderNumber,
            'title' => 'Relatorio playlists menos vendidas',
            'notes' => 'Venda criada pelo PlaylistLessSoldReportSeeder.',
            'subtotal_amount' => $amount,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'currency' => 'BRL',
            'confirmed_at' => in_array($saleStatus, [SaleStatus::CampaignStarted, SaleStatus::CampaignFinished], true) ? $now : null,
            'confirmed_by_id' => in_array($saleStatus, [SaleStatus::CampaignStarted, SaleStatus::CampaignFinished], true) ? $user->id : null,
            'finished_at' => $saleStatus === SaleStatus::CampaignFinished ? $now : null,
            'finished_by_id' => $saleStatus === SaleStatus::CampaignFinished ? $user->id : null,
            'canceled_at' => $saleStatus === SaleStatus::Canceled ? $now : null,
            'canceled_by_id' => $saleStatus === SaleStatus::Canceled ? $user->id : null,
            'cancellation_reason' => $saleStatus === SaleStatus::Canceled ? 'Venda cancelada para validar filtro do relatorio.' : null,
        ]);

        $item = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'type' => SaleItemType::PlaylistSlot,
            'description' => $trackName,
            'quantity' => 1,
            'unit_amount' => $amount,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'currency' => 'BRL',
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $itemStatus,
            'playlist_top_id' => $playlistTop->id,
            'track_spotify_id' => 'seed'.Str::random(18),
            'track_spotify_url' => 'https://open.spotify.com/track/seed'.Str::random(18),
            'track_name' => $trackName,
            'track_artists' => ['BestPlay Seeder'],
            'track_album_name' => 'Seeder Album',
            'track_duration_ms' => 180000,
            'track_metadata' => ['source' => 'playlist_less_sold_report_seeder'],
            'metadata' => ['report_seed' => true],
            'created_by_id' => $user->id,
        ]);

        $slot = PlaylistSlot::query()->create([
            'playlist_top_id' => $playlistTop->id,
            'sale_item_id' => $item->id,
            'status' => $slotStatus,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'quantity' => 1,
            'track_spotify_id' => $item->track_spotify_id,
            'track_spotify_url' => $item->track_spotify_url,
            'track_name' => $item->track_name,
            'track_artists' => $item->track_artists,
            'track_album_name' => $item->track_album_name,
            'track_duration_ms' => $item->track_duration_ms,
            'track_metadata' => ['source' => 'playlist_less_sold_report_seeder'],
            'notes' => 'Slot criado pelo PlaylistLessSoldReportSeeder.',
            'created_by_id' => $user->id,
            'canceled_at' => $slotStatus === PlaylistSlotStatus::Canceled ? $now : null,
            'canceled_by_id' => $slotStatus === PlaylistSlotStatus::Canceled ? $user->id : null,
            'cancellation_reason' => $slotStatus === PlaylistSlotStatus::Canceled ? 'Slot cancelado para validar filtro do relatorio.' : null,
        ]);

        $item->update([
            'sellable_type' => $slot->getMorphClass(),
            'sellable_id' => $slot->id,
        ]);
    }
}

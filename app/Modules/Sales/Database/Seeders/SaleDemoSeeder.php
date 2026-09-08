<?php

declare(strict_types=1);

namespace App\Modules\Sales\Database\Seeders;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Sales\CancelReceivableFromSale;
use App\Modules\Finance\Actions\Sales\CreateReceivableFromSale;
use App\Modules\Finance\Database\Seeders\FinancialCategorySeeder;
use App\Modules\Finance\Models\FinancialAmountComponent;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Playlists\Database\Seeders\MusicCatalogSeeder;
use App\Modules\Playlists\Database\Seeders\TopSeeder;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use App\Modules\Sales\Actions\CreateSaleDraft;
use App\Modules\Sales\Actions\Items\SyncSaleTotals;
use App\Modules\Sales\Actions\Status\CancelSale;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Actions\Status\FinishSaleCampaign;
use App\Modules\Sales\Actions\Status\QuoteSale;
use App\Modules\Sales\Actions\Status\RevertSaleToDraft;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Services\Database\Seeders\ServiceSeeder;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SaleDemoSeeder extends Seeder
{
    private const string PREFIX = 'VEN-DEMO - ';

    private const string ORDER_PREFIX = 'VEN-DEMO-';

    private const string USER_EMAIL = 'vendas.demo.seed@bestplay.local';

    private const string CLIENT_EMAIL = 'cliente.vendas.demo.seed@bestplay.local';

    private const string ARTIST_EMAIL = 'artista.vendas.demo.seed@bestplay.local';

    private User $actor;

    private Company $company;

    private Client $client;

    private Client $artist;

    private PlaylistTop $popTop;

    private PlaylistTop $mpbTop;

    private Service $ads;

    private Service $course;

    private Service $label;

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            CompanySeeder::class,
            MusicCatalogSeeder::class,
            TopSeeder::class,
            ServiceSeeder::class,
            FinancialCategorySeeder::class,
        ]);

        DB::transaction(function (): void {
            $this->clearPreviousScenario();

            $this->actor = $this->user();
            $this->company = Company::query()->where('nickname', 'Best Play')->firstOrFail();
            $this->actor->companies()->syncWithoutDetaching([
                $this->company->id => ['access_source' => 'seeder'],
            ]);

            Auth::login($this->actor);

            try {
                $this->client = $this->makeClient(
                    email: self::CLIENT_EMAIL,
                    name: 'Cliente Vendas Demo',
                    document: '40000000000100',
                    phone: '11990000010',
                );
                $this->artist = $this->makeClient(
                    email: self::ARTIST_EMAIL,
                    name: 'Artista Vendas Demo',
                    document: '40000000000200',
                    phone: '11990000011',
                );
                $this->popTop = $this->makePlaylistTop(
                    slug: 'ven-demo-pop-hits',
                    name: self::PREFIX.'Pop Hits',
                    genreSlug: 'pop',
                    subgenreSlug: 'pop-nacional',
                    topSlug: 'top-10',
                    spotifyPlaylistId: '7VENDEMOPLAYLIST000001',
                    slotsQuantity: 10,
                    price: 99_900,
                );
                $this->mpbTop = $this->makePlaylistTop(
                    slug: 'ven-demo-mpb-prime',
                    name: self::PREFIX.'MPB Prime',
                    genreSlug: 'mpb',
                    subgenreSlug: 'mpb-antigas',
                    topSlug: 'top-20',
                    spotifyPlaylistId: '7VENDEMOPLAYLIST000002',
                    slotsQuantity: 20,
                    price: 59_900,
                );
                $this->ads = $this->service('ads');
                $this->course = $this->service('curso');
                $this->label = $this->service('selo');

                $this->seedDraftsAndQuotes();
                $this->seedStartedCampaigns();
                $this->seedFinishedCampaigns();
                $this->seedCanceledSales();
            } finally {
                Auth::logout();
            }
        });

        $this->command?->info('Cenario de vendas demo criado: rascunho, orcamento, campanha iniciada, finalizada e cancelada.');
        $this->command?->line('Login demo: '.self::USER_EMAIL.' / password');
    }

    private function seedDraftsAndQuotes(): void
    {
        $this->draft('01', 'Rascunho de servico', [
            $this->serviceItem($this->ads, 150_000),
        ], $this->client);

        $this->draft('02', 'Rascunho de playlist', [
            $this->playlistItem($this->popTop, $this->track('4cOdK2wGLETkXr3p0dPhzB', 'Never Gonna Give You Up', 'Rick Astley'), today()->addDays(7)->toDateString(), today()->addDays(37)->toDateString()),
        ], $this->artist);

        $this->quote($this->draft('03', 'Orcamento de playlist', [
            $this->playlistItem($this->mpbTop, $this->track('0VjIjW4GlUZAMYd2vXMi3b', 'Blinding Lights', 'The Weeknd'), today()->addDays(10)->toDateString(), today()->addDays(40)->toDateString()),
        ], $this->artist));

        $this->quote($this->draft('04', 'Orcamento misto', [
            $this->playlistItem($this->popTop, $this->track('3n3Ppam7vgaVa1iaRUc9Lp', 'Mr. Brightside', 'The Killers'), today()->addDays(14)->toDateString(), today()->addDays(44)->toDateString()),
            $this->serviceItem($this->course, 80_000),
        ], $this->client));

        $reverted = $this->quote($this->draft('05', 'Orcamento revertido para rascunho', [
            $this->serviceItem($this->label, 220_000),
        ], $this->client));
        app(RevertSaleToDraft::class)->handle($reverted);
    }

    private function seedStartedCampaigns(): void
    {
        $this->confirm($this->quote($this->draft('06', 'Campanha iniciada de playlist', [
            $this->playlistItem($this->popTop, $this->track('7ouMYWpwJ422jRcDASZB7P', 'Knights of Cydonia', 'Muse'), today()->toDateString(), today()->addDays(30)->toDateString()),
        ], $this->artist)));

        $this->confirm($this->quote($this->draft('07', 'Campanha iniciada de servico', [
            $this->serviceItem($this->ads, 350_000, quantity: 2),
        ], $this->client)));

        $this->confirm($this->quote($this->draft('08', 'Campanha iniciada mista', [
            $this->playlistItem($this->mpbTop, $this->track('2takcwOaAZWiXQijPHIx7B', 'Time', 'Pink Floyd'), today()->addDays(5)->toDateString(), today()->addDays(35)->toDateString()),
            $this->serviceItem($this->ads, 90_000),
        ], $this->artist)));

        $this->confirm($this->quote($this->draft('09', 'Campanha iniciada com duas playlists', [
            $this->playlistItem($this->popTop, $this->track('4kwbbhuemSkRWvtN5dkWmb', 'Somebody Told Me', 'The Killers'), today()->addDays(60)->toDateString(), today()->addDays(90)->toDateString()),
            $this->playlistItem($this->mpbTop, $this->track('1r9xUipOqoNwggBpENDsvJ', 'Take On Me', 'a-ha'), today()->addDays(60)->toDateString(), today()->addDays(90)->toDateString()),
        ], $this->artist)));

        $this->confirm($this->quote($this->draft('10', 'Campanha iniciada sem periodo', [
            $this->playlistItem($this->mpbTop, $this->track('7qiZfU4dY1lWllzX7mPBI3', 'Shape of You', 'Ed Sheeran')),
        ], $this->artist)));

        $discounted = $this->draft('16', 'Campanha iniciada com desconto', [
            $this->playlistItem($this->popTop, $this->track('0lYBSQXN6rCTvUZvg9S0lU', 'Happier', 'Marshmello'), today()->addDays(15)->toDateString(), today()->addDays(45)->toDateString()),
            $this->serviceItem($this->course, 50_000),
        ], $this->client);
        $discounted->forceFill(['discount_amount' => 15_000])->save();
        app(SyncSaleTotals::class)->handle($discounted);
        $this->confirm($this->quote($discounted->refresh()));
    }

    private function seedFinishedCampaigns(): void
    {
        $this->finish($this->confirm($this->quote($this->draft('11', 'Campanha finalizada de playlist', [
            $this->playlistItem($this->popTop, $this->track('0VjIjW4GlUZAMYd2vXMi3c', 'Save Your Tears', 'The Weeknd'), today()->subDays(60)->toDateString(), today()->subDays(30)->toDateString()),
        ], $this->artist))));

        $this->finish($this->confirm($this->quote($this->draft('12', 'Campanha finalizada mista', [
            $this->playlistItem($this->mpbTop, $this->track('3n3Ppam7vgaVa1iaRUc9Lq', 'All The Small Things', 'blink-182'), today()->subDays(90)->toDateString(), today()->subDays(60)->toDateString()),
            $this->serviceItem($this->label, 180_000),
        ], $this->client))));
    }

    private function seedCanceledSales(): void
    {
        $this->cancel($this->draft('13', 'Cancelada em rascunho', [
            $this->serviceItem($this->ads, 40_000),
        ], $this->client), 'Cliente desistiu antes do orcamento.');

        $this->cancel($this->quote($this->draft('14', 'Cancelada em orcamento', [
            $this->playlistItem($this->popTop, $this->track('2takcwOaAZWiXQijPHIx7C', 'Yellow', 'Coldplay'), today()->addDays(20)->toDateString(), today()->addDays(50)->toDateString()),
        ], $this->artist)), 'Orcamento recusado pelo cliente.');

        $this->cancel($this->confirm($this->quote($this->draft('15', 'Cancelada apos iniciar', [
            $this->playlistItem($this->mpbTop, $this->track('7ouMYWpwJ422jRcDASZB7Q', 'Feel Good Inc.', 'Gorillaz'), today()->addDays(10)->toDateString(), today()->addDays(40)->toDateString()),
        ], $this->artist))), 'Campanha cancelada apos a confirmacao.');
    }

    private function clearPreviousScenario(): void
    {
        $saleIds = Sale::query()
            ->withTrashed()
            ->where('order_number', 'like', self::ORDER_PREFIX.'%')
            ->pluck('id');

        if ($saleIds->isNotEmpty()) {
            $this->clearFinancialEntriesForSales($saleIds->all());

            PlaylistDeliveryTask::query()
                ->withTrashed()
                ->whereIn('sale_id', $saleIds)
                ->forceDelete();

            $saleItemIds = SaleItem::query()
                ->withTrashed()
                ->whereIn('sale_id', $saleIds)
                ->pluck('id');

            if ($saleItemIds->isNotEmpty()) {
                PlaylistSlot::query()
                    ->withTrashed()
                    ->whereIn('sale_item_id', $saleItemIds)
                    ->forceDelete();
            }

            SaleItem::query()->withTrashed()->whereIn('sale_id', $saleIds)->forceDelete();
            Sale::query()->withTrashed()->whereIn('id', $saleIds)->forceDelete();
        }

        $playlistIds = Playlist::query()
            ->withTrashed()
            ->where('name', 'like', self::PREFIX.'%')
            ->pluck('id');

        if ($playlistIds->isEmpty()) {
            return;
        }

        PlaylistTop::query()->withTrashed()->whereIn('playlist_id', $playlistIds)->forceDelete();
        DB::table('playlist_music_subgenre')->whereIn('playlist_id', $playlistIds)->delete();
        Playlist::query()->withTrashed()->whereIn('id', $playlistIds)->forceDelete();
    }

    /**
     * @param  array<int, int|string>  $saleIds
     */
    private function clearFinancialEntriesForSales(array $saleIds): void
    {
        $entryIds = FinancialEntry::query()
            ->withTrashed()
            ->where('originable_type', (new Sale)->getMorphClass())
            ->whereIn('originable_id', $saleIds)
            ->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
        }

        FinancialAmountComponent::query()
            ->where('amountable_type', (new FinancialEntry)->getMorphClass())
            ->whereIn('amountable_id', $entryIds)
            ->delete();

        FinancialInstallment::query()
            ->withTrashed()
            ->whereIn('financial_entry_id', $entryIds)
            ->forceDelete();

        FinancialEntry::query()
            ->withTrashed()
            ->whereIn('id', $entryIds)
            ->forceDelete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function draft(string $code, string $title, array $items, Client $client): Sale
    {
        $sale = app(CreateSaleDraft::class)->handle([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'title' => self::PREFIX.$title,
            'notes' => 'Venda criada pelo SaleDemoSeeder.',
            'items' => $items,
        ], $this->actor);

        $sale->forceFill([
            'order_number' => self::ORDER_PREFIX.$code,
        ])->save();

        return $sale->refresh()->load('saleItems');
    }

    private function quote(Sale $sale): Sale
    {
        app(QuoteSale::class)->handle($sale);

        return $sale->refresh()->load('saleItems');
    }

    private function confirm(Sale $sale): Sale
    {
        app(ConfirmSale::class)->handle($sale, confirmed: true);
        $sale = $sale->refresh();
        app(CreateReceivableFromSale::class)->handle($sale);

        return $sale->refresh()->load(['saleItems.sellable']);
    }

    private function finish(Sale $sale): Sale
    {
        app(FinishSaleCampaign::class)->handle($sale, confirmed: true);

        return $sale->refresh()->load(['saleItems.sellable']);
    }

    private function cancel(Sale $sale, string $reason): Sale
    {
        app(CancelSale::class)->handle($sale, $reason, confirmed: true);
        app(CancelReceivableFromSale::class)->handle($sale->refresh());

        return $sale->refresh()->load(['saleItems.sellable']);
    }

    /**
     * @return array<string, mixed>
     */
    private function playlistItem(
        PlaylistTop $playlistTop,
        array $track,
        ?string $startsOn = null,
        ?string $endsOn = null,
    ): array {
        return [
            'type' => SaleItemType::PlaylistSlot->value,
            'playlist_top_id' => $playlistTop->id,
            'quantity' => 1,
            'unit_amount' => $playlistTop->price,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'track_spotify_url' => $track['url'],
            'track_name' => $track['name'],
            'track_artists' => [['name' => $track['artist']]],
            'track_album_name' => $track['album'],
            'track_duration_ms' => $track['duration_ms'],
            'track_isrc' => $track['isrc'],
            'track_image_url' => $track['image_url'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceItem(Service $service, int $unitAmount, int $quantity = 1): array
    {
        return [
            'type' => SaleItemType::Service->value,
            'service_id' => $service->id,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
        ];
    }

    /**
     * @return array{name: string, artist: string, album: string, url: string, duration_ms: int, isrc: string, image_url: string}
     */
    private function track(string $spotifyId, string $name, string $artist): array
    {
        return [
            'name' => $name,
            'artist' => $artist,
            'album' => 'Album Demo Vendas',
            'url' => "https://open.spotify.com/track/{$spotifyId}",
            'duration_ms' => 210000,
            'isrc' => 'BRBPM2600001',
            'image_url' => 'https://i.scdn.co/image/ab67616d0000b273demo00000000000000000000',
        ];
    }

    private function user(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'Vendas Demo Seeder',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'must_change_password' => false,
            ],
        );

        $commercialRole = Role::query()->where('slug', 'commercial')->first();

        if ($commercialRole instanceof Role) {
            $user->roles()->syncWithoutDetaching([$commercialRole->id]);
        }

        return $user;
    }

    private function makeClient(string $email, string $name, string $document, string $phone): Client
    {
        return Client::query()->updateOrCreate(
            ['email' => $email],
            [
                'company_id' => $this->company->id,
                'responsible_user_id' => $this->actor->id,
                'name' => $name,
                'company_name' => $name.' LTDA',
                'phone' => $phone,
                'document' => $document,
                'musical_genre' => 'Pop',
                'is_agent' => false,
                'classification' => 'Classe A',
                'status' => 'active',
            ],
        );
    }

    private function makePlaylistTop(
        string $slug,
        string $name,
        string $genreSlug,
        string $subgenreSlug,
        string $topSlug,
        string $spotifyPlaylistId,
        int $slotsQuantity,
        int $price,
    ): PlaylistTop {
        $genre = MusicGenre::query()->where('slug', $genreSlug)->firstOrFail();
        $subgenre = MusicSubgenre::query()->where('slug', $subgenreSlug)->first();
        $top = Top::query()->where('slug', $topSlug)->firstOrFail();

        $playlist = Playlist::query()
            ->withTrashed()
            ->updateOrCreate(
                ['slug' => $slug],
                [
                    'music_genre_id' => $genre->id,
                    'name' => $name,
                    'status' => PlaylistStatus::Active,
                    'partner_name' => 'Curadoria BestPlay',
                    'spotify_playlist_id' => $spotifyPlaylistId,
                    'spotify_url' => 'https://open.spotify.com/playlist/'.$spotifyPlaylistId,
                    'followers_count' => 12000,
                    'estimated_reach' => 28000,
                    'external_metadata' => ['source' => 'sale_demo_seeder'],
                    'notes' => 'Playlist criada para cenarios comerciais demo.',
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
                    'slots_quantity' => $slotsQuantity,
                    'occupied_slots_count' => 0,
                    'price' => $price,
                    'currency' => 'BRL',
                    'metrics' => [
                        'estimated_streams' => 10000,
                        'estimated_reach' => 20000,
                        'average_retention_days' => 14,
                        'commercial_score' => 80,
                    ],
                    'notes' => 'TOP demo para vendas.',
                    'is_active' => true,
                ],
            );

        if ($playlistTop->trashed()) {
            $playlistTop->restore();
        }

        return $playlistTop->load(['playlist', 'top']);
    }

    private function service(string $slug): Service
    {
        return Service::query()->where('slug', $slug)->firstOrFail();
    }
}

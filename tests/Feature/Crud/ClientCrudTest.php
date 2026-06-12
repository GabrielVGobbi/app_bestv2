<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Modules\Addresses\Models\Address;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_client_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk();
    }

    public function test_client_index_exposes_quick_filter_options(): void
    {
        $user = User::factory()->create();
        $responsible = User::factory()->create(['name' => 'Ana Atendimento']);
        $commercial = Role::factory()->create(['slug' => 'commercial']);

        $responsible->roles()->attach($commercial);

        Client::factory()->create([
            'classification' => 'Classe A',
            'musical_genre' => 'Rock',
            'responsible_user_id' => $responsible->id,
        ]);

        Client::factory()->create([
            'classification' => 'Classe B',
            'musical_genre' => 'Pop',
            'responsible_user_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/clients/index')
                ->where('quickFilterOptions.classifications', collect(Client::CLASSIFICATIONS)
                    ->map(fn (string $classification): array => [
                        'label' => $classification,
                        'value' => $classification,
                    ])
                    ->all())
                ->where('quickFilterOptions.musicalGenres', [
                    ['label' => 'Pop', 'value' => 'Pop'],
                    ['label' => 'Rock', 'value' => 'Rock'],
                ])
                ->missing('quickFilterOptions.attendants')
            );
    }

    public function test_authenticated_user_can_view_client_split_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('clients.split'))
            ->assertOk();
    }

    public function test_api_returns_client_records(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(2)->create(['status' => 'active']);

        $this->actingAs($user)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_api_filters_clients_by_multiple_classifications_and_genres(): void
    {
        $user = User::factory()->create();

        Client::factory()->create([
            'name' => 'Cliente Pop A',
            'classification' => 'Classe A',
            'musical_genre' => 'Pop',
        ]);

        Client::factory()->create([
            'name' => 'Cliente Rock B',
            'classification' => 'Classe B',
            'musical_genre' => 'Rock',
        ]);

        Client::factory()->create([
            'name' => 'Cliente Funk C',
            'classification' => 'Classe C',
            'musical_genre' => 'Funk',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/clients?'.http_build_query([
                'filter' => [
                    'classification' => [
                        'operator' => 'in',
                        'value' => ['Classe A', 'Classe B'],
                    ],
                    'musical_genre' => [
                        'operator' => 'in',
                        'value' => ['Pop', 'Rock'],
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Cliente Rock B')
            ->assertJsonPath('data.1.name', 'Cliente Pop A');
    }

    public function test_api_returns_client_details_for_master_detail_view(): void
    {
        $user = User::factory()->create();
        $responsible = User::factory()->create([
            'name' => 'Responsável Comercial',
            'email' => 'responsavel@example.com',
        ]);
        $client = Client::factory()->create([
            'responsible_user_id' => $responsible->id,
            'name' => 'Maria Silva',
            'company_name' => 'Best Play Producoes',
            'email' => 'maria@example.com',
            'phone' => '11999990000',
            'document' => '12345678900',
            'notes' => 'Cliente indicado por evento.',
            'status' => 'active',
        ]);

        Address::factory()->for($client, 'addressable')->create([
            'street' => 'Rua das Flores',
            'number' => '100',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/clients/{$client->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $client->uuid)
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonPath('data.company_name', 'Best Play Producoes')
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.responsible_user.name', 'Responsável Comercial')
            ->assertJsonPath('data.addresses.0.street', 'Rua das Flores')
            ->assertJsonPath('data.addresses.0.is_default', true);
    }

    public function test_client_show_exposes_timeline_subject_contract(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'name' => 'Cliente com timeline',
        ]);

        $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/clients/show')
                ->where('clientData.data.uuid', $client->uuid)
                ->where('timelineSubject.type', Client::timelineType())
                ->where('timelineSubject.id', $client->uuid)
            );
    }

    public function test_authenticated_user_can_create_client_with_commercial_fields(): void
    {
        $user = User::factory()->create();
        $responsible = User::factory()->create();
        $commercial = Role::factory()->create(['slug' => 'commercial']);
        $responsible->roles()->attach($commercial);

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'responsible_user_id' => $responsible->id,
                'name' => 'Maria Silva',
                'company_name' => 'Best Play Producoes',
                'email' => 'maria@example.com',
                'phone' => '(11) 99999-0000',
                'document' => '123.456.789-00',
                'musical_genre' => 'Pop',
                'is_agent' => true,
                'notes' => 'Contato indicado por evento.',
                'classification' => 'Classe S',
                'source_id' => 'instagram',
                'status' => 'active',
            ])
            ->assertRedirect();

        $client = Client::query()->where('email', 'maria@example.com')->firstOrFail();

        $this->assertSame($responsible->id, $client->responsible_user_id);
        $this->assertSame('Best Play Producoes', $client->company_name);
        $this->assertSame('(11) 99999-0000', $client->phone);
        $this->assertSame('123.456.789-00', $client->document);
        $this->assertSame('Classe S', $client->classification);
        $this->assertTrue($client->is_agent);
        $this->assertSame('active', $client->status);
    }

    public function test_client_rejects_invalid_classification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Maria Silva',
                'classification' => 'premium',
            ])
            ->assertSessionHasErrors('classification');

        $this->assertDatabaseMissing('clients', ['name' => 'Maria Silva']);
    }

    public function test_client_update_rejects_invalid_classification(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'classification' => 'Classe A',
        ]);

        $this->actingAs($user)
            ->put(route('clients.update', $client), [
                'name' => $client->name,
                'classification' => 'premium',
            ])
            ->assertSessionHasErrors('classification');

        $this->assertSame('Classe A', $client->refresh()->classification);
    }

    public function test_client_rejects_responsible_user_without_commercial_role(): void
    {
        $user = User::factory()->create();
        $responsible = User::factory()->create();

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'responsible_user_id' => $responsible->id,
                'name' => 'Maria Silva',
            ])
            ->assertSessionHasErrors('responsible_user_id');

        $this->assertDatabaseMissing('clients', ['name' => 'Maria Silva']);
    }

    public function test_authenticated_user_can_quick_update_client_name(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['name' => 'Nome antigo']);

        $this->actingAs($user)
            ->patch(route('clients.quick-update', $client), [
                'field' => 'name',
                'value' => 'Nome atualizado',
            ])
            ->assertRedirect();

        $this->assertSame('Nome atualizado', $client->refresh()->name);
    }

    public function test_authenticated_user_can_quick_update_client_responsible_user(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $responsible = User::factory()->create();
        $commercial = Role::factory()->create(['slug' => 'commercial']);

        $responsible->roles()->attach($commercial);

        $this->actingAs($user)
            ->patch(route('clients.quick-update', $client), [
                'field' => 'responsible_user_id',
                'value' => (string) $responsible->id,
            ])
            ->assertRedirect();

        $this->assertSame($responsible->id, $client->refresh()->responsible_user_id);
    }

    public function test_client_quick_update_rejects_unsupported_fields(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'name' => 'Nome original',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)
            ->patch(route('clients.quick-update', $client), [
                'field' => 'email',
                'value' => 'alterado@example.com',
            ])
            ->assertSessionHasErrors('field');

        $client->refresh();

        $this->assertSame('Nome original', $client->name);
        $this->assertSame('original@example.com', $client->email);
    }

    public function test_client_quick_update_rejects_extra_payload_fields(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'name' => 'Nome original',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)
            ->patch(route('clients.quick-update', $client), [
                'field' => 'name',
                'value' => 'Nome atualizado',
                'email' => 'alterado@example.com',
            ])
            ->assertSessionHasErrors('field');

        $client->refresh();

        $this->assertSame('Nome original', $client->name);
        $this->assertSame('original@example.com', $client->email);
    }

    public function test_authenticated_user_can_change_client_status(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->patch(route('clients.status', $client), ['status' => 'archived'])
            ->assertRedirect();

        $this->assertSame('archived', $client->refresh()->status);
    }

    public function test_client_status_update_rejects_invalid_value(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->patch(route('clients.status', $client), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $client->refresh()->status);
    }

    public function test_client_optional_unique_fields_ignore_blank_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('clients.store'), ['name' => 'Cliente sem contato'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('clients.store'), ['name' => 'Outro cliente sem contato'])
            ->assertRedirect();

        $this->assertSame(2, Client::query()->count());
    }

    public function test_client_rejects_duplicate_email_phone_and_document_when_filled(): void
    {
        $user = User::factory()->create();

        Client::factory()->create([
            'email' => 'cliente@example.com',
            'phone' => '11999990000',
            'document' => '12345678900',
        ]);

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Duplicado',
                'email' => 'cliente@example.com',
                'phone' => '11999990000',
                'document' => '12345678900',
            ])
            ->assertSessionHasErrors(['email', 'phone', 'document']);
    }

    public function test_client_uses_addresses_without_creating_duplicates(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $payload = [
            'model' => 'client',
            'model_id' => $client->uuid,
            'street' => 'Rua das Flores',
            'number' => '100',
            'district' => 'Centro',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'zipcode' => '01001-000',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/addresses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.zipcode', '01001000')
            ->assertJsonPath('data.is_default', true);

        $this->actingAs($user)
            ->postJson('/api/v1/addresses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.zipcode', '01001000')
            ->assertJsonPath('data.is_default', true);

        $client->refresh();

        $this->assertCount(1, $client->addresses);
        $this->assertInstanceOf(Address::class, $client->defaultAddress());
    }

    public function test_client_addresses_can_be_updated_defaulted_and_removed(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $primary = Address::factory()->for($client, 'addressable')->create(['is_default' => true]);
        $secondary = Address::factory()->for($client, 'addressable')->create([
            'is_default' => false,
            'street' => 'Rua Antiga',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/addresses/{$secondary->uuid}", [
                'street' => 'Rua Nova',
                'number' => '200',
                'district' => 'Centro',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'zipcode' => '01310-000',
            ])
            ->assertOk()
            ->assertJsonPath('data.street', 'Rua Nova')
            ->assertJsonPath('data.zipcode', '01310000');

        $this->actingAs($user)
            ->patchJson("/api/v1/addresses/{$secondary->uuid}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($primary->refresh()->is_default);

        $this->actingAs($user)
            ->deleteJson("/api/v1/addresses/{$secondary->uuid}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted($secondary);
        $this->assertTrue($primary->refresh()->is_default);
    }

    public function test_client_keeps_only_one_default_address_when_created_or_updated(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $primary = Address::factory()->for($client, 'addressable')->create(['is_default' => true]);

        $this->actingAs($user)
            ->postJson('/api/v1/addresses', [
                'model' => 'client',
                'model_id' => $client->uuid,
                'street' => 'Alameda das Margaridas',
                'number' => '921',
                'district' => 'Alphaville',
                'city' => 'Santana de Parnaiba',
                'state' => 'SP',
                'zipcode' => '06539-270',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $secondary = $client->addresses()
            ->where('number', '921')
            ->firstOrFail();

        $this->assertFalse($primary->refresh()->is_default);
        $this->assertSame(1, $client->addresses()->where('is_default', true)->count());

        $this->actingAs($user)
            ->putJson("/api/v1/addresses/{$primary->uuid}", [
                'street' => 'Alameda das Margaridas',
                'number' => '1',
                'district' => 'Alphaville',
                'city' => 'Santana de Parnaiba',
                'state' => 'SP',
                'zipcode' => '06539-270',
                'is_default' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($secondary->refresh()->is_default);
        $this->assertSame(1, $client->addresses()->where('is_default', true)->count());

        $this->actingAs($user)
            ->postJson('/api/v1/addresses', [
                'model' => 'client',
                'model_id' => $client->uuid,
                'street' => 'Alameda das Margaridas',
                'number' => '921',
                'district' => 'Alphaville',
                'city' => 'Santana de Parnaiba',
                'state' => 'SP',
                'zipcode' => '06539-270',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.uuid', $secondary->uuid)
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($primary->refresh()->is_default);
        $this->assertSame(1, $client->addresses()->where('is_default', true)->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Seeders\PaymentMethodSeeder;
use App\Modules\Finance\Enums\PaymentMethodType;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_global_payment_methods_idempotently(): void
    {
        $this->seed(PaymentMethodSeeder::class);
        $this->seed(PaymentMethodSeeder::class);

        $pix = PaymentMethod::query()->where('code', 'pix')->firstOrFail();

        $this->assertNull($pix->company_id);
        $this->assertSame(PaymentMethodType::Pix, $pix->type);
        $this->assertTrue($pix->requires_reference);
        $this->assertTrue($pix->is_active);
        $this->assertSame(7, PaymentMethod::query()->count());
    }

    public function test_active_scope_excludes_inactive_methods(): void
    {
        PaymentMethod::factory()->create(['code' => 'active_method', 'is_active' => true]);
        PaymentMethod::factory()->create(['code' => 'inactive_method', 'is_active' => false]);

        $this->assertSame(1, PaymentMethod::query()->active()->count());
    }

    public function test_payment_method_pages_render_for_authorized_user(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance', seedPermissions: true);
        $paymentMethod = PaymentMethod::factory()->create([
            'name' => 'Pix',
            'code' => 'pix_local',
            'type' => PaymentMethodType::Pix,
        ]);

        $this->actingAs($user)
            ->get(route('payment-methods.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('app/payment-methods/index'));

        $this->actingAs($user)
            ->get(route('payment-methods.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/payment-methods/create')
                ->has('typeOptions'));

        $this->actingAs($user)
            ->get(route('payment-methods.show', $paymentMethod))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/payment-methods/show')
                ->has('paymentMethodData.data'));

        $this->actingAs($user)
            ->get(route('payment-methods.edit', $paymentMethod))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/payment-methods/edit')
                ->has('paymentMethodData.data'));
    }

    public function test_payment_method_can_be_created_updated_deactivated_and_deleted_from_web(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);

        $this->actingAs($user)
            ->post(route('payment-methods.store'), [
                'name' => 'Pix ecommerce',
                'code' => 'pix_ecommerce',
                'type' => PaymentMethodType::Pix->value,
                'requires_reference' => '1',
                'is_active' => '1',
                'sort_order' => 2,
                'metadata' => [
                    'notes' => 'Usado para recebimentos online.',
                ],
            ])
            ->assertRedirect();

        $paymentMethod = PaymentMethod::query()->where('code', 'pix_ecommerce')->firstOrFail();

        $this->assertSame(PaymentMethodType::Pix, $paymentMethod->type);
        $this->assertTrue($paymentMethod->requires_reference);
        $this->assertSame('Usado para recebimentos online.', $paymentMethod->metadata['notes']);

        $this->actingAs($user)
            ->put(route('payment-methods.update', $paymentMethod), [
                'name' => 'Pix financeiro',
                'code' => 'pix_financeiro',
                'type' => PaymentMethodType::Pix->value,
                'requires_reference' => '0',
                'is_active' => '1',
                'sort_order' => 3,
            ])
            ->assertRedirect(route('payment-methods.show', $paymentMethod));

        $paymentMethod->refresh();

        $this->assertSame('Pix financeiro', $paymentMethod->name);
        $this->assertSame('pix_financeiro', $paymentMethod->code);
        $this->assertFalse($paymentMethod->requires_reference);

        $this->actingAs($user)
            ->patch(route('payment-methods.status', $paymentMethod), [
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertFalse($paymentMethod->refresh()->is_active);

        $this->actingAs($user)
            ->delete(route('payment-methods.destroy', $paymentMethod))
            ->assertRedirect(route('payment-methods.index'));

        $this->assertModelMissing($paymentMethod);
    }

    public function test_payment_method_code_is_unique_per_company_scope(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);
        $company = Company::factory()->create();

        PaymentMethod::factory()->create([
            'company_id' => null,
            'code' => 'pix',
            'type' => PaymentMethodType::Pix,
        ]);
        PaymentMethod::factory()->create([
            'company_id' => $company->id,
            'code' => 'pix',
            'type' => PaymentMethodType::Pix,
        ]);

        $this->actingAs($user)
            ->post(route('payment-methods.store'), [
                'name' => 'Pix duplicado global',
                'code' => 'pix',
                'type' => PaymentMethodType::Pix->value,
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($user)
            ->post(route('payment-methods.store'), [
                'company_id' => $company->id,
                'name' => 'Pix duplicado empresa',
                'code' => 'pix',
                'type' => PaymentMethodType::Pix->value,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_payment_method_api_returns_resource_and_options(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);
        $company = Company::factory()->create();
        $globalPix = PaymentMethod::factory()->create([
            'company_id' => null,
            'name' => 'Pix global',
            'code' => 'pix_global',
            'type' => PaymentMethodType::Pix,
            'requires_reference' => true,
        ]);
        PaymentMethod::factory()->create([
            'company_id' => $company->id,
            'name' => 'Boleto local',
            'code' => 'boleto_local',
            'type' => PaymentMethodType::Boleto,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.payment-methods.index', [
                'filter' => [
                    'type' => PaymentMethodType::Pix->value,
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $globalPix->id)
            ->assertJsonPath('data.0.type_label', 'Pix');

        $this->actingAs($user)
            ->getJson(route('api.payment-methods.options', [
                'company_id' => $company->id,
                'active' => '1',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'pix_global');
    }

    private function userWithRole(string $roleSlug, bool $seedPermissions = false): User
    {
        $this->seed(RolesSeeder::class);

        if ($seedPermissions) {
            $this->seed(PermissionsSeeder::class);
        }

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}

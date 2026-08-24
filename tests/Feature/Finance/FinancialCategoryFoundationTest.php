<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Seeders\FinancialCategorySeeder;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialCategoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_idempotent_global_tree(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $this->seed(FinancialCategorySeeder::class);

        $this->assertDatabaseCount('financial_categories', 12);

        $revenue = FinancialCategory::query()
            ->where('code', 'REC')
            ->whereNull('company_id')
            ->firstOrFail();

        $this->assertSame(FinancialCategoryType::Income, $revenue->type);
        $this->assertCount(2, $revenue->children);
        $this->assertSame('Receitas > Receita de servicos', $revenue->children->first()->fullPath());
    }

    public function test_category_tree_helpers_and_rules(): void
    {
        $company = Company::factory()->create();
        $parent = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
            'name' => 'Despesas',
            'slug' => 'despesas',
        ]);
        $child = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
            'parent_id' => $parent->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
        ]);
        $grandchild = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
            'parent_id' => $child->id,
            'name' => 'Trafego pago',
            'slug' => 'trafego-pago',
        ]);

        $this->assertSame('Despesas > Marketing > Trafego pago', $grandchild->fullPath());
        $this->assertSame(2, $grandchild->depth());
        $this->assertTrue($child->hasChildren());
        $this->assertSame([$child->id, $grandchild->id], $parent->descendantIds());
        $this->assertTrue($grandchild->isDescendantOf($parent));

        $this->expectException(ValidationException::class);

        $parent->update(['parent_id' => $grandchild->id]);
    }

    public function test_parent_must_have_same_type(): void
    {
        $expenseParent = FinancialCategory::factory()->expense()->create();

        $this->expectException(ValidationException::class);

        FinancialCategory::factory()->income()->create([
            'parent_id' => $expenseParent->id,
        ]);
    }

    public function test_category_with_children_or_entries_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $category = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
        ]);

        FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
            'parent_id' => $category->id,
        ]);

        $this->assertFalse($category->canBeDeleted());
        $this->assertContains('children', $category->deletionBlockers());

        $leaf = FinancialCategory::factory()->expense()->create();
        FinancialEntry::factory()->create([
            'category_id' => $leaf->id,
        ]);

        $this->assertFalse($leaf->canBeDeleted());
        $this->assertContains('financial_entries', $leaf->deletionBlockers());
    }

    public function test_financial_category_pages_render_for_authorized_user(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance', seedPermissions: true);
        $category = FinancialCategory::factory()->expense()->create();

        $this->actingAs($user)
            ->get(route('financial-categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-categories/index')
                ->has('typeOptions'));

        $this->actingAs($user)
            ->get(route('financial-categories.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('app/financial-categories/create'));

        $this->actingAs($user)
            ->get(route('financial-categories.show', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-categories/show')
                ->has('categoryData.data'));

        $this->actingAs($user)
            ->get(route('financial-categories.edit', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-categories/edit')
                ->has('categoryData.data'));
    }

    public function test_category_can_be_created_updated_and_deactivated_from_web(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post(route('financial-categories.store'), [
                'company_id' => $company->id,
                'name' => 'Marketing',
                'code' => 'MKT',
                'type' => FinancialCategoryType::Expense->value,
                'color' => '#f97316',
                'sort_order' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $category = FinancialCategory::query()->where('code', 'MKT')->firstOrFail();

        $this->assertSame($company->id, $category->company_id);
        $this->assertSame('marketing', $category->slug);

        $this->actingAs($user)
            ->put(route('financial-categories.update', $category), [
                'company_id' => $company->id,
                'name' => 'Marketing e midia',
                'code' => 'MKT',
                'type' => FinancialCategoryType::Expense->value,
                'color' => '#ea580c',
                'sort_order' => 11,
                'is_active' => '0',
            ])
            ->assertRedirect(route('financial-categories.show', $category));

        $category->refresh();

        $this->assertSame('Marketing e midia', $category->name);
        $this->assertFalse($category->is_active);
    }

    public function test_api_returns_resource_and_tree_shape(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);
        $company = Company::factory()->create();
        $parent = FinancialCategory::factory()->expense()->create([
            'company_id' => null,
            'name' => 'Despesas',
            'slug' => 'despesas',
        ]);
        $child = FinancialCategory::factory()->expense()->create([
            'company_id' => null,
            'parent_id' => $parent->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
        ]);
        FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
            'name' => 'Receita local',
            'slug' => 'receita-local',
        ]);

        $this->actingAs($user)
            ->getJson(route('api.financial-categories.index', [
                'filter' => [
                    'type' => FinancialCategoryType::Expense->value,
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.full_path', 'Despesas')
            ->assertJsonPath('data.0.has_children', true);

        $this->actingAs($user)
            ->getJson(route('api.financial-categories.tree', [
                'type' => FinancialCategoryType::Expense->value,
                'company_id' => $company->id,
                'active' => '1',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonPath('data.0.children.0.full_path', 'Despesas > Marketing')
            ->assertJsonPath('data.0.children.0.depth', 1)
            ->assertJsonPath('data.0.children.0.has_children', false)
            ->assertJsonPath('data.0.children.0.breadcrumb.0.name', 'Despesas');

        $this->actingAs($user)
            ->getJson(route('api.financial-categories.index', [
                'ids' => (string) $child->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $child->id)
            ->assertJsonPath('data.0.name', 'Marketing');
    }

    public function test_category_delete_route_blocks_records_in_use(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);
        $category = FinancialCategory::factory()->expense()->create();
        FinancialEntry::factory()->create(['category_id' => $category->id]);

        $this->actingAs($user)
            ->delete(route('financial-categories.destroy', $category))
            ->assertForbidden();

        $this->assertModelExists($category);
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

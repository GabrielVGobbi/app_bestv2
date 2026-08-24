<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialOperationalConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_entry_requires_leaf_category_and_due_date(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'description' => 'Recebível sem classificação operacional',
                'components' => [[
                    'code' => FinancialAmountComponentCode::Principal->value,
                    'operation' => FinancialAmountOperation::Add->value,
                    'amount' => 10000,
                ]],
            ])
            ->assertSessionHasErrors(['category_id', 'due_date']);

        $this->assertSame(0, FinancialEntry::query()->count());
    }

    public function test_internal_entry_receives_deterministic_operational_defaults(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Integração interna sem classificação explícita',
            'issue_date' => '2026-08-24',
            'components' => [[
                'code' => FinancialAmountComponentCode::Principal,
                'operation' => FinancialAmountOperation::Add,
                'amount' => 10000,
            ]],
        ])->load(['category.children', 'installments.category.children']);

        $this->assertSame('SYS-UNCAT-REC', $entry->category?->code);
        $this->assertFalse($entry->category?->hasChildren() ?? true);
        $this->assertSame('2026-08-24', $entry->due_date?->toDateString());
        $this->assertSame($entry->category_id, $entry->installments->sole()->category_id);
        $this->assertSame('2026-08-24', $entry->installments->sole()->due_date?->toDateString());
    }

    public function test_operational_backfill_is_auditable_and_reversible(): void
    {
        $entry = FinancialEntry::factory()->create();
        $installment = FinancialInstallment::factory()->create([
            'financial_entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'direction' => $entry->direction,
        ]);

        DB::table('financial_entries')->where('id', $entry->id)->update(['category_id' => null, 'due_date' => null]);
        DB::table('financial_installments')->where('id', $installment->id)->update(['category_id' => null, 'due_date' => null]);

        $migration = $this->operationalBackfillMigration();
        $migration->up();

        $entry->refresh();
        $installment->refresh();

        $this->assertNotNull($entry->category_id);
        $this->assertNotNull($entry->due_date);
        $this->assertSame($entry->category_id, $installment->category_id);
        $this->assertNotNull($installment->due_date);
        $this->assertDatabaseHas('financial_operational_backfills', [
            'record_type' => 'entry',
            'record_id' => $entry->id,
            'original_category_id' => null,
            'original_due_date' => null,
        ]);
        $this->assertDatabaseHas('financial_operational_backfills', [
            'record_type' => 'installment',
            'record_id' => $installment->id,
            'original_category_id' => null,
            'original_due_date' => null,
        ]);

        $migration->down();

        $this->assertDatabaseHas('financial_entries', ['id' => $entry->id, 'category_id' => null, 'due_date' => null]);
        $this->assertDatabaseHas('financial_installments', ['id' => $installment->id, 'category_id' => null, 'due_date' => null]);
    }

    private function operationalBackfillMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require base_path('app/Modules/Finance/Database/Migrations/2026_08_24_151324_backfill_financial_operational_requirements.php');

        return $migration;
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}

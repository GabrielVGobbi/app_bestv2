<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Models\BankStatementImport;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankStatementImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_imports_csv_statement_and_duplicate_lines_are_ignored(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $file = UploadedFile::fake()->createWithContent(
            'extrato.csv',
            "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n2026-07-06,Tarifa,-4.50,TRX-2\n2026-07-07,Cliente BR,\"1.234,56\",TRX-3\n"
        );

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => $file,
                'source' => 'csv',
                'financial_account_id' => $account->id,
            ])
            ->assertRedirect(route('financial-reconciliation.imports.index'));

        $this->assertSame(1, BankStatementImport::query()->count());
        $this->assertSame(3, BankStatementLine::query()->count());
        $this->assertTrue(BankStatementLine::query()->where('financial_account_id', $account->id)->exists());

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato-repetido.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n2026-07-06,Tarifa,-4.50,TRX-2\n2026-07-07,Cliente BR,\"1.234,56\",TRX-3\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $account->id,
            ])
            ->assertRedirect(route('financial-reconciliation.imports.index'));

        $this->assertSame(2, BankStatementImport::query()->count());
        $this->assertSame(3, BankStatementLine::query()->count());
        $this->assertDatabaseHas('bank_statement_lines', [
            'description' => 'Cliente A',
            'amount' => 10050,
            'reference' => 'TRX-1',
        ]);
        $this->assertDatabaseHas('bank_statement_lines', [
            'description' => 'Cliente BR',
            'amount' => 123456,
            'reference' => 'TRX-3',
            'financial_account_id' => $account->id,
        ]);
    }

    public function test_same_statement_lines_can_be_imported_for_different_financial_accounts(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $firstAccount = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $secondAccount = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $contents = "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n";

        foreach ([$firstAccount, $secondAccount] as $account) {
            $this->actingAs($user)
                ->post(route('financial-reconciliation.imports.store'), [
                    'file' => UploadedFile::fake()->createWithContent('extrato.csv', $contents),
                    'source' => 'csv',
                    'financial_account_id' => $account->id,
                ])
                ->assertRedirect(route('financial-reconciliation.imports.index'));
        }

        $this->assertSame(2, BankStatementImport::query()->count());
        $this->assertSame(2, BankStatementLine::query()->count());
        $this->assertSame(1, BankStatementLine::query()->where('financial_account_id', $firstAccount->id)->count());
        $this->assertSame(1, BankStatementLine::query()->where('financial_account_id', $secondAccount->id)->count());
    }

    public function test_import_requires_active_financial_account(): void
    {
        $user = $this->userWithRole('finance');

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
            ])
            ->assertSessionHasErrors('financial_account_id');

        $inactive = FinancialAccount::factory()->create(['active' => false]);

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('financial_account_id');
    }

    public function test_finance_user_imports_minimal_ofx_statement(): void
    {
        $user = $this->userWithRole('finance');
        $account = FinancialAccount::factory()->create();
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML

<OFX>
<BANKMSGSRSV1>
<STMTTRNRS>
<STMTRS>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20260705120000[-3:BRT]
<TRNAMT>100.50
<FITID>OFX-1
<MEMO>Cliente OFX
</STMTTRN>
</BANKTRANLIST>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent('extrato.ofx', $ofx),
                'source' => 'ofx',
                'financial_account_id' => $account->id,
            ])
            ->assertRedirect(route('financial-reconciliation.imports.index'));

        $this->assertDatabaseHas('bank_statement_lines', [
            'description' => 'Cliente OFX',
            'amount' => 10050,
            'reference' => 'OFX-1',
            'financial_account_id' => $account->id,
        ]);
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}

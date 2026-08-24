<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Reconciliation\ImportBankStatement;
use App\Modules\Finance\Http\Resources\BankStatementLineResource;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BankStatementPayloadProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_minimizes_encrypts_and_never_serializes_raw_payload(): void
    {
        $company = Company::factory()->create();
        $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $file = UploadedFile::fake()->createWithContent(
            'extrato-protegido.csv',
            "date,description,amount,reference,bank_secret\n2026-08-24,Cliente protegido,123.45,SEC-1,NUNCA-PERSISTIR\n",
        );

        $import = app(ImportBankStatement::class)->handle($file, [
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'source' => 'csv',
        ]);
        $line = $import->lines->sole();
        $storedPayload = (string) DB::table('bank_statement_lines')
            ->where('id', $line->id)
            ->value('protected_payload');

        $this->assertNull($line->raw_payload);
        $this->assertSame('csv', $line->protected_payload['source']);
        $this->assertSame('SEC-1', $line->protected_payload['reference']);
        $this->assertArrayNotHasKey('bank_secret', $line->protected_payload);
        $this->assertStringNotContainsString('Cliente protegido', $storedPayload);
        $this->assertStringNotContainsString('NUNCA-PERSISTIR', $storedPayload);

        $serialized = (new BankStatementLineResource($line))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('raw_payload', $serialized);
        $this->assertArrayNotHasKey('protected_payload', $serialized);
        $this->assertArrayNotHasKey('raw_payload', $line->toArray());
        $this->assertArrayNotHasKey('protected_payload', $line->toArray());

        $activityProperties = Activity::query()
            ->where('subject_type', $line->getMorphClass())
            ->where('subject_id', $line->id)
            ->get()
            ->pluck('properties')
            ->map(fn ($properties): string => $properties->toJson())
            ->implode('|');

        $this->assertStringNotContainsString('raw_payload', $activityProperties);
        $this->assertStringNotContainsString('protected_payload', $activityProperties);
        $this->assertStringNotContainsString('NUNCA-PERSISTIR', $activityProperties);
    }

    public function test_protected_payload_round_trips_through_encrypted_cast(): void
    {
        $company = Company::factory()->create();
        $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $line = BankStatementLine::query()->create([
            'bank_statement_import_id' => $this->statementImportId($company->id, $account->id),
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'posted_at' => '2026-08-24',
            'description' => 'Linha protegida',
            'amount' => 100,
            'reference' => 'CAST-1',
            'line_hash' => hash('sha256', 'CAST-1'),
            'raw_payload' => null,
            'protected_payload' => ['private' => 'conteúdo cifrado'],
        ]);

        $raw = (string) DB::table('bank_statement_lines')->where('id', $line->id)->value('protected_payload');

        $this->assertStringNotContainsString('conteúdo cifrado', $raw);
        $this->assertSame(['private' => 'conteúdo cifrado'], $line->refresh()->protected_payload);
    }

    private function statementImportId(int $companyId, int $accountId): int
    {
        return (int) DB::table('bank_statement_imports')->insertGetId([
            'uuid' => fake()->uuid(),
            'company_id' => $companyId,
            'financial_account_id' => $accountId,
            'created_by_id' => null,
            'source' => 'csv',
            'status' => 'processed',
            'original_name' => 'cast.csv',
            'file_hash' => hash('sha256', 'cast.csv'),
            'line_count' => 1,
            'duplicate_count' => 0,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Models\User;
use App\Modules\Finance\Enums\BankStatementImportStatus;
use App\Modules\Finance\Models\BankStatementImport;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use App\Modules\Finance\Support\MoneyStringParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportBankStatement
{
    public function __construct(
        private readonly SuggestReconciliationMatches $suggestMatches,
        private readonly FinancialCompanyAccess $companyAccess,
        private readonly MoneyStringParser $moneyParser,
    ) {}

    /**
     * @param  array{company_id?: int|null, financial_account_id?: int|null, source?: string|null}  $payload
     */
    public function handle(UploadedFile $file, array $payload = [], ?User $actor = null): BankStatementImport
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $source = strtolower((string) ($payload['source'] ?? $file->getClientOriginalExtension() ?: 'csv'));
        $financialAccount = $this->resolveFinancialAccount($payload);
        if ($actor instanceof User) {
            $this->companyAccess->authorize($actor, (int) $financialAccount->company_id);
        }
        $companyId = (int) $financialAccount->company_id;
        $rows = $source === 'ofx' ? $this->parseOfx($contents) : $this->parseCsv($contents);

        return DB::transaction(function () use ($file, $source, $financialAccount, $companyId, $contents, $rows, $actor): BankStatementImport {
            $import = BankStatementImport::query()->create([
                'company_id' => $companyId,
                'financial_account_id' => $financialAccount->id,
                'created_by_id' => $actor?->getKey(),
                'source' => $source,
                'status' => BankStatementImportStatus::Processed,
                'original_name' => $file->getClientOriginalName(),
                'file_hash' => hash('sha256', $contents),
                'metadata' => [
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ],
            ]);

            $lineCount = 0;
            $duplicateCount = 0;

            foreach ($rows as $row) {
                $hash = $this->lineHash($financialAccount->id, $row);

                if (BankStatementLine::query()
                    ->where('financial_account_id', $financialAccount->id)
                    ->where('line_hash', $hash)
                    ->exists()) {
                    $duplicateCount++;

                    continue;
                }

                $line = BankStatementLine::query()->create([
                    'bank_statement_import_id' => $import->id,
                    'company_id' => $companyId,
                    'financial_account_id' => $financialAccount->id,
                    'posted_at' => $row['posted_at'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'reference' => $row['reference'],
                    'line_hash' => $hash,
                    'raw_payload' => null,
                    'protected_payload' => $row['protected_payload'],
                ]);

                $this->suggestMatches->handle($line);
                $lineCount++;
            }

            $import->forceFill([
                'line_count' => $lineCount,
                'duplicate_count' => $duplicateCount,
            ])->save();

            return $import->refresh()->load(['financialAccount', 'lines.financialAccount', 'lines.matches']);
        }, attempts: 3);
    }

    /**
     * @param  array{company_id?: int|null, financial_account_id?: int|null}  $payload
     */
    private function resolveFinancialAccount(array $payload): FinancialAccount
    {
        $financialAccountId = $payload['financial_account_id'] ?? null;

        if (! is_int($financialAccountId) && ! (is_string($financialAccountId) && ctype_digit($financialAccountId))) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Informe uma conta financeira ativa.'),
            ]);
        }

        $financialAccount = FinancialAccount::query()
            ->active()
            ->find((int) $financialAccountId);

        if (! $financialAccount) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Conta financeira ativa não encontrada.'),
            ]);
        }

        if (isset($payload['company_id']) && (int) $payload['company_id'] !== (int) $financialAccount->company_id) {
            throw ValidationException::withMessages([
                'company_id' => __('Empresa do extrato deve ser a mesma da conta financeira.'),
            ]);
        }

        return $financialAccount;
    }

    /**
     * @return array<int, array{posted_at: string, description: string, amount: int, reference: ?string, protected_payload: array<string, mixed>}>
     */
    private function parseCsv(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents));

        if (! is_array($lines) || $lines === []) {
            return [];
        }

        $header = str_getcsv((string) array_shift($lines));
        $header = array_map(fn (string $value): string => strtolower(trim($value)), $header);
        $rows = [];

        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $values = str_getcsv((string) $line);
            $data = array_combine($header, $values);

            if (! is_array($data)) {
                continue;
            }

            $rows[] = [
                'posted_at' => Carbon::parse($data['date'] ?? $data['posted_at'] ?? now())->toDateString(),
                'description' => trim((string) ($data['description'] ?? $data['memo'] ?? 'Linha de extrato')),
                'amount' => $this->amountToCents((string) ($data['amount'] ?? 0)),
                'reference' => blank($data['reference'] ?? null) ? null : trim((string) $data['reference']),
                'protected_payload' => [
                    'source' => 'csv',
                    'date' => $data['date'] ?? $data['posted_at'] ?? null,
                    'description' => $data['description'] ?? $data['memo'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'reference' => $data['reference'] ?? null,
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{posted_at: string, description: string, amount: int, reference: ?string, protected_payload: array<string, mixed>}>
     */
    private function parseOfx(string $contents): array
    {
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $contents, $matches);
        $rows = [];

        foreach ($matches[1] ?? [] as $block) {
            $amount = $this->ofxValue($block, 'TRNAMT') ?? '0';
            $postedAt = $this->ofxValue($block, 'DTPOSTED') ?? now()->format('Ymd');
            $reference = $this->ofxValue($block, 'FITID');
            $description = $this->ofxValue($block, 'MEMO') ?? $this->ofxValue($block, 'NAME') ?? 'Linha OFX';

            $rows[] = [
                'posted_at' => Carbon::createFromFormat('Ymd', substr($postedAt, 0, 8))->toDateString(),
                'description' => trim($description),
                'amount' => $this->amountToCents($amount),
                'reference' => blank($reference) ? null : trim((string) $reference),
                'protected_payload' => [
                    'source' => 'ofx',
                    'posted_at' => $postedAt,
                    'description' => $description,
                    'amount' => $amount,
                    'reference' => $reference,
                ],
            ];
        }

        return $rows;
    }

    private function ofxValue(string $block, string $tag): ?string
    {
        if (! preg_match('/<'.$tag.'>([^\r\n<]+)/i', $block, $match)) {
            return null;
        }

        return trim((string) $match[1]);
    }

    private function amountToCents(string $value): int
    {
        try {
            return $this->moneyParser->toCents($value);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'file' => __('O extrato possui valor monetário inválido ou com mais de duas casas decimais.'),
            ]);
        }
    }

    /**
     * @param  array{posted_at: string, description: string, amount: int, reference: ?string}  $row
     */
    private function lineHash(int $financialAccountId, array $row): string
    {
        return hash('sha256', implode('|', [
            $financialAccountId,
            $row['posted_at'],
            $row['amount'],
            Str::squish($row['description']),
            $row['reference'] ?? '',
        ]));
    }
}

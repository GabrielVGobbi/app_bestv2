<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Entries;

use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ValueError;

class CreateManualFinancialEntry
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): FinancialEntry
    {
        $company = $this->resolveCompany($payload['company_id'] ?? null);
        $direction = $this->resolveDirection($payload['direction'] ?? null);
        $this->validateOriginablePair($payload);
        $counterparty = $this->resolveCounterparty($payload, $company);
        $category = $this->resolveCategory($payload['category_id'] ?? null, $company);

        $components = $this->normalizeComponents($payload['components'] ?? null);
        $grossAmount = $components
            ->where('operation', FinancialAmountOperation::Add)
            ->sum('amount');
        $subtractAmount = $components
            ->where('operation', FinancialAmountOperation::Subtract)
            ->sum('amount');
        $netAmount = $grossAmount - $subtractAmount;

        if ($netAmount <= 0) {
            throw ValidationException::withMessages([
                'components' => __('O valor liquido do lancamento deve ser maior que zero.'),
            ]);
        }

        $description = trim((string) ($payload['description'] ?? ''));

        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => __('Informe a descricao do lancamento financeiro.'),
            ]);
        }

        return DB::transaction(function () use ($payload, $company, $direction, $counterparty, $category, $components, $grossAmount, $netAmount, $description): FinancialEntry {
            $issueDate = $this->dateOrNull($payload['issue_date'] ?? null) ?? now()->toDateString();
            $competenceDate = $this->dateOrNull($payload['competence_date'] ?? null) ?? $issueDate;
            $dueDate = $this->dateOrNull($payload['due_date'] ?? null);

            $entry = FinancialEntry::query()->create([
                'company_id' => $company->id,
                'created_by_id' => auth()->id(),
                'direction' => $direction,
                'status' => FinancialEntryStatus::Open,
                'counterparty_type' => $counterparty?->getMorphClass(),
                'counterparty_id' => $counterparty?->getKey(),
                'originable_type' => $payload['originable_type'] ?? null,
                'originable_id' => $payload['originable_id'] ?? null,
                'origin_reference' => blank($payload['origin_reference'] ?? null) ? null : (string) $payload['origin_reference'],
                'category_id' => $category?->id,
                'description' => $description,
                'issue_date' => $issueDate,
                'competence_date' => $competenceDate,
                'due_date' => $dueDate,
                'gross_amount' => $grossAmount,
                'net_amount' => $netAmount,
                'settled_amount' => 0,
                'balance_amount' => $netAmount,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
            ]);

            $entry->amountComponents()->createMany(
                $components
                    ->map(fn (array $component): array => [
                        'company_id' => $company->id,
                        'code' => $component['code'],
                        'label' => $component['label'],
                        'operation' => $component['operation'],
                        'amount' => $component['amount'],
                        'position' => $component['position'],
                        'metadata' => $component['metadata'],
                    ])
                    ->all()
            );

            $entry->installments()->create([
                'company_id' => $company->id,
                'status' => FinancialEntryStatus::Open,
                'installment_number' => 1,
                'due_date' => $dueDate,
                'original_amount' => $netAmount,
                'settled_amount' => 0,
                'balance_amount' => $netAmount,
                'currency' => $entry->currency,
                'metadata' => null,
            ]);

            return $entry->load(['amountComponents', 'installments', 'counterparty']);
        }, attempts: 3);
    }

    private function resolveCompany(mixed $value): Company
    {
        $companyId = $this->positiveInteger($value, 'company_id');
        $company = Company::query()->find($companyId);

        if (! $company) {
            throw ValidationException::withMessages([
                'company_id' => __('Empresa invalida para o lancamento financeiro.'),
            ]);
        }

        return $company;
    }

    private function resolveDirection(mixed $value): FinancialDirection
    {
        if ($value instanceof FinancialDirection) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return FinancialDirection::from($value);
            } catch (ValueError) {
                // Validation exception below keeps the external error shape consistent.
            }
        }

        throw ValidationException::withMessages([
            'direction' => __('Informe se o lancamento e a receber ou a pagar.'),
        ]);
    }

    private function resolveCategory(mixed $value, Company $company): ?FinancialCategory
    {
        if (blank($value)) {
            return null;
        }

        $category = FinancialCategory::query()->find($this->positiveInteger($value, 'category_id'));

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => __('Categoria financeira nao encontrada.'),
            ]);
        }

        if ($category->company_id !== null && (int) $category->company_id !== $company->id) {
            throw ValidationException::withMessages([
                'category_id' => __('Categoria financeira pertence a outra empresa.'),
            ]);
        }

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => __('Categoria financeira inativa nao pode ser usada em novo lancamento.'),
            ]);
        }

        if ($category->hasChildren()) {
            throw ValidationException::withMessages([
                'category_id' => __('Selecione uma categoria financeira folha para o lancamento.'),
            ]);
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateOriginablePair(array $payload): void
    {
        $originableType = $payload['originable_type'] ?? null;
        $originableId = $payload['originable_id'] ?? null;

        if (blank($originableType) && blank($originableId)) {
            return;
        }

        if (! blank($originableType) && ! blank($originableId)) {
            return;
        }

        throw ValidationException::withMessages([
            'originable' => __('Informe tipo e identificador da origem, ou deixe ambos vazios.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCounterparty(array $payload, Company $company): ?Model
    {
        $counterpartyType = $payload['counterparty_type'] ?? null;
        $counterpartyId = $payload['counterparty_id'] ?? null;

        if (blank($counterpartyType) && blank($counterpartyId)) {
            return null;
        }

        if (blank($counterpartyType) || blank($counterpartyId)) {
            throw ValidationException::withMessages([
                'counterparty' => __('Informe tipo e identificador da contraparte, ou deixe ambos vazios.'),
            ]);
        }

        if (
            ! is_string($counterpartyType)
            || ! class_exists($counterpartyType)
            || ! is_subclass_of($counterpartyType, Model::class)
            || ! in_array($counterpartyType, $this->allowedCounterpartyTypes(), true)
        ) {
            throw ValidationException::withMessages([
                'counterparty_type' => __('Tipo de contraparte invalido.'),
            ]);
        }

        /** @var class-string<Model> $counterpartyType */
        $counterparty = $counterpartyType::query()->find($this->positiveInteger($counterpartyId, 'counterparty_id'));

        if (! $counterparty) {
            throw ValidationException::withMessages([
                'counterparty_id' => __('Contraparte nao encontrada.'),
            ]);
        }

        if ($counterparty->getAttribute('company_id') !== null && (int) $counterparty->getAttribute('company_id') !== $company->id) {
            throw ValidationException::withMessages([
                'counterparty_id' => __('Contraparte pertence a outra empresa.'),
            ]);
        }

        if ($counterparty instanceof Supplier && $counterparty->isArchived()) {
            throw ValidationException::withMessages([
                'counterparty_id' => __('Fornecedor arquivado nao pode ser usado em novo lancamento financeiro.'),
            ]);
        }

        return $counterparty;
    }

    /** @return array<int, class-string<Model>> */
    private function allowedCounterpartyTypes(): array
    {
        return [
            Client::class,
            Supplier::class,
        ];
    }

    /**
     * @return Collection<int, array{code: FinancialAmountComponentCode, label: ?string, operation: FinancialAmountOperation, amount: int, position: int, metadata: ?array}>
     */
    private function normalizeComponents(mixed $components): Collection
    {
        if (! is_array($components) || $components === []) {
            throw ValidationException::withMessages([
                'components' => __('Informe pelo menos um componente de valor.'),
            ]);
        }

        return collect($components)
            ->values()
            ->map(function (mixed $component, int $index): array {
                if (! is_array($component)) {
                    throw ValidationException::withMessages([
                        "components.{$index}" => __('Componente de valor invalido.'),
                    ]);
                }

                return [
                    'code' => $this->resolveComponentCode($component['code'] ?? null, $index),
                    'label' => blank($component['label'] ?? null) ? null : (string) $component['label'],
                    'operation' => $this->resolveOperation($component['operation'] ?? null, $index),
                    'amount' => $this->positiveInteger($component['amount'] ?? null, "components.{$index}.amount"),
                    'position' => $this->positiveInteger($component['position'] ?? $index, "components.{$index}.position", allowZero: true),
                    'metadata' => is_array($component['metadata'] ?? null) ? $component['metadata'] : null,
                ];
            });
    }

    private function resolveComponentCode(mixed $value, int $index): FinancialAmountComponentCode
    {
        if ($value instanceof FinancialAmountComponentCode) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return FinancialAmountComponentCode::from($value);
            } catch (ValueError) {
                // Validation exception below keeps the external error shape consistent.
            }
        }

        throw ValidationException::withMessages([
            "components.{$index}.code" => __('Tipo de componente de valor invalido.'),
        ]);
    }

    private function resolveOperation(mixed $value, int $index): FinancialAmountOperation
    {
        if ($value instanceof FinancialAmountOperation) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return FinancialAmountOperation::from($value);
            } catch (ValueError) {
                // Validation exception below keeps the external error shape consistent.
            }
        }

        throw ValidationException::withMessages([
            "components.{$index}.operation" => __('Operacao do componente de valor invalida.'),
        ]);
    }

    private function positiveInteger(mixed $value, string $field, bool $allowZero = false): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || ($allowZero ? $value < 0 : $value <= 0)) {
            throw ValidationException::withMessages([
                $field => __('Informe um valor inteiro em centavos.'),
            ]);
        }

        return $value;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}

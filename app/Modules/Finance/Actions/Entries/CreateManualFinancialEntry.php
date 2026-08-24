<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Entries;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Support\FinancialAmountComposition;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ValueError;

class CreateManualFinancialEntry
{
    public function __construct(
        private readonly FinancialCompanyAccess $companyAccess,
        private readonly RecalculateFinancialEntryStatus $recalculateStatus,
        private readonly FinancialAmountComposition $amountComposition,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?User $actor = null): FinancialEntry
    {
        $company = $this->resolveCompany($payload['company_id'] ?? null);

        if ($actor instanceof User) {
            $this->companyAccess->authorize($actor, $company);
        }
        $direction = $this->resolveDirection($payload['direction'] ?? null);
        $this->validateOriginablePair($payload);
        $counterparty = $this->resolveCounterparty($payload, $company, $direction);
        $category = $this->resolveCategory($payload['category_id'] ?? null, $company, $direction);

        $componentOrigin = blank($payload['originable_type'] ?? null)
            ? 'manual'
            : str((string) $payload['originable_type'])->classBasename()->snake()->value();
        $composition = $this->amountComposition->normalize($payload['components'] ?? null, $componentOrigin);
        $components = $composition['components'];
        $grossAmount = $composition['gross_amount'];
        $additionAmount = $composition['addition_amount'];
        $deductionAmount = $composition['deduction_amount'];
        $netAmount = $composition['net_amount'];

        $description = trim((string) ($payload['description'] ?? ''));
        $issueDate = $this->dateOrNull($payload['issue_date'] ?? null) ?? now()->toDateString();
        $competenceDate = $this->dateOrNull($payload['competence_date'] ?? null) ?? $issueDate;
        $dueDate = $this->dateOrNull($payload['due_date'] ?? null);
        $installments = $this->normalizeInstallments(
            $payload['installments'] ?? null,
            $netAmount,
            $dueDate,
            $competenceDate,
        )->map(fn (array $installment): array => [
            ...$installment,
            'due_date' => $installment['due_date'] ?? $issueDate,
        ]);
        $entryDueDate = $dueDate ?? $installments
            ->pluck('due_date')
            ->filter()
            ->sort()
            ->first();

        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => __('Informe a descricao do lancamento financeiro.'),
            ]);
        }

        return DB::transaction(function () use ($payload, $actor, $company, $direction, $counterparty, $category, $components, $grossAmount, $additionAmount, $deductionAmount, $netAmount, $description, $issueDate, $competenceDate, $entryDueDate, $installments): FinancialEntry {
            $entry = FinancialEntry::query()->create([
                'company_id' => $company->id,
                'created_by_id' => $actor?->getKey(),
                'direction' => $direction,
                'status' => FinancialEntryStatus::Open,
                'counterparty_type' => $counterparty?->getMorphClass(),
                'counterparty_id' => $counterparty?->getKey(),
                'originable_type' => $payload['originable_type'] ?? null,
                'originable_id' => $payload['originable_id'] ?? null,
                'category_id' => $category?->id,
                'description' => $description,
                'issue_date' => $issueDate,
                'competence_date' => $competenceDate,
                'due_date' => $entryDueDate,
                'gross_amount' => $grossAmount,
                'addition_amount' => $additionAmount,
                'deduction_amount' => $deductionAmount,
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
                        'nature' => $component['nature'],
                        'label' => $component['label'],
                        'operation' => $component['operation'],
                        'affects_net' => $component['affects_net'],
                        'amount' => $component['amount'],
                        'origin' => $component['origin'],
                        'base_amount' => $component['base_amount'],
                        'rate_basis_points' => $component['rate_basis_points'],
                        'calculation_method' => $component['calculation_method'],
                        'position' => $component['position'],
                        'metadata' => $component['metadata'],
                    ])
                    ->all()
            );

            $entry->installments()->createMany(
                $installments
                    ->map(fn (array $installment): array => [
                        'company_id' => $company->id,
                        'status' => FinancialEntryStatus::Open,
                        'direction' => $direction,
                        'counterparty_type' => $counterparty?->getMorphClass(),
                        'counterparty_id' => $counterparty?->getKey(),
                        'category_id' => $category?->id,
                        'installment_number' => $installment['installment_number'],
                        'due_date' => $installment['due_date'],
                        'competence_date' => $installment['competence_date'],
                        'original_amount' => $installment['amount'],
                        'settled_amount' => 0,
                        'balance_amount' => $installment['amount'],
                        'currency' => $entry->currency,
                        'settled_at' => null,
                        'metadata' => $installment['metadata'],
                    ])
                    ->all()
            );

            return $this->recalculateStatus->handle($entry);
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

    private function resolveCategory(mixed $value, Company $company, FinancialDirection $direction): ?FinancialCategory
    {
        if (blank($value)) {
            $type = match ($direction) {
                FinancialDirection::Receivable => FinancialCategoryType::Income,
                FinancialDirection::Payable => FinancialCategoryType::Expense,
            };
            $code = match ($direction) {
                FinancialDirection::Receivable => 'SYS-UNCAT-REC',
                FinancialDirection::Payable => 'SYS-UNCAT-PAY',
            };

            return FinancialCategory::query()->firstOrCreate(
                ['company_id' => null, 'code' => $code, 'type' => $type->value],
                [
                    'name' => $direction === FinancialDirection::Receivable
                        ? 'Receitas não classificadas'
                        : 'Despesas não classificadas',
                    'slug' => strtolower($code),
                    'description' => 'Categoria sistêmica para fluxos internos sem classificação explícita.',
                    'sort_order' => 65535,
                    'is_active' => true,
                ],
            );
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

        $expectedType = match ($direction) {
            FinancialDirection::Receivable => FinancialCategoryType::Income,
            FinancialDirection::Payable => FinancialCategoryType::Expense,
        };

        if ($category->type !== $expectedType) {
            throw ValidationException::withMessages([
                'category_id' => __('Categoria financeira incompativel com a direcao do lancamento.'),
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
    private function resolveCounterparty(array $payload, Company $company, FinancialDirection $direction): Model
    {
        $counterpartyType = $payload['counterparty_type'] ?? null;
        $counterpartyId = $payload['counterparty_id'] ?? null;

        if (blank($counterpartyType) || blank($counterpartyId)) {
            throw ValidationException::withMessages([
                'counterparty_type' => __('Informe a contraparte do lancamento financeiro.'),
                'counterparty_id' => __('Informe a contraparte do lancamento financeiro.'),
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

        $expectedCounterpartyType = match ($direction) {
            FinancialDirection::Receivable => Client::class,
            FinancialDirection::Payable => Supplier::class,
        };

        if ($counterpartyType !== $expectedCounterpartyType) {
            throw ValidationException::withMessages([
                'counterparty_type' => match ($direction) {
                    FinancialDirection::Receivable => __('Lancamento a receber deve usar cliente como contraparte.'),
                    FinancialDirection::Payable => __('Lancamento a pagar deve usar fornecedor como contraparte.'),
                },
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

                $code = $this->resolveComponentCode($component['code'] ?? null, $index);
                $label = blank($component['label'] ?? null) ? null : trim((string) $component['label']);
                $operation = $this->resolveOperation($component['operation'] ?? null, $index);

                $this->validateComponentInvariants($code, $label, $operation, $index);

                return [
                    'code' => $code,
                    'label' => $label,
                    'operation' => $operation,
                    'amount' => $this->positiveInteger($component['amount'] ?? null, "components.{$index}.amount"),
                    'position' => $this->positiveInteger($component['position'] ?? $index, "components.{$index}.position", allowZero: true),
                    'metadata' => is_array($component['metadata'] ?? null) ? $component['metadata'] : null,
                ];
            });
    }

    private function validateComponentInvariants(
        FinancialAmountComponentCode $code,
        ?string $label,
        FinancialAmountOperation $operation,
        int $index,
    ): void {
        $fixedOperation = $code->fixedOperation();

        if ($fixedOperation && $operation !== $fixedOperation) {
            throw ValidationException::withMessages([
                "components.{$index}.operation" => __('A operação informada não é permitida para este tipo de ajuste.'),
            ]);
        }

        if ($code->requiresDescription() && blank($label)) {
            throw ValidationException::withMessages([
                "components.{$index}.label" => __('Informe uma descrição para o ajuste do tipo Outro.'),
            ]);
        }
    }

    /**
     * @return Collection<int, array{installment_number: int, due_date: ?string, competence_date: ?string, amount: int, metadata: ?array}>
     */
    private function normalizeInstallments(mixed $installments, int $netAmount, ?string $fallbackDueDate, ?string $fallbackCompetenceDate): Collection
    {
        if (! is_array($installments) || $installments === []) {
            return collect([
                [
                    'installment_number' => 1,
                    'due_date' => $fallbackDueDate,
                    'competence_date' => $fallbackCompetenceDate,
                    'amount' => $netAmount,
                    'metadata' => null,
                ],
            ]);
        }

        $normalized = collect($installments)
            ->values()
            ->map(function (mixed $installment, int $index) use ($fallbackDueDate, $fallbackCompetenceDate): array {
                if (! is_array($installment)) {
                    throw ValidationException::withMessages([
                        "installments.{$index}" => __('Parcela invalida.'),
                    ]);
                }

                return [
                    'installment_number' => blank($installment['installment_number'] ?? null)
                        ? $index + 1
                        : $this->positiveInteger($installment['installment_number'], "installments.{$index}.installment_number"),
                    'due_date' => $this->dateOrNull($installment['due_date'] ?? null) ?? $fallbackDueDate,
                    'competence_date' => $this->dateOrNull($installment['competence_date'] ?? null) ?? $fallbackCompetenceDate,
                    'amount' => $this->positiveInteger($installment['amount'] ?? null, "installments.{$index}.amount"),
                    'metadata' => is_array($installment['metadata'] ?? null) ? $installment['metadata'] : null,
                ];
            });

        $duplicateNumber = $normalized
            ->pluck('installment_number')
            ->duplicates()
            ->first();

        if ($duplicateNumber !== null) {
            throw ValidationException::withMessages([
                'installments' => __('Numeracao de parcelas duplicada.'),
            ]);
        }

        if ($normalized->sum('amount') !== $netAmount) {
            throw ValidationException::withMessages([
                'installments' => __('A soma das parcelas deve fechar com o valor liquido do lancamento.'),
            ]);
        }

        return $normalized
            ->sortBy('installment_number')
            ->values();
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

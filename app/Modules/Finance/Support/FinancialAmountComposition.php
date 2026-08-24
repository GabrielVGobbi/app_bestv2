<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountNature;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use ValueError;

class FinancialAmountComposition
{
    /**
     * @return array{components: Collection<int, array<string, mixed>>, gross_amount: int, addition_amount: int, deduction_amount: int, net_amount: int}
     */
    public function normalize(mixed $components, string $defaultOrigin): array
    {
        if (! is_array($components) || $components === []) {
            throw ValidationException::withMessages([
                'components' => __('Informe pelo menos um componente de valor.'),
            ]);
        }

        $normalized = collect($components)->values()->map(
            fn (mixed $component, int $index): array => $this->normalizeComponent($component, $index, $defaultOrigin),
        );

        if ($normalized->where('nature', FinancialAmountNature::Principal)->count() !== 1) {
            throw ValidationException::withMessages([
                'components' => __('A composição deve possuir exatamente um componente principal.'),
            ]);
        }

        $grossAmount = (int) $normalized->firstWhere('nature', FinancialAmountNature::Principal)['amount'];
        $additionAmount = (int) $normalized
            ->where('nature', FinancialAmountNature::Addition)
            ->where('affects_net', true)
            ->sum('amount');
        $deductionAmount = (int) $normalized
            ->where('nature', FinancialAmountNature::Deduction)
            ->where('affects_net', true)
            ->sum('amount');
        $netAmount = $grossAmount + $additionAmount - $deductionAmount;

        if ($netAmount <= 0) {
            throw ValidationException::withMessages([
                'components' => __('O valor líquido da composição deve ser maior que zero.'),
            ]);
        }

        return [
            'components' => $normalized,
            'gross_amount' => $grossAmount,
            'addition_amount' => $additionAmount,
            'deduction_amount' => $deductionAmount,
            'net_amount' => $netAmount,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeComponent(mixed $component, int $index, string $defaultOrigin): array
    {
        if (! is_array($component)) {
            throw ValidationException::withMessages([
                "components.{$index}" => __('Componente de valor inválido.'),
            ]);
        }

        $code = $this->enumValue(FinancialAmountComponentCode::class, $component['code'] ?? null, "components.{$index}.code");
        $providedOperation = blank($component['operation'] ?? null)
            ? null
            : $this->enumValue(FinancialAmountOperation::class, $component['operation'], "components.{$index}.operation");
        $nature = blank($component['nature'] ?? null)
            ? $code->defaultNature($providedOperation)
            : $this->enumValue(FinancialAmountNature::class, $component['nature'], "components.{$index}.nature");
        $operation = $providedOperation ?? $nature->operation();
        $affectsNet = array_key_exists('affects_net', $component)
            ? filter_var($component['affects_net'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : $nature->affectsNet();

        if (! in_array($nature, $code->allowedNatures(), true)) {
            throw ValidationException::withMessages([
                "components.{$index}.nature" => __('A natureza não é compatível com o tipo do componente.'),
            ]);
        }

        if ($operation !== $nature->operation()) {
            throw ValidationException::withMessages([
                "components.{$index}.operation" => __('A operação não é compatível com a natureza do componente.'),
            ]);
        }

        if ($affectsNet === null || $affectsNet !== $nature->affectsNet()) {
            throw ValidationException::withMessages([
                "components.{$index}.affects_net" => __('O impacto no líquido não é compatível com a natureza do componente.'),
            ]);
        }

        $label = blank($component['label'] ?? null) ? null : trim((string) $component['label']);

        if ($code->requiresDescription() && $label === null) {
            throw ValidationException::withMessages([
                "components.{$index}.label" => __('Informe uma descrição para o componente do tipo Outro.'),
            ]);
        }

        return [
            'code' => $code,
            'nature' => $nature,
            'label' => $label,
            'operation' => $operation,
            'affects_net' => $affectsNet,
            'amount' => $this->integer($component['amount'] ?? null, "components.{$index}.amount", minimum: 1),
            'origin' => blank($component['origin'] ?? null) ? $defaultOrigin : trim((string) $component['origin']),
            'base_amount' => blank($component['base_amount'] ?? null)
                ? null
                : $this->integer($component['base_amount'], "components.{$index}.base_amount", minimum: 0),
            'rate_basis_points' => blank($component['rate_basis_points'] ?? null)
                ? null
                : $this->integer($component['rate_basis_points'], "components.{$index}.rate_basis_points", minimum: 0),
            'calculation_method' => blank($component['calculation_method'] ?? null)
                ? null
                : trim((string) $component['calculation_method']),
            'position' => $this->integer($component['position'] ?? $index, "components.{$index}.position", minimum: 0),
            'metadata' => is_array($component['metadata'] ?? null) ? $component['metadata'] : null,
        ];
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    private function enumValue(string $enum, mixed $value, string $field): \BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        try {
            return $enum::from((string) $value);
        } catch (ValueError) {
            throw ValidationException::withMessages([$field => __('Valor de componente inválido.')]);
        }
    }

    private function integer(mixed $value, string $field, int $minimum): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < $minimum) {
            throw ValidationException::withMessages([$field => __('Informe um valor inteiro em centavos.')]);
        }

        return $value;
    }
}

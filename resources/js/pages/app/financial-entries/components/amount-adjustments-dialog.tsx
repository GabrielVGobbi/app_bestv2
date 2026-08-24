import {
    BadgeDollarSign,
    ArrowDownToLine,
    ArrowUpFromLine,
    Check,
    CircleDollarSign,
    Landmark,
    Percent,
    ReceiptText,
    Scale,
    Tag,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type {
    FinancialAmountAdjustmentOption,
    FinancialAmountComponentCode,
    FinancialAmountOperation,
    FinancialDirection,
} from '@/types/app/financial-entry';

export interface AmountComponentDraft {
    id: string;
    code: FinancialAmountComponentCode;
    label: string;
    operation: FinancialAmountOperation;
    amount: string;
}

interface AmountAdjustmentsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    principalAmount: string;
    adjustments: AmountComponentDraft[];
    options: FinancialAmountAdjustmentOption[];
    direction: FinancialDirection;
    onConfirm: (adjustments: AmountComponentDraft[]) => void;
    disabled?: boolean;
}

const moneyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const optionIcons: Partial<
    Record<FinancialAmountComponentCode, typeof CircleDollarSign>
> = {
    interest: Percent,
    penalty: Scale,
    fee: ReceiptText,
    tax: Landmark,
    discount: Tag,
    withholding: ArrowDownToLine,
    other: CircleDollarSign,
};

function uniqueId(prefix: string): string {
    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function parseAmountToCents(value: string): number {
    if (!value) {
        return 0;
    }

    const hasDecimal = value.includes('.') || value.includes(',');
    const digits = value.replace(/[^\d]/g, '');

    if (!digits) {
        return 0;
    }

    if (hasDecimal) {
        const normalized = value.replace(/[^\d,.-]/g, '').replace(',', '.');
        const parsed = Number.parseFloat(normalized);

        return Number.isNaN(parsed) ? 0 : Math.round(parsed * 100);
    }

    return Number.parseInt(digits, 10);
}

function centsToDecimal(value: number): string {
    return (value / 100).toFixed(2);
}

function consolidateAdjustments(
    adjustments: AmountComponentDraft[],
    options: FinancialAmountAdjustmentOption[],
): AmountComponentDraft[] {
    const optionByCode = new Map(
        options.map((option) => [option.value, option]),
    );
    const consolidated = new Map<string, AmountComponentDraft>();
    const result: AmountComponentDraft[] = [];

    for (const adjustment of adjustments) {
        if (adjustment.code === 'other') {
            result.push({ ...adjustment });
            continue;
        }

        const option = optionByCode.get(adjustment.code);
        const operation = option?.operation ?? adjustment.operation;
        const key = option?.operation
            ? adjustment.code
            : `${adjustment.code}:${operation}`;
        const existing = consolidated.get(key);

        if (existing) {
            existing.amount = centsToDecimal(
                parseAmountToCents(existing.amount) +
                    parseAmountToCents(adjustment.amount),
            );
            continue;
        }

        const nextAdjustment = {
            ...adjustment,
            label: option?.label ?? adjustment.label,
            operation,
        };

        consolidated.set(key, nextAdjustment);
        result.push(nextAdjustment);
    }

    return result;
}

export function AmountAdjustmentsDialog({
    open,
    onOpenChange,
    principalAmount,
    adjustments,
    options,
    direction,
    onConfirm,
    disabled,
}: AmountAdjustmentsDialogProps) {
    const [drafts, setDrafts] = useState<AmountComponentDraft[]>(() =>
        consolidateAdjustments(adjustments, options),
    );
    const [attemptedSubmit, setAttemptedSubmit] = useState(false);

    const principalCents = parseAmountToCents(principalAmount);
    const additionsCents = drafts
        .filter((draft) => draft.operation === 'add')
        .reduce((total, draft) => total + parseAmountToCents(draft.amount), 0);
    const subtractionsCents = drafts
        .filter((draft) => draft.operation === 'subtract')
        .reduce((total, draft) => total + parseAmountToCents(draft.amount), 0);
    const projectedNetCents =
        principalCents + additionsCents - subtractionsCents;
    const invalidDraftIds = useMemo(
        () =>
            new Set(
                drafts
                    .filter(
                        (draft) =>
                            parseAmountToCents(draft.amount) <= 0 ||
                            (draft.code === 'other' &&
                                draft.label.trim() === ''),
                    )
                    .map((draft) => draft.id),
            ),
        [drafts],
    );
    const canConfirm =
        !disabled && invalidDraftIds.size === 0 && projectedNetCents > 0;

    function updateDraft(
        id: string,
        patch: Partial<Omit<AmountComponentDraft, 'id'>>,
    ): void {
        setDrafts((currentDrafts) =>
            currentDrafts.map((draft) =>
                draft.id === id ? { ...draft, ...patch } : draft,
            ),
        );
    }

    function focusAmount(id: string): void {
        window.requestAnimationFrame(() => {
            document.getElementById(`adjustment-amount-${id}`)?.focus();
        });
    }

    function addOption(option: FinancialAmountAdjustmentOption): void {
        if (option.value !== 'other') {
            const existing = drafts.find(
                (draft) => draft.code === option.value,
            );

            if (existing) {
                focusAmount(existing.id);

                return;
            }
        }

        const draft: AmountComponentDraft = {
            id: uniqueId('adjustment'),
            code: option.value,
            label: option.requires_description ? '' : option.label,
            operation: option.operation ?? 'add',
            amount: '0.00',
        };

        setDrafts((currentDrafts) => [...currentDrafts, draft]);
        setAttemptedSubmit(false);
        focusAmount(draft.id);
    }

    function removeDraft(id: string): void {
        setDrafts((currentDrafts) =>
            currentDrafts.filter((draft) => draft.id !== id),
        );
    }

    function handleConfirm(): void {
        setAttemptedSubmit(true);

        if (!canConfirm) {
            return;
        }

        onConfirm(drafts);
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader className="border-b border-border/60 px-5 pt-5 pr-12 pb-4 sm:px-6 sm:pt-6">
                    <DialogTitle className="text-xl text-balance">
                        Ajustar composição
                    </DialogTitle>
                    <DialogDescription className="text-pretty">
                        Adicione acréscimos e reduções ao valor principal. O
                        efeito de cada tipo já está definido.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                    <div className="flex flex-col gap-6">
                        <div className="overflow-hidden rounded-2xl border border-border/70 bg-muted/25 shadow-[0_1px_0_oklch(0_0_0/0.03)]">
                            <div className="grid grid-cols-3 divide-x divide-border/60 border-b border-border/60">
                                <div className="flex min-w-0 flex-col gap-1 px-3 py-3 sm:px-4">
                                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                        Principal
                                    </span>
                                    <span className="truncate text-sm font-semibold tabular-nums sm:text-base">
                                        {moneyFormatter.format(
                                            principalCents / 100,
                                        )}
                                    </span>
                                </div>
                                <div className="flex min-w-0 flex-col gap-1 px-3 py-3 sm:px-4">
                                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                        Acréscimos
                                    </span>
                                    <span className="truncate text-sm font-semibold text-inflow tabular-nums sm:text-base">
                                        +
                                        {moneyFormatter.format(
                                            additionsCents / 100,
                                        )}
                                    </span>
                                </div>
                                <div className="flex min-w-0 flex-col gap-1 px-3 py-3 sm:px-4">
                                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                        Reduções
                                    </span>
                                    <span className="truncate text-sm font-semibold text-outflow tabular-nums sm:text-base">
                                        −
                                        {moneyFormatter.format(
                                            subtractionsCents / 100,
                                        )}
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-center justify-between gap-4 bg-background/70 px-4 py-4 sm:px-5">
                                <div className="flex items-center gap-2 text-sm font-medium">
                                    <BadgeDollarSign className="size-4 text-muted-foreground" />
                                    Líquido projetado
                                </div>
                                <span
                                    className={cn(
                                        'text-xl font-semibold tracking-tight tabular-nums sm:text-2xl',
                                        projectedNetCents <= 0
                                            ? 'text-destructive'
                                            : direction === 'payable'
                                              ? 'text-outflow'
                                              : 'text-inflow',
                                    )}
                                >
                                    {moneyFormatter.format(
                                        projectedNetCents / 100,
                                    )}
                                </span>
                            </div>
                        </div>

                        <section aria-labelledby="adjustment-types-heading">
                            <div className="flex flex-col gap-1">
                                <h3
                                    id="adjustment-types-heading"
                                    className="text-sm font-semibold"
                                >
                                    Adicionar tipo
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Selecione um tipo e informe o valor abaixo.
                                </p>
                            </div>

                            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                {options.map((option) => {
                                    const Icon =
                                        optionIcons[option.value] ??
                                        CircleDollarSign;
                                    const isSelected =
                                        option.value !== 'other' &&
                                        drafts.some(
                                            (draft) =>
                                                draft.code === option.value,
                                        );
                                    const isAddition =
                                        option.operation === 'add';

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() => addOption(option)}
                                            disabled={disabled}
                                            aria-pressed={isSelected}
                                            className={cn(
                                                'group flex min-h-20 items-start gap-3 rounded-xl border border-border/70 bg-background px-3 py-3 text-left shadow-xs transition-[border-color,background-color,transform,box-shadow] duration-150 outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20 active:scale-[0.96] disabled:pointer-events-none disabled:opacity-50 motion-reduce:transition-none motion-reduce:active:scale-100',
                                                isSelected
                                                    ? 'border-primary/35 bg-primary/5 shadow-[0_0_0_1px_color-mix(in_oklab,var(--primary)_14%,transparent)]'
                                                    : 'hover:border-foreground/20 hover:bg-muted/35',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                    option.operation ===
                                                        'subtract'
                                                        ? 'bg-outflow-soft text-outflow-soft-foreground'
                                                        : option.operation ===
                                                            'add'
                                                          ? 'bg-inflow-soft text-inflow-soft-foreground'
                                                          : 'bg-secondary text-secondary-foreground',
                                                )}
                                            >
                                                <Icon className="size-4" />
                                            </span>
                                            <span className="flex min-w-0 flex-1 flex-col gap-1">
                                                <span className="truncate text-sm font-medium">
                                                    {option.label}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {option.operation === null
                                                        ? 'Você define'
                                                        : isAddition
                                                          ? '+ Acréscimo'
                                                          : '− Redução'}
                                                </span>
                                            </span>
                                            {isSelected ? (
                                                <Check className="size-4 shrink-0 text-primary" />
                                            ) : null}
                                        </button>
                                    );
                                })}
                            </div>
                        </section>

                        <section aria-labelledby="selected-adjustments-heading">
                            <div className="flex items-center justify-between gap-3">
                                <h3
                                    id="selected-adjustments-heading"
                                    className="text-sm font-semibold"
                                >
                                    Ajustes selecionados
                                </h3>
                                <Badge variant="muted">
                                    {drafts.length}{' '}
                                    {drafts.length === 1 ? 'item' : 'itens'}
                                </Badge>
                            </div>

                            {drafts.length === 0 ? (
                                <div className="mt-3 flex min-h-24 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 py-5 text-center">
                                    <CircleDollarSign className="size-5 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum ajuste selecionado.
                                    </p>
                                </div>
                            ) : (
                                <div className="mt-3 flex flex-col gap-2">
                                    {drafts.map((draft) => {
                                        const option = options.find(
                                            (item) => item.value === draft.code,
                                        );
                                        const Icon =
                                            optionIcons[draft.code] ??
                                            CircleDollarSign;
                                        const isInvalid =
                                            attemptedSubmit &&
                                            invalidDraftIds.has(draft.id);

                                        return (
                                            <div
                                                key={draft.id}
                                                className={cn(
                                                    'rounded-xl border border-border/70 bg-background p-3 shadow-xs transition-[border-color,box-shadow] duration-150',
                                                    isInvalid &&
                                                        'border-destructive/50 ring-2 ring-destructive/10',
                                                )}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span
                                                        className={cn(
                                                            'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                                            draft.operation ===
                                                                'add'
                                                                ? 'bg-inflow-soft text-inflow-soft-foreground'
                                                                : 'bg-outflow-soft text-outflow-soft-foreground',
                                                        )}
                                                    >
                                                        <Icon className="size-4" />
                                                    </span>

                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <p className="truncate text-sm font-medium">
                                                                {option?.label ??
                                                                    draft.label ??
                                                                    'Ajuste'}
                                                            </p>
                                                            <Badge
                                                                variant={
                                                                    draft.operation ===
                                                                    'add'
                                                                        ? 'inflow'
                                                                        : 'outflow'
                                                                }
                                                            >
                                                                {draft.operation ===
                                                                'add'
                                                                    ? 'Acréscimo'
                                                                    : 'Redução'}
                                                            </Badge>
                                                        </div>
                                                    </div>

                                                    <div className="w-36 shrink-0 sm:w-44">
                                                        <InputMoney
                                                            id={`adjustment-amount-${draft.id}`}
                                                            value={draft.amount}
                                                            onChange={(
                                                                amount,
                                                            ) =>
                                                                updateDraft(
                                                                    draft.id,
                                                                    { amount },
                                                                )
                                                            }
                                                            disabled={disabled}
                                                            aria-label={`Valor de ${option?.label ?? draft.label}`}
                                                            aria-invalid={
                                                                isInvalid ||
                                                                undefined
                                                            }
                                                            className="h-10 text-right font-semibold tabular-nums"
                                                        />
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            removeDraft(
                                                                draft.id,
                                                            )
                                                        }
                                                        disabled={disabled}
                                                        aria-label={`Remover ${option?.label ?? draft.label}`}
                                                        className="size-10 shrink-0 text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>

                                                {draft.code === 'other' ? (
                                                    <div className="mt-3 grid gap-3 border-t border-border/60 pt-3 sm:grid-cols-[1fr_auto]">
                                                        <div className="flex flex-col gap-1.5">
                                                            <label
                                                                htmlFor={`adjustment-label-${draft.id}`}
                                                                className="text-xs font-medium text-muted-foreground"
                                                            >
                                                                Descrição
                                                            </label>
                                                            <Input
                                                                id={`adjustment-label-${draft.id}`}
                                                                value={
                                                                    draft.label
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateDraft(
                                                                        draft.id,
                                                                        {
                                                                            label: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Ex.: correção contratual"
                                                                disabled={
                                                                    disabled
                                                                }
                                                                aria-invalid={
                                                                    isInvalid ||
                                                                    undefined
                                                                }
                                                                className="h-10"
                                                            />
                                                        </div>
                                                        <div className="flex flex-col gap-1.5">
                                                            <span className="text-xs font-medium text-muted-foreground">
                                                                Efeito no total
                                                            </span>
                                                            <ToggleGroup
                                                                type="single"
                                                                variant="outline"
                                                                value={
                                                                    draft.operation
                                                                }
                                                                onValueChange={(
                                                                    operation,
                                                                ) => {
                                                                    if (
                                                                        operation ===
                                                                            'add' ||
                                                                        operation ===
                                                                            'subtract'
                                                                    ) {
                                                                        updateDraft(
                                                                            draft.id,
                                                                            {
                                                                                operation,
                                                                            },
                                                                        );
                                                                    }
                                                                }}
                                                                className="h-10"
                                                                aria-label="Efeito do ajuste no total"
                                                            >
                                                                <ToggleGroupItem
                                                                    value="add"
                                                                    className="h-10 px-3 data-[state=on]:bg-inflow-soft data-[state=on]:text-inflow-soft-foreground"
                                                                >
                                                                    <ArrowUpFromLine className="size-4" />
                                                                    Acréscimo
                                                                </ToggleGroupItem>
                                                                <ToggleGroupItem
                                                                    value="subtract"
                                                                    className="h-10 px-3 data-[state=on]:bg-outflow-soft data-[state=on]:text-outflow-soft-foreground"
                                                                >
                                                                    <ArrowDownToLine className="size-4" />
                                                                    Desconto
                                                                </ToggleGroupItem>
                                                            </ToggleGroup>
                                                        </div>
                                                    </div>
                                                ) : null}

                                                {isInvalid ? (
                                                    <p className="mt-2 text-xs text-destructive">
                                                        {draft.code ===
                                                            'other' &&
                                                        draft.label.trim() ===
                                                            ''
                                                            ? 'Informe uma descrição para este ajuste.'
                                                            : 'Informe um valor maior que zero.'}
                                                    </p>
                                                ) : null}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </section>

                        {attemptedSubmit && projectedNetCents <= 0 ? (
                            <div className="rounded-lg border border-destructive/30 bg-destructive-soft px-4 py-3 text-sm text-destructive-soft-foreground">
                                As reduções não podem zerar ou superar o valor
                                principal somado aos acréscimos.
                            </div>
                        ) : null}
                    </div>
                </div>

                <DialogFooter className="border-t border-border/60 bg-muted/15 px-5 py-4 sm:px-6">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={disabled}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        onClick={handleConfirm}
                        disabled={disabled}
                    >
                        <Check className="size-4" />
                        Aplicar {drafts.length}{' '}
                        {drafts.length === 1 ? 'ajuste' : 'ajustes'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

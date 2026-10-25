import {
    AlertCircle,
    CheckCircle2,
    CreditCard,
    Plus,
    RefreshCw,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
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
import { cn } from '@/lib/utils';

export interface InstallmentItem {
    amount: string;
    due_date: string;
}

interface InstallmentsConfigProps {
    totalAmount: string;
    initialDueDate: string;
    confirmedInstallments: InstallmentItem[];
    onConfirm: (installments: InstallmentItem[]) => void;
    disabled?: boolean;
}

const moneyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

function parseAmount(value: string): number {
    return Number.parseFloat(value) || 0;
}

function formatDate(value: string): string {
    if (!value) {
        return '-';
    }

    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function dateStringToDate(value: string): Date | undefined {
    if (!value) {
        return undefined;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return undefined;
    }

    return new Date(year, month - 1, day);
}

function dateToDateString(value: Date): string {
    return [
        String(value.getFullYear()),
        String(value.getMonth() + 1).padStart(2, '0'),
        String(value.getDate()).padStart(2, '0'),
    ].join('-');
}

function addMonths(dateValue: string, months: number): string {
    if (!dateValue) {
        return '';
    }

    const [year, month, day] = dateValue.split('-').map(Number);

    if (!year || !month || !day) {
        return '';
    }

    const targetMonth = month - 1 + months;
    const targetYear = year + Math.floor(targetMonth / 12);
    const normalizedMonth = ((targetMonth % 12) + 12) % 12;
    const lastDay = new Date(targetYear, normalizedMonth + 1, 0).getDate();
    const clampedDay = Math.min(day, lastDay);

    return [
        String(targetYear),
        String(normalizedMonth + 1).padStart(2, '0'),
        String(clampedDay).padStart(2, '0'),
    ].join('-');
}

function todayString(): string {
    return dateToDateString(new Date());
}

function distributeEvenly(
    totalAmount: string,
    count: number,
    firstDueDate: string,
): InstallmentItem[] {
    const totalCents = Math.round(parseAmount(totalAmount) * 100);
    const quantity = Math.max(1, count);
    const baseAmount = Math.floor(totalCents / quantity);
    const remainder = totalCents - baseAmount * quantity;
    const baseDueDate = firstDueDate || todayString();

    return Array.from({ length: quantity }, (_, index) => {
        const isLast = index === quantity - 1;
        const amountCents = baseAmount + (isLast ? remainder : 0);

        return {
            amount: (amountCents / 100).toFixed(2),
            due_date: addMonths(baseDueDate, index),
        };
    });
}

export function InstallmentsConfig({
    totalAmount,
    initialDueDate,
    confirmedInstallments,
    onConfirm,
    disabled,
}: InstallmentsConfigProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [count, setCount] = useState(1);
    const [rows, setRows] = useState<InstallmentItem[]>([]);

    function handleOpen(): void {
        if (confirmedInstallments.length > 0) {
            setCount(confirmedInstallments.length);
            setRows(
                confirmedInstallments.map((installment) => ({
                    ...installment,
                })),
            );
        } else {
            const baseDueDate = initialDueDate || todayString();

            setCount(1);
            setRows(distributeEvenly(totalAmount, 1, baseDueDate));
        }

        setIsOpen(true);
    }

    function handleCountChange(nextCount: number): void {
        const normalizedCount = Math.max(1, Math.min(360, nextCount || 1));
        const baseDueDate =
            rows[0]?.due_date || initialDueDate || todayString();

        setCount(normalizedCount);
        setRows(distributeEvenly(totalAmount, normalizedCount, baseDueDate));
    }

    function handleDistribute(): void {
        const baseDueDate =
            rows[0]?.due_date || initialDueDate || todayString();

        setRows(distributeEvenly(totalAmount, rows.length, baseDueDate));
        setCount(rows.length);
    }

    function handleAddRow(): void {
        const lastDueDate =
            rows[rows.length - 1]?.due_date || initialDueDate || '';
        const nextRows = [
            ...rows,
            {
                amount: '0.00',
                due_date: lastDueDate ? addMonths(lastDueDate, 1) : '',
            },
        ];

        setRows(nextRows);
        setCount(nextRows.length);
    }

    function handleRemoveRow(index: number): void {
        if (rows.length <= 1) {
            return;
        }

        const nextRows = rows.filter(
            (_, currentIndex) => currentIndex !== index,
        );

        setRows(nextRows);
        setCount(nextRows.length);
    }

    function updateRow(
        index: number,
        field: keyof InstallmentItem,
        value: string,
    ): void {
        setRows((currentRows) =>
            currentRows.map((row, currentIndex) =>
                currentIndex === index ? { ...row, [field]: value } : row,
            ),
        );
    }

    function handleConfirm(): void {
        if (!isValid) {
            return;
        }

        onConfirm(rows);
        setIsOpen(false);
    }

    const totalAmountNumber = parseAmount(totalAmount);
    const rowsSum = rows.reduce((sum, row) => sum + parseAmount(row.amount), 0);
    const diffCents =
        Math.round(rowsSum * 100) - Math.round(totalAmountNumber * 100);
    const hasZeroRows = rows.some((row) => parseAmount(row.amount) <= 0);
    const isValid = diffCents === 0 && rows.length > 0 && !hasZeroRows;
    const hasConfirmed = confirmedInstallments.length > 0;

    return (
        <>
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium">Parcelamento</p>
                        {hasConfirmed ? (
                            <p className="text-xs text-muted-foreground">
                                {confirmedInstallments.length} parcela
                                {confirmedInstallments.length > 1
                                    ? 's'
                                    : ''}{' '}
                                configurada
                                {confirmedInstallments.length > 1 ? 's' : ''}
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Opcional parcelar este título em múltiplos
                                vencimentos
                            </p>
                        )}
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        variant={hasConfirmed ? 'default' : 'outline'}
                        onClick={handleOpen}
                        disabled={disabled}
                    >
                        <CreditCard className="mr-1.5 size-3.5" />
                        {hasConfirmed
                            ? 'Editar parcelas'
                            : 'Configurar parcelas'}
                    </Button>
                </div>

                {hasConfirmed ? (
                    <div className="divide-y rounded-lg border text-sm">
                        {confirmedInstallments.map((installment, index) => (
                            <div
                                key={`${installment.due_date}-${index}`}
                                className="flex items-center justify-between px-3 py-2"
                            >
                                <span className="truncate text-muted-foreground">
                                    {index + 1}/{confirmedInstallments.length} -{' '}
                                    {formatDate(installment.due_date)}
                                </span>
                                <span className="ml-4 shrink-0 font-medium tabular-nums">
                                    {moneyFormatter.format(
                                        parseAmount(installment.amount),
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : null}
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Configurar parcelas</DialogTitle>
                        <DialogDescription className="sr-only">
                            Configure quantidade, vencimentos e valores das
                            parcelas.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 space-y-4 overflow-y-auto pr-1">
                        <Card className="bg-muted">
                            <CardContent className="px-4 py-3">
                                <div className="flex items-end gap-3">
                                    <div className="flex-1">
                                        <p className="mb-1 text-xs text-muted-foreground">
                                            Valor total
                                        </p>
                                        <p className="text-xl font-semibold tabular-nums">
                                            {moneyFormatter.format(
                                                totalAmountNumber,
                                            )}
                                        </p>
                                    </div>

                                    <div className="w-28">
                                        <p className="mb-1 text-xs text-muted-foreground">
                                            Nº de parcelas
                                        </p>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={360}
                                            value={count}
                                            onChange={(event) =>
                                                handleCountChange(
                                                    Number(event.target.value),
                                                )
                                            }
                                            aria-label="Número de parcelas"
                                            className="text-center"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="mt-10 space-y-2">
                            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleDistribute}
                                >
                                    <RefreshCw className="mr-1.5 size-3.5" />
                                    Distribuir igualmente
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleAddRow}
                                    className="w-full border border-dashed"
                                >
                                    <Plus className="mr-1.5 size-3.5" />
                                    Adicionar parcela
                                </Button>
                            </div>

                            <div className="grid grid-cols-[28px_1fr_1fr_32px] gap-2 px-1 pb-1">
                                <span className="text-center text-xs text-muted-foreground">
                                    #
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Valor
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Vencimento
                                </span>
                                <span />
                            </div>

                            {rows.map((row, index) => (
                                <div
                                    key={`${row.due_date}-${index}`}
                                    className="grid grid-cols-[28px_1fr_1fr_32px] items-center gap-2"
                                >
                                    <span className="text-center text-xs font-medium text-muted-foreground">
                                        {index + 1}
                                    </span>
                                    <InputMoney
                                        value={row.amount}
                                        onChange={(value) =>
                                            updateRow(index, 'amount', value)
                                        }
                                        aria-label={`Valor da parcela ${index + 1}`}
                                    />
                                    <DatePicker
                                        value={dateStringToDate(row.due_date)}
                                        onChange={(date) =>
                                            updateRow(
                                                index,
                                                'due_date',
                                                date
                                                    ? dateToDateString(date)
                                                    : '',
                                            )
                                        }
                                        placeholder="Vencimento"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-muted-foreground hover:bg-destructive-soft hover:text-destructive-soft-foreground"
                                        aria-label="Remover parcela"
                                        onClick={() => handleRemoveRow(index)}
                                        disabled={rows.length <= 1}
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            ))}
                        </div>

                        <div
                            className={cn(
                                'flex items-center gap-2 rounded-lg p-3 text-sm',
                                isValid
                                    ? 'bg-success-soft text-success-soft-foreground'
                                    : 'bg-warning-soft text-warning-soft-foreground',
                            )}
                        >
                            {isValid ? (
                                <>
                                    <CheckCircle2 className="size-4 shrink-0" />
                                    <span>
                                        Total conferido:{' '}
                                        {moneyFormatter.format(rowsSum)}
                                    </span>
                                </>
                            ) : (
                                <>
                                    <AlertCircle className="size-4 shrink-0" />
                                    <span>
                                        {diffCents > 0
                                            ? `Excesso de ${moneyFormatter.format(
                                                  diffCents / 100,
                                              )} - reduza os valores`
                                            : `Faltam ${moneyFormatter.format(
                                                  Math.abs(diffCents) / 100,
                                              )} para atingir o total`}
                                    </span>
                                </>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsOpen(false)}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={handleConfirm}
                            disabled={!isValid}
                        >
                            <CheckCircle2 className="mr-1.5 size-3.5" />
                            Confirmar {rows.length}{' '}
                            {rows.length === 1 ? 'parcela' : 'parcelas'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

import { useForm } from '@inertiajs/react';
import { Loader2, ReceiptText } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

import { store } from '@/actions/App/Modules/Finance/Http/Controllers/FinancialSettlementController';
import { AsyncSelect } from '@/components/async-select';
import type { AsyncSelectOption } from '@/components/async-select';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import FormField from '@/components/ui/forms/form-field';
import { InputMoney } from '@/components/ui/input-money';
import { Textarea } from '@/components/ui/textarea';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialAccount } from '@/types/app/financial-account';
import type { FinancialInstallment } from '@/types/app/financial-entry';
import type { PaymentMethod } from '@/types/app/payment-method';

interface SettlementDialogProps {
    /** A baixa pertence a parcela; do titulo so precisamos da empresa e da moeda. */
    companyId: number;
    currency: string;
    installment: FinancialInstallment | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface SettlementFormData {
    settled_at: string;
    financial_account_id: string;
    payment_method_id: string;
    gross_amount: string;
    discount_amount: string;
    interest_amount: string;
    penalty_amount: string;
    notes: string;
}

type FieldErrors = Record<string, string | undefined>;

const todayString = new Date().toISOString().slice(0, 10);

function centsToDecimal(cents: number): string {
    return (cents / 100).toFixed(2);
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

function stringToDate(value: string): Date | undefined {
    if (!value) {
        return undefined;
    }

    return new Date(`${value}T00:00:00`);
}

function dateToString(date: Date | undefined): string {
    return date ? date.toISOString().slice(0, 10) : '';
}

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

function paymentMethodOption(item: unknown): AsyncSelectOption {
    const paymentMethod = item as Partial<PaymentMethod>;
    const description = [paymentMethod.type_label, paymentMethod.code]
        .filter(Boolean)
        .join(' - ');

    return {
        value: String(paymentMethod.id ?? ''),
        label: String(paymentMethod.name ?? ''),
        description,
    };
}

function financialAccountOption(item: unknown): AsyncSelectOption {
    const financialAccount = item as Partial<FinancialAccount>;
    const description = [
        financialAccount.type_label,
        financialAccount.institution_name,
        financialAccount.account_number,
    ]
        .filter(Boolean)
        .join(' - ');

    return {
        value: String(financialAccount.id ?? ''),
        label: String(
            financialAccount.display_name ?? financialAccount.name ?? '',
        ),
        description,
    };
}

function firstError(errors: FieldErrors): string {
    return (
        Object.values(errors).find((message): message is string =>
            Boolean(message),
        ) ?? 'Revise os dados da baixa.'
    );
}

export function SettlementDialog({
    companyId,
    currency,
    installment,
    open,
    onOpenChange,
}: SettlementDialogProps) {
    const form = useForm<SettlementFormData>({
        settled_at: todayString,
        financial_account_id: '',
        payment_method_id: '',
        gross_amount: '0.00',
        discount_amount: '0.00',
        interest_amount: '0.00',
        penalty_amount: '0.00',
        notes: '',
    });
    const { clearErrors, data, post, processing, reset, setData } = form;
    const errors = form.errors as FieldErrors;

    const grossAmount = parseAmountToCents(data.gross_amount);
    const discountAmount = parseAmountToCents(data.discount_amount);
    const interestAmount = parseAmountToCents(data.interest_amount);
    const penaltyAmount = parseAmountToCents(data.penalty_amount);
    const netAmount =
        grossAmount - discountAmount + interestAmount + penaltyAmount;
    const balanceAmount = installment?.balance_amount ?? 0;

    useEffect(() => {
        if (!open || !installment) {
            return;
        }

        setData({
            settled_at: todayString,
            financial_account_id: '',
            payment_method_id: '',
            gross_amount: centsToDecimal(installment.balance_amount),
            discount_amount: '0.00',
            interest_amount: '0.00',
            penalty_amount: '0.00',
            notes: '',
        });
        clearErrors();
    }, [clearErrors, installment, open, setData]);

    function close(): void {
        onOpenChange(false);
        reset();
        clearErrors();
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!installment) {
            return;
        }

        if (grossAmount <= 0) {
            toast.error('Informe um valor de baixa maior que zero.');

            return;
        }

        if (grossAmount > balanceAmount) {
            toast.error('A baixa não pode exceder o saldo da parcela.');

            return;
        }

        if (netAmount <= 0) {
            toast.error('O valor líquido da baixa deve ser maior que zero.');

            return;
        }

        if (!data.financial_account_id) {
            toast.error('Selecione a conta financeira da baixa.');

            return;
        }

        form.transform(() => ({
            settled_at: data.settled_at || null,
            financial_account_id: Number(data.financial_account_id),
            payment_method_id: data.payment_method_id
                ? Number(data.payment_method_id)
                : null,
            gross_amount: grossAmount,
            discount_amount: discountAmount,
            interest_amount: interestAmount,
            penalty_amount: penaltyAmount,
            origin: 'manual',
            metadata: data.notes.trim() ? { notes: data.notes.trim() } : null,
        }));

        post(store.url(installment.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Baixa registrada.');
                close();
            },
            onError: (requestErrors) =>
                toast.error(firstError(requestErrors as FieldErrors)),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) =>
                nextOpen ? onOpenChange(true) : close()
            }
        >
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                            <ReceiptText className="size-5" />
                        </span>
                        <div>
                            <DialogTitle>Registrar baixa</DialogTitle>
                            <DialogDescription>
                                {installment
                                    ? `Parcela ${installment.installment_number} - saldo ${money(installment.balance_amount, installment.currency)}`
                                    : 'Selecione uma parcela aberta.'}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Data da baixa"
                            error={errors.settled_at}
                        >
                            <DatePicker
                                value={stringToDate(data.settled_at)}
                                onChange={(date) =>
                                    setData('settled_at', dateToString(date))
                                }
                            />
                        </FormField>

                        <FormField
                            label="Método"
                            error={errors.payment_method_id}
                        >
                            <AsyncSelect
                                value={data.payment_method_id || null}
                                endpoint={apiRoutes.paymentMethods.options}
                                params={{
                                    company_id: companyId,
                                    active: '1',
                                }}
                                transformOption={paymentMethodOption}
                                placeholder="Selecione o método"
                                searchPlaceholder="Buscar método..."
                                emptyMessage="Nenhum método ativo encontrado."
                                clearable
                                onChange={(value) =>
                                    setData('payment_method_id', value ?? '')
                                }
                            />
                        </FormField>

                        <FormField
                            label="Valor da baixa"
                            required
                            error={errors.gross_amount}
                        >
                            <InputMoney
                                value={data.gross_amount}
                                onChange={(value) =>
                                    setData('gross_amount', value)
                                }
                                aria-invalid={Boolean(errors.gross_amount)}
                            />
                        </FormField>

                        <FormField
                            label="Conta financeira"
                            required
                            error={errors.financial_account_id}
                        >
                            <AsyncSelect
                                value={data.financial_account_id || null}
                                endpoint={apiRoutes.financialAccounts.options}
                                params={{
                                    company_id: companyId,
                                    active: '1',
                                }}
                                transformOption={financialAccountOption}
                                placeholder="Selecione a conta"
                                searchPlaceholder="Buscar conta..."
                                emptyMessage="Nenhuma conta ativa encontrada."
                                onChange={(value) =>
                                    setData('financial_account_id', value ?? '')
                                }
                            />
                        </FormField>

                        <FormField
                            label="Desconto"
                            error={errors.discount_amount}
                        >
                            <InputMoney
                                value={data.discount_amount}
                                onChange={(value) =>
                                    setData('discount_amount', value)
                                }
                                aria-invalid={Boolean(errors.discount_amount)}
                            />
                        </FormField>

                        <FormField label="Juros" error={errors.interest_amount}>
                            <InputMoney
                                value={data.interest_amount}
                                onChange={(value) =>
                                    setData('interest_amount', value)
                                }
                                aria-invalid={Boolean(errors.interest_amount)}
                            />
                        </FormField>

                        <FormField label="Multa" error={errors.penalty_amount}>
                            <InputMoney
                                value={data.penalty_amount}
                                onChange={(value) =>
                                    setData('penalty_amount', value)
                                }
                                aria-invalid={Boolean(errors.penalty_amount)}
                            />
                        </FormField>

                        <div className="rounded-md border bg-muted/30 px-3 py-2">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Líquido movimentado
                            </p>
                            <p className="mt-1 text-lg font-semibold">
                                {money(netAmount, currency)}
                            </p>
                        </div>

                        <FormField
                            label="Observações"
                            className="sm:col-span-2"
                            error={errors.metadata}
                        >
                            <Textarea
                                value={data.notes}
                                rows={3}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                                placeholder="Referência, comprovante ou observação interna"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={processing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !installment}
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <ReceiptText className="size-4" />
                            )}
                            Registrar baixa
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

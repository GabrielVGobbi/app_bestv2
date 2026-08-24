import { Link, useForm } from '@inertiajs/react';
import {
    CircleAlert,
    Pencil,
    Plus,
    Save,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ClipboardEvent, FormEvent, KeyboardEvent } from 'react';
import { toast } from 'sonner';

import {
    index,
    store,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import { AsyncSelect } from '@/components/async-select';
import type { AsyncSelectOption } from '@/components/async-select';
import FinancialCategoryTreeSelect from '@/components/financial/financial-category-tree-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import FormField from '@/components/ui/forms/form-field';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { apiRoutes } from '@/lib/api/api-routes';
import { cn } from '@/lib/utils';
import type {
    CounterpartyTypeByDirection,
    FinancialAmountAdjustmentOption,
    FinancialAmountComponentCode,
    FinancialAmountOperation,
    FinancialDirection,
    FinancialEntryOption,
} from '@/types/app/financial-entry';
import type { Supplier } from '@/types/app/supplier';
import { AmountAdjustmentsDialog } from './amount-adjustments-dialog';
import type { AmountComponentDraft } from './amount-adjustments-dialog';
import { InstallmentsConfig } from './installments-config';
import type { InstallmentItem } from './installments-config';
import { SupplierQuickCreateDialog } from './supplier-quick-create-dialog';

interface FinancialEntryFormProps {
    directionOptions: FinancialEntryOption[];
    amountAdjustmentOptions: FinancialAmountAdjustmentOption[];
    counterpartyTypeByDirection: CounterpartyTypeByDirection;
}

interface FinancialEntryFormData {
    company_id: string;
    direction: FinancialDirection;
    counterparty_id: string;
    category_id: string;
    description: string;
    issue_date: string;
    competence_date: string;
    due_date: string;
    amount: string;
    notes: string;
    components: AmountComponentDraft[];
    installments: InstallmentItem[];
}

interface SubmittedComponent {
    code: FinancialAmountComponentCode;
    label: string | null;
    operation: FinancialAmountOperation;
    amount: number;
}

interface ValidationIssue {
    message: string;
    selector?: string;
}

type FieldErrors = Record<string, string | undefined>;

const todayString = new Date().toISOString().slice(0, 10);

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

function formatCentsToDisplay(value: number): string {
    const absoluteValue = Math.abs(value);
    const [integerPart, decimalPart] = (absoluteValue / 100)
        .toFixed(2)
        .split('.');

    return `${integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${decimalPart}`;
}

function formatMoneyFromCents(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value / 100);
}

function stringToDate(value: string): Date | undefined {
    if (!value) {
        return undefined;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return undefined;
    }

    return new Date(year, month - 1, day);
}

function dateToString(value: Date): string {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function optionFromResource(item: unknown): AsyncSelectOption {
    if (typeof item !== 'object' || item === null) {
        return {
            value: String(item),
            label: String(item),
        };
    }

    const record = item as Record<string, unknown>;
    const document = record.document ?? record.cnpj;
    const companyName = record.company_name ?? record.nickname;

    return {
        value: String(record.id ?? ''),
        label: String(
            record.name ?? companyName ?? record.email ?? record.id ?? '',
        ),
        description:
            [companyName, document].filter(Boolean).join(' - ') || undefined,
        imageUrl:
            typeof record.logo_url === 'string' ? record.logo_url : undefined,
        avatarInitials:
            typeof record.logo_initials === 'string'
                ? record.logo_initials
                : undefined,
        avatarColor:
            typeof record.logo_color === 'string'
                ? record.logo_color
                : undefined,
    };
}

function firstFormError(errors: FieldErrors): string {
    return (
        Object.values(errors).find((message): message is string =>
            Boolean(message),
        ) ?? 'Verifique os campos obrigatórios.'
    );
}

function directionOptionMeta(direction: FinancialDirection) {
    return direction === 'payable'
        ? {
              icon: TrendingDown,
              categoryType: 'expense' as const,
              counterpartyLabel: 'Fornecedor',
              counterpartyEndpoint: apiRoutes.suppliers.index,
              counterpartyPlaceholder: 'Selecione um fornecedor',
          }
        : {
              icon: TrendingUp,
              categoryType: 'income' as const,
              counterpartyLabel: 'Cliente',
              counterpartyEndpoint: apiRoutes.clients.index,
              counterpartyPlaceholder: 'Selecione um cliente',
          };
}

function SectionHeading({
    id,
    title,
    description,
}: {
    id: string;
    title: string;
    description?: string;
}) {
    return (
        <div className="space-y-1">
            <h2 id={id} className="text-sm font-semibold tracking-[-0.01em]">
                {title}
            </h2>
            {description ? (
                <p className="text-[13px] leading-relaxed text-muted-foreground">
                    {description}
                </p>
            ) : null}
        </div>
    );
}

function AmountHeroInput({
    id,
    value,
    onChange,
    disabled,
    invalid,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    invalid?: boolean;
}) {
    const internalCents = parseAmountToCents(value);
    const displayValue = formatCentsToDisplay(internalCents);

    function handleKeyDown(event: KeyboardEvent<HTMLInputElement>): void {
        if (event.ctrlKey || event.metaKey) {
            return;
        }

        if (event.key === 'Backspace' || event.key === 'Delete') {
            event.preventDefault();
            onChange(centsToDecimal(Math.floor(internalCents / 10)));

            return;
        }

        const navigationKeys = [
            'Tab',
            'Escape',
            'Enter',
            'ArrowLeft',
            'ArrowRight',
            'Home',
            'End',
        ];

        if (navigationKeys.includes(event.key)) {
            return;
        }

        if (!/^\d$/.test(event.key)) {
            event.preventDefault();

            return;
        }

        event.preventDefault();

        const nextCents = internalCents * 10 + Number.parseInt(event.key, 10);

        if (nextCents > 999999999) {
            return;
        }

        onChange(centsToDecimal(nextCents));
    }

    function handlePaste(event: ClipboardEvent<HTMLInputElement>): void {
        event.preventDefault();

        const digits = event.clipboardData
            .getData('text')
            .replace(/[^\d]/g, '');

        if (!digits) {
            return;
        }

        const nextCents = Number.parseInt(digits, 10);

        if (nextCents > 999999999) {
            return;
        }

        onChange(centsToDecimal(nextCents));
    }

    return (
        <div className="flex items-baseline gap-2">
            <span className="text-3xl font-extralight tracking-tight transition-colors select-none">
                R$
            </span>
            <input
                id={id}
                type="text"
                inputMode="numeric"
                value={displayValue}
                onKeyDown={handleKeyDown}
                onPaste={handlePaste}
                onFocus={(event) => event.target.select()}
                readOnly
                disabled={disabled}
                aria-invalid={invalid || undefined}
                placeholder="0,00"
                className="min-w-0 flex-1 rounded-none border-0 border-b border-transparent bg-transparent px-0 py-2 text-3xl font-extralight tracking-tight transition-colors outline-none selection:bg-primary/20 selection:text-foreground placeholder:text-muted-foreground/20 hover:border-border/25 focus:border-border/40 focus:ring-0 focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring/25 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 dark:selection:bg-primary/30"
            />
        </div>
    );
}

function fixedComponent(
    code: FinancialAmountComponentCode,
    label: string,
    operation: FinancialAmountOperation,
    amount: string,
): SubmittedComponent | null {
    const amountInCents = parseAmountToCents(amount);

    if (amountInCents <= 0) {
        return null;
    }

    return {
        code,
        label,
        operation,
        amount: amountInCents,
    };
}

function buildSubmittedComponents(
    data: FinancialEntryFormData,
): SubmittedComponent[] {
    return [
        fixedComponent('principal', 'Principal', 'add', data.amount),
        ...data.components.map((component) =>
            fixedComponent(
                component.code,
                component.label.trim() || 'Componente',
                component.operation,
                component.amount,
            ),
        ),
    ].filter(
        (component): component is SubmittedComponent => component !== null,
    );
}

function installmentsTotal(installments: InstallmentItem[]): number {
    return installments.reduce(
        (total, installment) => total + parseAmountToCents(installment.amount),
        0,
    );
}

function uniqueValidationIssues(
    issues: Array<ValidationIssue | undefined>,
): ValidationIssue[] {
    const uniqueIssues = new Map<string, ValidationIssue>();

    for (const issue of issues) {
        if (!issue || issue.message.trim() === '') {
            continue;
        }

        if (!uniqueIssues.has(issue.message)) {
            uniqueIssues.set(issue.message, issue);
        }
    }

    return Array.from(uniqueIssues.values());
}

function serverErrorSelector(field: string): string | undefined {
    if (field === 'description') {
        return '#financial-entry-description';
    }

    if (field === 'company_id') {
        return '[data-financial-field="company"] button';
    }

    if (field === 'counterparty_id' || field === 'counterparty_type') {
        return '[data-financial-field="counterparty"] button';
    }

    if (field === 'category_id') {
        return '[data-financial-field="category"] button';
    }

    if (field === 'due_date') {
        return '#financial-entry-due-date';
    }

    if (field === 'components' || field.startsWith('components.')) {
        return '[data-financial-section="amount-composition"] button';
    }

    if (field === 'installments' || field.startsWith('installments.')) {
        return '[data-financial-section="installments"] button';
    }

    return undefined;
}

export default function FinancialEntryForm({
    directionOptions,
    amountAdjustmentOptions,
    counterpartyTypeByDirection,
}: FinancialEntryFormProps) {
    const [adjustmentsDialogOpen, setAdjustmentsDialogOpen] = useState(false);
    const [adjustmentsDialogVersion, setAdjustmentsDialogVersion] = useState(0);
    const [detailsReady, setDetailsReady] = useState(false);
    const [supplierQuickCreateOpen, setSupplierQuickCreateOpen] =
        useState(false);
    const [createdSupplierOption, setCreatedSupplierOption] =
        useState<AsyncSelectOption | null>(null);
    const [pendingPopoverOpen, setPendingPopoverOpen] = useState(false);

    const form = useForm<FinancialEntryFormData>({
        company_id: '',
        direction: 'payable',
        counterparty_id: '',
        category_id: '',
        description: 'Lançamento Financeiro',
        issue_date: todayString,
        competence_date: '',
        due_date: '',
        amount: '0.00',
        notes: '',
        components: [],
        installments: [],
    });
    const { data, setData, processing } = form;
    const errors = form.errors as FieldErrors;
    const meta = directionOptionMeta(data.direction);
    const selectedCompanyId = data.company_id ? Number(data.company_id) : null;
    const stepComplete =
        data.description.trim() !== '' && parseAmountToCents(data.amount) > 0;
    const shouldRenderDetails = stepComplete && detailsReady;
    const submittedComponents = useMemo(
        () => buildSubmittedComponents(data),
        [data],
    );
    const grossAmount = submittedComponents
        .filter((component) => component.operation === 'add')
        .reduce((total, component) => total + component.amount, 0);
    const subtractAmount = submittedComponents
        .filter((component) => component.operation === 'subtract')
        .reduce((total, component) => total + component.amount, 0);
    const netAmount = grossAmount - subtractAmount;
    const principalAmount = parseAmountToCents(data.amount);
    const additionsAmount = data.components
        .filter((component) => component.operation === 'add')
        .reduce(
            (total, component) => total + parseAmountToCents(component.amount),
            0,
        );
    const reductionsAmount = data.components
        .filter((component) => component.operation === 'subtract')
        .reduce(
            (total, component) => total + parseAmountToCents(component.amount),
            0,
        );
    const hasNegativeNetAmount = netAmount < 0;
    const displayNetAmount = Math.max(netAmount, 0);
    const netValue = centsToDecimal(displayNetAmount);
    const confirmedInstallmentsTotal = installmentsTotal(data.installments);
    const hasConfirmedInstallments = data.installments.length > 0;
    const installmentsAreValid =
        !hasConfirmedInstallments ||
        (confirmedInstallmentsTotal === netAmount &&
            data.installments.every(
                (installment) => parseAmountToCents(installment.amount) > 0,
            ));
    const canSubmit =
        !processing &&
        stepComplete &&
        data.company_id !== '' &&
        data.category_id !== '' &&
        data.counterparty_id !== '' &&
        data.due_date !== '' &&
        !hasNegativeNetAmount &&
        netAmount > 0 &&
        installmentsAreValid;
    const validationIssues = useMemo(() => {
        const serverIssues = Object.entries(errors).map(
            ([field, message]): ValidationIssue | undefined =>
                message
                    ? {
                          message,
                          selector: serverErrorSelector(field),
                      }
                    : undefined,
        );

        return uniqueValidationIssues([
            ...serverIssues,
            data.description.trim() === ''
                ? {
                      message: 'Informe a descrição do lançamento.',
                      selector: '#financial-entry-description',
                  }
                : undefined,
            parseAmountToCents(data.amount) <= 0
                ? {
                      message: 'Informe o valor bruto.',
                      selector: '#financial-entry-amount',
                  }
                : undefined,
            data.company_id === ''
                ? {
                      message: 'Selecione a empresa do lançamento.',
                      selector: '[data-financial-field="company"] button',
                  }
                : undefined,
            data.counterparty_id === ''
                ? {
                      message: `Selecione ${meta.counterpartyLabel.toLowerCase()}.`,
                      selector: '[data-financial-field="counterparty"] button',
                  }
                : undefined,
            data.category_id === ''
                ? {
                      message: 'Selecione uma categoria financeira.',
                      selector: '[data-financial-field="category"] button',
                  }
                : undefined,
            data.due_date === ''
                ? {
                      message: 'Informe o vencimento do lançamento.',
                      selector: '#financial-entry-due-date',
                  }
                : undefined,
            hasNegativeNetAmount
                ? {
                      message:
                          'Subtrações não podem ser maiores que o valor bruto.',
                      selector:
                          '[data-financial-section="amount-composition"] button',
                  }
                : undefined,
            !hasNegativeNetAmount && netAmount <= 0
                ? {
                      message: 'O valor líquido precisa ser maior que zero.',
                      selector:
                          '[data-financial-section="amount-composition"] button',
                  }
                : undefined,
            !installmentsAreValid
                ? {
                      message:
                          'A soma das parcelas precisa fechar com o líquido calculado.',
                      selector:
                          '[data-financial-section="installments"] button',
                  }
                : undefined,
        ]);
    }, [
        data.amount,
        data.category_id,
        data.company_id,
        data.counterparty_id,
        data.description,
        data.due_date,
        errors,
        hasNegativeNetAmount,
        installmentsAreValid,
        meta.counterpartyLabel,
        netAmount,
    ]);
    const pendingCount = validationIssues.length;
    const counterpartyError =
        errors.counterparty_id ?? errors.counterparty_type;
    const counterpartyParams = useMemo(
        () => ({
            'filter[company_id]': data.company_id || undefined,
        }),
        [data.company_id],
    );

    useEffect(() => {
        if (detailsReady || !stepComplete) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            setDetailsReady(true);
        });

        return () => window.cancelAnimationFrame(frame);
    }, [detailsReady, stepComplete]);

    function changeDirection(direction: FinancialDirection): void {
        setCreatedSupplierOption(null);
        setSupplierQuickCreateOpen(false);
        setData((currentData) => ({
            ...currentData,
            direction,
            category_id: '',
            counterparty_id: '',
        }));
    }

    function removeComponent(id: string): void {
        setData(
            'components',
            data.components.filter((component) => component.id !== id),
        );
    }

    function openAdjustmentsDialog(): void {
        setAdjustmentsDialogVersion((version) => version + 1);
        setAdjustmentsDialogOpen(true);
    }

    function confirmComponents(components: AmountComponentDraft[]): void {
        setData('components', components);
    }

    function selectCreatedSupplier(supplier: Supplier): void {
        const option = optionFromResource(supplier);

        setCreatedSupplierOption(option);
        setData('counterparty_id', option.value);
    }

    function focusPendingField(selector: string): void {
        window.requestAnimationFrame(() => {
            const target = document.querySelector<HTMLElement>(selector);

            target?.focus();
            target?.scrollIntoView({ block: 'center' });
        });
    }

    function selectPendingIssue(issue: ValidationIssue): void {
        if (!issue.selector) {
            return;
        }

        setPendingPopoverOpen(false);
        focusPendingField(issue.selector);
    }

    function focusFirstPendingField(): void {
        const firstFocusableIssue = validationIssues.find(
            (issue) => issue.selector,
        );

        focusPendingField(
            firstFocusableIssue?.selector ?? '#financial-entry-description',
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!canSubmit) {
            toast.error(
                validationIssues[0]?.message ??
                    'Revise os campos obrigatórios para continuar.',
            );
            focusFirstPendingField();

            return;
        }

        const counterpartyType = counterpartyTypeByDirection[data.direction];
        const components = submittedComponents.map((component, position) => ({
            ...component,
            position,
        }));
        const installments =
            data.installments.length > 0
                ? data.installments.map((installment, index) => ({
                      installment_number: index + 1,
                      due_date: installment.due_date || null,
                      competence_date: data.competence_date || null,
                      amount: parseAmountToCents(installment.amount),
                  }))
                : [
                      {
                          installment_number: 1,
                          due_date: data.due_date || null,
                          competence_date: data.competence_date || null,
                          amount: netAmount,
                      },
                  ];

        form.transform(() => ({
            company_id: Number(data.company_id),
            direction: data.direction,
            counterparty_type: counterpartyType,
            counterparty_id: Number(data.counterparty_id),
            category_id: data.category_id ? Number(data.category_id) : null,
            description: data.description.trim(),
            issue_date: data.issue_date || null,
            competence_date: data.competence_date || null,
            due_date: data.due_date || null,
            currency: 'BRL',
            metadata: data.notes.trim() ? { notes: data.notes.trim() } : null,
            components,
            installments,
        }));

        form.post(store.url(), {
            preserveScroll: true,
            onError: (requestErrors: FieldErrors) =>
                toast.error(firstFormError(requestErrors)),
        });
    }

    return (
        <>
            <form
                onSubmit={submit}
                className="relative flex h-full flex-1 flex-col p-0 sm:p-4"
            >
                <Card className="py-2 pt-6">
                    <CardContent className="space-y-7 px-4 sm:px-6">
                        <fieldset className="space-y-2">
                            <legend className="text-sm leading-none font-semibold">
                                Tipo de lançamento{' '}
                                <span className="text-destructive">*</span>
                            </legend>
                            <div className="grid grid-cols-2 gap-3">
                                {directionOptions.map((option) => {
                                    const optionDirection =
                                        option.value as FinancialDirection;
                                    const isSelected =
                                        data.direction === optionDirection;
                                    const isPayable =
                                        optionDirection === 'payable';
                                    const Icon = isPayable
                                        ? TrendingDown
                                        : TrendingUp;

                                    return (
                                        <label
                                            key={option.value}
                                            className={cn(
                                                'relative flex cursor-pointer items-center justify-center gap-3 rounded-xl border px-4 py-3 transition-all duration-300 outline-none has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/30',
                                                isSelected
                                                    ? isPayable
                                                        ? 'scale-[1.02] border-outflow/40 bg-outflow-soft text-outflow-soft-foreground shadow-[0_4px_20px_-4px_color-mix(in_oklab,var(--outflow)_24%,transparent)] ring-1 ring-outflow/20'
                                                        : 'scale-[1.02] border-inflow/40 bg-inflow-soft text-inflow-soft-foreground shadow-[0_4px_20px_-4px_color-mix(in_oklab,var(--inflow)_24%,transparent)] ring-1 ring-inflow/20'
                                                    : 'border-border/60 bg-transparent text-muted-foreground hover:scale-[1.01] hover:bg-muted/50 hover:text-foreground',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="direction"
                                                value={optionDirection}
                                                checked={isSelected}
                                                onChange={() =>
                                                    changeDirection(
                                                        optionDirection,
                                                    )
                                                }
                                                disabled={processing}
                                                className="sr-only"
                                            />
                                            <Icon
                                                className={cn(
                                                    'size-4 transition-transform duration-300',
                                                    isSelected
                                                        ? isPayable
                                                            ? 'translate-y-0.5'
                                                            : '-translate-y-0.5'
                                                        : 'opacity-60',
                                                )}
                                            />
                                            <span className="text-[15px] font-medium tracking-tight">
                                                {option.label}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {errors.direction ? (
                                <p className="text-sm text-destructive">
                                    {errors.direction}
                                </p>
                            ) : null}
                        </fieldset>

                        <div>
                            <label
                                htmlFor="financial-entry-description"
                                className="sr-only"
                            >
                                Descrição do lançamento
                            </label>
                            <input
                                id="financial-entry-description"
                                type="text"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                disabled={processing}
                                placeholder="O que você está lançando?"
                                aria-invalid={
                                    Boolean(errors.description) || undefined
                                }
                                className="w-full rounded-none border-0 border-b border-transparent bg-transparent px-0 py-2 text-2xl font-light tracking-tight transition-colors outline-none selection:bg-primary/20 selection:text-foreground placeholder:text-muted-foreground/30 hover:border-border/25 focus:border-border/40 focus:ring-0 focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring/25 focus-visible:ring-offset-2 dark:selection:bg-primary/30"
                                autoFocus
                            />
                            {errors.description ? (
                                <p className="mt-3 text-sm text-destructive">
                                    {errors.description}
                                </p>
                            ) : (
                                <p className="mt-3 px-1 text-sm text-muted-foreground">
                                    Nomeie seu lançamento de forma clara e
                                    objetiva.
                                </p>
                            )}
                        </div>

                        <FormField
                            id="financial-entry-amount"
                            label="Valor Bruto"
                            required
                            error={
                                errors.components ??
                                errors['components.0.amount']
                            }
                        >
                            <AmountHeroInput
                                id="financial-entry-amount"
                                value={data.amount}
                                onChange={(value) => setData('amount', value)}
                                disabled={processing}
                                invalid={Boolean(
                                    errors.components ??
                                    errors['components.0.amount'],
                                )}
                            />
                        </FormField>

                        {hasNegativeNetAmount ? (
                            <div className="rounded-xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                                Subtrações não podem ser maiores que o valor
                                bruto. Revise os ajustes no valor para
                                continuar.
                            </div>
                        ) : null}

                        {shouldRenderDetails ? (
                            <div className="space-y-9 border-t border-border/60 pt-8">
                                <section
                                    aria-labelledby="entry-data-heading"
                                    className="space-y-5"
                                >
                                    <SectionHeading
                                        id="entry-data-heading"
                                        title="Dados do lançamento"
                                        description="Defina a empresa responsável, a contraparte e a classificação financeira."
                                    />

                                    <div className="@container">
                                        <div className="grid gap-5 @min-[520px]:grid-cols-2">
                                            <div
                                                data-financial-field="company"
                                                className="min-w-0 space-y-2"
                                            >
                                                <div className="flex min-h-7 items-center">
                                                    <span className="text-sm leading-none font-semibold">
                                                        Empresa{' '}
                                                        <span className="text-destructive">
                                                            *
                                                        </span>
                                                    </span>
                                                </div>
                                                <div>
                                                    <AsyncSelect
                                                        endpoint={
                                                            apiRoutes.companies
                                                                .index
                                                        }
                                                        value={
                                                            data.company_id ||
                                                            null
                                                        }
                                                        onChange={(value) => {
                                                            setCreatedSupplierOption(
                                                                null,
                                                            );
                                                            setData(
                                                                (
                                                                    currentData,
                                                                ) => ({
                                                                    ...currentData,
                                                                    company_id:
                                                                        value ??
                                                                        '',
                                                                    category_id:
                                                                        '',
                                                                    counterparty_id:
                                                                        '',
                                                                }),
                                                            );
                                                        }}
                                                        placeholder="Selecione uma empresa"
                                                        clearable
                                                        disabled={processing}
                                                        transformOption={
                                                            optionFromResource
                                                        }
                                                        className="min-h-10"
                                                    />
                                                </div>

                                                {errors.company_id ? (
                                                    <p className="text-sm text-destructive">
                                                        {errors.company_id}
                                                    </p>
                                                ) : null}
                                            </div>

                                            <div
                                                data-financial-field="counterparty"
                                                className="min-w-0 space-y-2"
                                            >
                                                <div className="flex min-h-7 items-center justify-between gap-2">
                                                    <span className="text-sm leading-none font-semibold">
                                                        {meta.counterpartyLabel}{' '}
                                                        <span className="text-destructive">
                                                            *
                                                        </span>
                                                    </span>

                                                    {data.direction ===
                                                    'payable' ? (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2 text-xs"
                                                            disabled={
                                                                processing ||
                                                                !data.company_id
                                                            }
                                                            onClick={() =>
                                                                setSupplierQuickCreateOpen(
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            <Plus className="size-3.5" />
                                                            Criar fornecedor
                                                        </Button>
                                                    ) : null}
                                                </div>

                                                <AsyncSelect
                                                    key={`${data.direction}-${data.company_id}`}
                                                    endpoint={
                                                        meta.counterpartyEndpoint
                                                    }
                                                    value={
                                                        data.counterparty_id ||
                                                        null
                                                    }
                                                    options={
                                                        createdSupplierOption
                                                            ? [
                                                                  createdSupplierOption,
                                                              ]
                                                            : undefined
                                                    }
                                                    params={counterpartyParams}
                                                    onChange={(value) => {
                                                        setCreatedSupplierOption(
                                                            null,
                                                        );
                                                        setData(
                                                            'counterparty_id',
                                                            value ?? '',
                                                        );
                                                    }}
                                                    placeholder={
                                                        meta.counterpartyPlaceholder
                                                    }
                                                    clearable
                                                    disabled={
                                                        processing ||
                                                        !data.company_id
                                                    }
                                                    transformOption={
                                                        optionFromResource
                                                    }
                                                    className="min-h-10"
                                                />

                                                {counterpartyError ? (
                                                    <p className="text-sm text-destructive">
                                                        {counterpartyError}
                                                    </p>
                                                ) : null}
                                            </div>

                                            <FormField
                                                label="Categoria"
                                                required
                                                error={errors.category_id}
                                                className="min-w-0 @min-[520px]:col-span-2"
                                            >
                                                <div data-financial-field="category">
                                                    <FinancialCategoryTreeSelect
                                                        value={
                                                            data.category_id ||
                                                            null
                                                        }
                                                        onChange={(value) =>
                                                            setData(
                                                                'category_id',
                                                                value ?? '',
                                                            )
                                                        }
                                                        type={meta.categoryType}
                                                        companyId={
                                                            data.company_id
                                                                ? Number(
                                                                      data.company_id,
                                                                  )
                                                                : null
                                                        }
                                                        mode="entry"
                                                        placeholder={
                                                            data.direction ===
                                                            'receivable'
                                                                ? 'Selecione uma categoria de entrada'
                                                                : 'Selecione uma categoria de despesa'
                                                        }
                                                        className="text-base"
                                                        disabled={processing}
                                                    />
                                                </div>
                                            </FormField>
                                        </div>
                                    </div>
                                </section>

                                <section
                                    data-financial-section="amount-composition"
                                    aria-labelledby="amount-composition-heading"
                                    className="space-y-5 border-t border-border/60 pt-8"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <SectionHeading
                                            id="amount-composition-heading"
                                            title="Composição do valor"
                                            description="Inclua descontos, taxas, impostos ou outros ajustes quando necessário."
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={
                                                data.components.length > 0
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            onClick={openAdjustmentsDialog}
                                            disabled={processing}
                                            className="min-h-10 shrink-0 self-start px-3 transition-transform active:scale-[0.96] motion-reduce:active:scale-100"
                                        >
                                            {data.components.length > 0 ? (
                                                <Pencil className="size-4" />
                                            ) : (
                                                <Plus className="size-4" />
                                            )}
                                            {data.components.length > 0
                                                ? 'Editar ajustes'
                                                : 'Adicionar ajuste'}
                                        </Button>
                                    </div>

                                    {data.components.length === 0 ? (
                                        <button
                                            type="button"
                                            onClick={openAdjustmentsDialog}
                                            disabled={processing}
                                            className="flex min-h-20 w-full items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/15 px-4 text-sm text-muted-foreground transition-[border-color,background-color,color] duration-150 hover:border-foreground/20 hover:bg-muted/30 hover:text-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                                        >
                                            Nenhum ajuste informado. Clique para
                                            compor o valor.
                                        </button>
                                    ) : (
                                        <div className="overflow-hidden rounded-xl border border-border/70 bg-background shadow-xs">
                                            {data.components.map(
                                                (component, index) => {
                                                    const option =
                                                        amountAdjustmentOptions.find(
                                                            (item) =>
                                                                item.value ===
                                                                component.code,
                                                        );

                                                    return (
                                                        <div
                                                            key={component.id}
                                                            className={cn(
                                                                'flex min-h-14 items-center gap-3 px-3 py-2.5 sm:px-4',
                                                                index > 0 &&
                                                                    'border-t border-border/60',
                                                            )}
                                                        >
                                                            <button
                                                                type="button"
                                                                onClick={
                                                                    openAdjustmentsDialog
                                                                }
                                                                className="flex min-w-0 flex-1 items-center gap-3 rounded-md text-left outline-none focus-visible:ring-2 focus-visible:ring-ring/20"
                                                            >
                                                                <span
                                                                    className={cn(
                                                                        'flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-semibold',
                                                                        component.operation ===
                                                                            'add'
                                                                            ? 'bg-inflow-soft text-inflow-soft-foreground'
                                                                            : component.operation ===
                                                                                'subtract'
                                                                              ? 'bg-outflow-soft text-outflow-soft-foreground'
                                                                              : 'bg-secondary text-secondary-foreground',
                                                                    )}
                                                                >
                                                                    {component.operation ===
                                                                    'add'
                                                                        ? '+'
                                                                        : component.operation ===
                                                                            'subtract'
                                                                          ? '−'
                                                                          : '•'}
                                                                </span>
                                                                <span className="min-w-0 flex-1">
                                                                    <span className="flex items-center gap-2">
                                                                        <span className="truncate text-sm font-medium">
                                                                            {component.code ===
                                                                            'other'
                                                                                ? component.label
                                                                                : (option?.label ??
                                                                                  component.label)}
                                                                        </span>
                                                                        <Badge
                                                                            variant={
                                                                                component.operation ===
                                                                                'add'
                                                                                    ? 'inflow'
                                                                                    : component.operation ===
                                                                                        'subtract'
                                                                                      ? 'outflow'
                                                                                      : 'secondary'
                                                                            }
                                                                            className="hidden sm:inline-flex"
                                                                        >
                                                                            {component.operation ===
                                                                            'add'
                                                                                ? 'Acréscimo'
                                                                                : component.operation ===
                                                                                    'subtract'
                                                                                  ? 'Redução'
                                                                                  : 'Informativo'}
                                                                        </Badge>
                                                                    </span>
                                                                </span>
                                                            </button>
                                                            <span
                                                                className={cn(
                                                                    'shrink-0 text-sm font-semibold tabular-nums sm:text-base',
                                                                    component.operation ===
                                                                        'add'
                                                                        ? 'text-inflow'
                                                                        : component.operation ===
                                                                            'subtract'
                                                                          ? 'text-outflow'
                                                                          : 'text-muted-foreground',
                                                                )}
                                                            >
                                                                {component.operation ===
                                                                'add'
                                                                    ? '+'
                                                                    : component.operation ===
                                                                        'subtract'
                                                                      ? '−'
                                                                      : ''}
                                                                {formatMoneyFromCents(
                                                                    parseAmountToCents(
                                                                        component.amount,
                                                                    ),
                                                                )}
                                                            </span>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    removeComponent(
                                                                        component.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    processing
                                                                }
                                                                aria-label={`Remover ${option?.label ?? component.label}`}
                                                                className="size-10 shrink-0 text-muted-foreground hover:text-destructive"
                                                            >
                                                                <span
                                                                    aria-hidden="true"
                                                                    className="text-lg leading-none"
                                                                >
                                                                    ×
                                                                </span>
                                                            </Button>
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </div>
                                    )}

                                    <div className="overflow-hidden rounded-2xl border border-border/70 bg-muted/25 shadow-[0_1px_0_oklch(0_0_0/0.03)]">
                                        <div className="grid grid-cols-3 divide-x divide-border/60 border-b border-border/60">
                                            <div className="flex min-w-0 flex-col gap-1 px-3 py-3 sm:px-4">
                                                <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                    Principal
                                                </span>
                                                <span className="truncate text-sm font-semibold tabular-nums">
                                                    {formatMoneyFromCents(
                                                        principalAmount,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex min-w-0 flex-col gap-1 px-3 py-3 sm:px-4">
                                                <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                    Acréscimos
                                                </span>
                                                <span className="truncate text-sm font-semibold text-inflow tabular-nums">
                                                    +
                                                    {formatMoneyFromCents(
                                                        additionsAmount,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex min-w-0 flex-col gap-1 px-3 py-3 sm:px-4">
                                                <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                    Reduções
                                                </span>
                                                <span className="truncate text-sm font-semibold text-outflow tabular-nums">
                                                    −
                                                    {formatMoneyFromCents(
                                                        reductionsAmount,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between gap-4 bg-background/70 px-4 py-4 sm:px-5">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">
                                                    {data.direction ===
                                                    'payable'
                                                        ? 'Valor líquido a pagar'
                                                        : 'Valor líquido a receber'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Principal + acréscimos −
                                                    reduções
                                                </p>
                                            </div>
                                            <span
                                                className={cn(
                                                    'shrink-0 text-xl font-semibold tracking-tight tabular-nums sm:text-2xl',
                                                    netAmount <= 0
                                                        ? 'text-destructive'
                                                        : data.direction ===
                                                            'payable'
                                                          ? 'text-outflow'
                                                          : 'text-inflow',
                                                )}
                                            >
                                                {formatMoneyFromCents(
                                                    netAmount,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    {hasConfirmedInstallments &&
                                    !installmentsAreValid ? (
                                        <Alert className="border-warning/30 bg-warning-soft text-warning-soft-foreground">
                                            <CircleAlert className="size-4" />
                                            <AlertDescription className="text-warning-soft-foreground">
                                                O valor líquido mudou. Revise as
                                                parcelas para que a soma volte a
                                                conferir antes de criar o
                                                lançamento.
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}
                                </section>

                                <section
                                    aria-labelledby="dates-heading"
                                    className="space-y-5 border-t border-border/60 pt-8"
                                >
                                    <SectionHeading
                                        id="dates-heading"
                                        title="Datas"
                                        description="Informe a emissão, a competência contábil e o vencimento do título."
                                    />

                                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                        <FormField
                                            label="Emissão"
                                            error={errors.issue_date}
                                            className="min-w-0 grid-cols-[minmax(0,1fr)]"
                                        >
                                            <DatePicker
                                                value={stringToDate(
                                                    data.issue_date,
                                                )}
                                                onChange={(date) =>
                                                    setData(
                                                        'issue_date',
                                                        date
                                                            ? dateToString(date)
                                                            : '',
                                                    )
                                                }
                                                disabled={processing}
                                            />
                                        </FormField>

                                        <FormField
                                            label="Competência"
                                            error={errors.competence_date}
                                            className="min-w-0 grid-cols-[minmax(0,1fr)]"
                                        >
                                            <DatePicker
                                                value={stringToDate(
                                                    data.competence_date,
                                                )}
                                                onChange={(date) =>
                                                    setData(
                                                        'competence_date',
                                                        date
                                                            ? dateToString(date)
                                                            : '',
                                                    )
                                                }
                                                disabled={processing}
                                            />
                                        </FormField>

                                        <FormField
                                            label="Vencimento"
                                            required
                                            error={errors.due_date}
                                            className="min-w-0 grid-cols-[minmax(0,1fr)]"
                                        >
                                            <DatePicker
                                                id="financial-entry-due-date"
                                                value={stringToDate(
                                                    data.due_date,
                                                )}
                                                onChange={(date) =>
                                                    setData(
                                                        'due_date',
                                                        date
                                                            ? dateToString(date)
                                                            : '',
                                                    )
                                                }
                                                disabled={processing}
                                            />
                                        </FormField>
                                    </div>
                                </section>

                                <section
                                    data-financial-section="installments"
                                    aria-labelledby="installments-heading"
                                    className="space-y-5 border-t border-border/60 pt-8"
                                >
                                    <SectionHeading
                                        id="installments-heading"
                                        title="Parcelamento"
                                        description="Divida o valor líquido em diferentes vencimentos, se necessário."
                                    />
                                    <InstallmentsConfig
                                        totalAmount={netValue}
                                        initialDueDate={data.due_date}
                                        confirmedInstallments={
                                            data.installments
                                        }
                                        onConfirm={(installments) =>
                                            setData(
                                                'installments',
                                                installments,
                                            )
                                        }
                                        disabled={processing || netAmount <= 0}
                                    />
                                </section>

                                <section
                                    aria-labelledby="notes-heading"
                                    className="space-y-5 border-t border-border/60 pt-8"
                                >
                                    <SectionHeading
                                        id="notes-heading"
                                        title="Observações"
                                        description="Registre informações complementares que ajudem na identificação do lançamento."
                                    />
                                    <FormField>
                                        <Textarea
                                            value={data.notes}
                                            onChange={(event) =>
                                                setData(
                                                    'notes',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={processing}
                                            rows={3}
                                            aria-label="Observações"
                                            placeholder="Informações adicionais"
                                            className="resize-none selection:bg-primary/20 selection:text-foreground dark:selection:bg-primary/30"
                                        />
                                    </FormField>
                                </section>
                            </div>
                        ) : null}
                    </CardContent>

                    {stepComplete ? (
                        <div className="sticky bottom-0 z-10 mt-auto border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                            <div className="w-full px-4 py-3 sm:px-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex min-w-0 items-center justify-between gap-4 sm:justify-start">
                                        <div className="flex min-w-0 flex-col">
                                            <span className="text-xs text-muted-foreground">
                                                Total líquido
                                            </span>
                                            <span
                                                className={cn(
                                                    'text-base font-medium tracking-tight tabular-nums',
                                                    hasNegativeNetAmount
                                                        ? 'text-destructive'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {formatMoneyFromCents(
                                                    displayNetAmount,
                                                )}
                                            </span>
                                        </div>
                                        {!processing && pendingCount > 0 ? (
                                            <Popover
                                                open={pendingPopoverOpen}
                                                onOpenChange={
                                                    setPendingPopoverOpen
                                                }
                                            >
                                                <PopoverTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-md px-2 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-500/10 focus-visible:ring-2 focus-visible:ring-amber-500/35 focus-visible:outline-none dark:text-amber-300"
                                                        aria-label={`${pendingCount} ${pendingCount === 1 ? 'pendência no formulário' : 'pendências no formulário'}. Mostrar detalhes.`}
                                                        aria-expanded={
                                                            pendingPopoverOpen
                                                        }
                                                        aria-controls="financial-entry-pending-issues"
                                                    >
                                                        <CircleAlert
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        {pendingCount}{' '}
                                                        {pendingCount === 1
                                                            ? 'pendência'
                                                            : 'pendências'}
                                                    </button>
                                                </PopoverTrigger>
                                                <PopoverContent
                                                    id="financial-entry-pending-issues"
                                                    align="start"
                                                    side="top"
                                                    sideOffset={10}
                                                    className="w-[min(22rem,calc(100vw-2rem))] p-2"
                                                >
                                                    <div className="px-2 pt-1 pb-2">
                                                        <p className="text-sm font-semibold">
                                                            Revise antes de
                                                            criar
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Selecione uma
                                                            pendência para ir ao
                                                            campo
                                                            correspondente.
                                                        </p>
                                                    </div>
                                                    <ul className="max-h-64 space-y-1 overflow-y-auto">
                                                        {validationIssues.map(
                                                            (issue) => (
                                                                <li
                                                                    key={
                                                                        issue.message
                                                                    }
                                                                >
                                                                    {issue.selector ? (
                                                                        <button
                                                                            type="button"
                                                                            className="flex min-h-10 w-full items-start gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:outline-none"
                                                                            onClick={() =>
                                                                                selectPendingIssue(
                                                                                    issue,
                                                                                )
                                                                            }
                                                                        >
                                                                            <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-500" />
                                                                            <span className="leading-snug">
                                                                                {
                                                                                    issue.message
                                                                                }
                                                                            </span>
                                                                        </button>
                                                                    ) : (
                                                                        <div className="flex min-h-10 items-start gap-2 px-2 py-2 text-sm">
                                                                            <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-500" />
                                                                            <span className="leading-snug">
                                                                                {
                                                                                    issue.message
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </PopoverContent>
                                            </Popover>
                                        ) : null}
                                        <span
                                            role="status"
                                            aria-live="polite"
                                            className="sr-only"
                                        >
                                            {pendingCount > 0
                                                ? `${pendingCount} ${pendingCount === 1 ? 'pendência no formulário' : 'pendências no formulário'}`
                                                : 'Formulário sem pendências'}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            asChild
                                            disabled={processing}
                                            className="flex-1 sm:flex-none"
                                        >
                                            <Link href={index.url()}>
                                                Cancelar
                                            </Link>
                                        </Button>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="flex-1 sm:flex-none"
                                        >
                                            <Save className="size-4" />
                                            {processing
                                                ? 'Salvando...'
                                                : 'Criar lançamento'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}
                </Card>
            </form>

            <AmountAdjustmentsDialog
                key={adjustmentsDialogVersion}
                open={adjustmentsDialogOpen}
                onOpenChange={setAdjustmentsDialogOpen}
                principalAmount={data.amount}
                adjustments={data.components}
                options={amountAdjustmentOptions}
                direction={data.direction}
                onConfirm={confirmComponents}
                disabled={processing}
            />

            <SupplierQuickCreateDialog
                companyId={selectedCompanyId}
                open={supplierQuickCreateOpen}
                onOpenChange={setSupplierQuickCreateOpen}
                onCreated={selectCreatedSupplier}
            />
        </>
    );
}

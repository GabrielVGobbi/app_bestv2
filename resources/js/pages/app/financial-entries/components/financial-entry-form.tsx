import { Link, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    Plus,
    Save,
    Trash2,
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
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { apiRoutes } from '@/lib/api/api-routes';
import { cn } from '@/lib/utils';
import type {
    CounterpartyTypeByDirection,
    FinancialAmountComponentCode,
    FinancialAmountOperation,
    FinancialDirection,
    FinancialEntryOption,
} from '@/types/app/financial-entry';
import type { Supplier } from '@/types/app/supplier';
import { InstallmentsConfig } from './installments-config';
import type { InstallmentItem } from './installments-config';
import { SupplierQuickCreateDialog } from './supplier-quick-create-dialog';

interface FinancialEntryFormProps {
    directionOptions: FinancialEntryOption[];
    componentCodeOptions: FinancialEntryOption[];
    componentOperationOptions: FinancialEntryOption[];
    counterpartyTypeByDirection: CounterpartyTypeByDirection;
}

interface AmountComponentDraft {
    id: string;
    code: FinancialAmountComponentCode;
    label: string;
    operation: FinancialAmountOperation;
    amount: string;
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

type FieldErrors = Record<string, string | undefined>;

const todayString = new Date().toISOString().slice(0, 10);

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

function makeComponent(): AmountComponentDraft {
    return {
        id: uniqueId('component'),
        code: 'other',
        label: 'Ajuste',
        operation: 'add',
        amount: '0.00',
    };
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

function SeparatorWithText({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            <Separator className="my-0 flex-1" />
            <span className="text-sm whitespace-nowrap text-muted-foreground">
                {label}
            </span>
            <Separator className="my-0 flex-1" />
        </div>
    );
}

function AmountHeroInput({
    value,
    onChange,
    disabled,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
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
                type="text"
                inputMode="numeric"
                value={displayValue}
                onKeyDown={handleKeyDown}
                onPaste={handlePaste}
                onFocus={(event) => event.target.select()}
                readOnly
                disabled={disabled}
                placeholder="0,00"
                className="min-w-0 flex-1 rounded-none border-0 border-b border-transparent bg-transparent px-0 py-2 text-3xl font-extralight tracking-tight transition-colors outline-none placeholder:text-muted-foreground/20 hover:border-border/50 focus:border-border focus:ring-0 disabled:pointer-events-none disabled:opacity-50"
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

function uniqueMessages(messages: Array<string | undefined>): string[] {
    return Array.from(
        new Set(
            messages.filter(
                (message): message is string =>
                    typeof message === 'string' && message.trim() !== '',
            ),
        ),
    );
}

function FormValidationBadge({ messages }: { messages: string[] }) {
    const [isOpen, setIsOpen] = useState(false);

    if (messages.length === 0) {
        return null;
    }

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    onMouseEnter={() => setIsOpen(true)}
                    onFocus={() => setIsOpen(true)}
                    aria-label="Ver pendências do formulário"
                    className="inline-flex size-7 shrink-0 items-center justify-center rounded-full border border-amber-400/35 bg-amber-50/80 text-xs font-semibold text-amber-700 shadow-xs transition-colors hover:border-amber-400/60 hover:bg-amber-100 focus-visible:ring-2 focus-visible:ring-amber-400/30 focus-visible:outline-none dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                >
                    !
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                side="top"
                sideOffset={10}
                onOpenAutoFocus={(event) => event.preventDefault()}
                className="w-72 overflow-hidden rounded-xl p-0"
            >
                <ul className="max-h-64 space-y-1 overflow-y-auto p-2">
                    {messages.map((message) => (
                        <li
                            key={message}
                            className="flex gap-2 rounded-lg px-2 py-1.5 text-sm text-foreground"
                        >
                            <span className="mt-2 size-1.5 shrink-0 rounded-full bg-amber-500" />
                            <span className="leading-snug">{message}</span>
                        </li>
                    ))}
                </ul>
            </PopoverContent>
        </Popover>
    );
}

export default function FinancialEntryForm({
    directionOptions,
    componentCodeOptions,
    componentOperationOptions,
    counterpartyTypeByDirection,
}: FinancialEntryFormProps) {
    const [showAdditionalComponents, setShowAdditionalComponents] =
        useState(false);
    const [detailsReady, setDetailsReady] = useState(false);
    const [supplierQuickCreateOpen, setSupplierQuickCreateOpen] =
        useState(false);
    const [createdSupplierOption, setCreatedSupplierOption] =
        useState<AsyncSelectOption | null>(null);

    const form = useForm<FinancialEntryFormData>({
        company_id: '',
        direction: 'payable',
        counterparty_id: '',
        category_id: '',
        description: '',
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
    const validationMessages = useMemo(
        () =>
            uniqueMessages([
                ...Object.values(errors),
                data.description.trim() === ''
                    ? 'Informe a descrição do lançamento.'
                    : undefined,
                parseAmountToCents(data.amount) <= 0
                    ? 'Informe o valor bruto.'
                    : undefined,
                data.company_id === ''
                    ? 'Selecione a empresa do lançamento.'
                    : undefined,
                data.category_id === ''
                    ? 'Selecione uma categoria financeira folha.'
                    : undefined,
                data.counterparty_id === ''
                    ? `Selecione ${meta.counterpartyLabel.toLowerCase()}.`
                    : undefined,
                data.due_date === ''
                    ? 'Informe o vencimento do lançamento.'
                    : undefined,
                hasNegativeNetAmount
                    ? 'Subtrações não podem ser maiores que o valor bruto.'
                    : undefined,
                !hasNegativeNetAmount && netAmount <= 0
                    ? 'O valor líquido precisa ser maior que zero.'
                    : undefined,
                !installmentsAreValid
                    ? 'A soma das parcelas precisa fechar com o líquido calculado.'
                    : undefined,
            ]),
        [
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
        ],
    );
    const shouldShowValidationBadge =
        !processing && !canSubmit && validationMessages.length > 0;
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

    function addComponent(): void {
        setData('components', [...data.components, makeComponent()]);
        setShowAdditionalComponents(true);
    }

    function updateComponent(
        id: string,
        patch: Partial<Omit<AmountComponentDraft, 'id'>>,
    ): void {
        setData(
            'components',
            data.components.map((component) =>
                component.id === id ? { ...component, ...patch } : component,
            ),
        );
    }

    function removeComponent(id: string): void {
        setData(
            'components',
            data.components.filter((component) => component.id !== id),
        );
    }

    function selectCreatedSupplier(supplier: Supplier): void {
        const option = optionFromResource(supplier);

        setCreatedSupplierOption(option);
        setData('counterparty_id', option.value);
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (netAmount <= 0) {
            toast.error(
                hasNegativeNetAmount
                    ? 'O líquido não pode ficar negativo.'
                    : 'Informe um valor líquido maior que zero.',
            );

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
                className="relative flex h-full flex-1 flex-col p-4"
            >
                <Card className="py-2 pt-6">
                    <CardContent className="space-y-7">
                        <FormField
                            label="Tipo de Lançamento"
                            required
                            error={errors.direction}
                        >
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
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() =>
                                                changeDirection(optionDirection)
                                            }
                                            disabled={processing}
                                            className={cn(
                                                'flex cursor-pointer items-center justify-center gap-3 rounded-xl border px-4 py-3 transition-all duration-300 outline-none',
                                                isSelected
                                                    ? isPayable
                                                        ? 'scale-[1.02] border-rose-100/50 bg-rose-400/30 text-rose-900 shadow-[0_4px_20px_-4px_rgba(244,63,94,0.2)] ring-1 ring-rose-100/30 dark:text-rose-600'
                                                        : 'scale-[1.02] border-emerald-500/50 bg-emerald-500/10 text-emerald-800 shadow-[0_4px_20px_-4px_rgba(16,185,129,0.2)] ring-1 ring-emerald-500/30 dark:text-emerald-500'
                                                    : 'border-border/60 bg-transparent text-muted-foreground hover:scale-[1.01] hover:bg-accent/40 hover:text-foreground',
                                            )}
                                        >
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
                                        </button>
                                    );
                                })}
                            </div>
                        </FormField>

                        <div>
                            <input
                                type="text"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                disabled={processing}
                                placeholder="O que você está lançando?"
                                className="w-full rounded-none border-0 border-b border-transparent bg-transparent px-0 py-2 text-2xl font-light tracking-tight transition-colors outline-none placeholder:text-muted-foreground/30 hover:border-border/50 focus:border-border focus:ring-0"
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
                            label="Valor Bruto"
                            required
                            error={
                                errors.components ??
                                errors['components.0.amount']
                            }
                        >
                            <AmountHeroInput
                                value={data.amount}
                                onChange={(value) => setData('amount', value)}
                                disabled={processing}
                            />
                        </FormField>

                        {hasNegativeNetAmount ? (
                            <div className="rounded-xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                                Subtrações não podem ser maiores que o valor
                                bruto. Ajuste os valores adicionais para
                                continuar.
                            </div>
                        ) : null}

                        {shouldRenderDetails ? (
                            <div className="space-y-7">
                                <Separator className="opacity-30" />

                                <div className="grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2">
                                    <FormField
                                        label="Empresa"
                                        required
                                        error={errors.company_id}
                                    >
                                        <AsyncSelect
                                            endpoint={apiRoutes.companies.index}
                                            value={data.company_id || null}
                                            onChange={(value) => {
                                                setCreatedSupplierOption(null);
                                                setData((currentData) => ({
                                                    ...currentData,
                                                    company_id: value ?? '',
                                                    category_id: '',
                                                    counterparty_id: '',
                                                }));
                                            }}
                                            placeholder="Selecione uma Empresa"
                                            clearable
                                            disabled={processing}
                                            transformOption={optionFromResource}
                                        />
                                    </FormField>
                                </div>

                                <FormField
                                    label="Categoria"
                                    required
                                    error={errors.category_id}
                                >
                                    <FinancialCategoryTreeSelect
                                        value={data.category_id || null}
                                        onChange={(value) =>
                                            setData('category_id', value ?? '')
                                        }
                                        type={meta.categoryType}
                                        companyId={
                                            data.company_id
                                                ? Number(data.company_id)
                                                : null
                                        }
                                        mode="entry"
                                        placeholder={
                                            data.direction === 'receivable'
                                                ? 'Selecione uma categoria de entrada'
                                                : 'Selecione uma categoria de despesa'
                                        }
                                        className="text-base"
                                        disabled={processing}
                                    />
                                </FormField>

                                <div className="overflow-hidden rounded-xl border bg-background">
                                    <div className="flex flex-wrap items-center justify-between gap-3 p-4">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <span className="text-sm font-medium">
                                                Valores adicionais
                                            </span>
                                            {data.components.length > 0 ? (
                                                <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                                    {data.components.length}
                                                </span>
                                            ) : null}
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={addComponent}
                                                disabled={processing}
                                                className="h-8 px-2.5"
                                            >
                                                <Plus className="mr-1.5 size-3.5" />
                                                Adicionar
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                onClick={() =>
                                                    setShowAdditionalComponents(
                                                        (current) => !current,
                                                    )
                                                }
                                                className="size-8"
                                                aria-label={
                                                    showAdditionalComponents
                                                        ? 'Ocultar valores adicionais'
                                                        : 'Mostrar valores adicionais'
                                                }
                                            >
                                                <ChevronDown
                                                    className={cn(
                                                        'size-4 transition-transform',
                                                        showAdditionalComponents &&
                                                            'rotate-180',
                                                    )}
                                                />
                                            </Button>
                                        </div>
                                    </div>

                                    {showAdditionalComponents ? (
                                        <div className="space-y-3 border-t bg-muted/20 p-4">
                                            {data.components.length === 0 ? (
                                                <div className="rounded-lg border border-dashed bg-background px-4 py-3 text-sm text-muted-foreground">
                                                    Nenhum valor adicional
                                                    informado.
                                                </div>
                                            ) : null}

                                            {data.components.map(
                                                (component, index) => (
                                                    <div
                                                        key={component.id}
                                                        className="rounded-xl border bg-background p-3 shadow-sm sm:p-4"
                                                    >
                                                        <div className="mb-3 flex items-center justify-between gap-3">
                                                            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                                Componente{' '}
                                                                {index + 1}
                                                            </span>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                                onClick={() =>
                                                                    removeComponent(
                                                                        component.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    processing
                                                                }
                                                                aria-label="Remover componente"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </div>

                                                        <div className="grid gap-3 min-[520px]:grid-cols-[minmax(110px,0.8fr)_minmax(120px,0.8fr)_minmax(150px,1fr)]">
                                                            <FormField label="Tipo">
                                                                <Select
                                                                    value={
                                                                        component.code
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateComponent(
                                                                            component.id,
                                                                            {
                                                                                code: value as FinancialAmountComponentCode,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {componentCodeOptions
                                                                            .filter(
                                                                                (
                                                                                    option,
                                                                                ) =>
                                                                                    option.value !==
                                                                                    'principal',
                                                                            )
                                                                            .map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            option.value
                                                                                        }
                                                                                        value={
                                                                                            option.value
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option.label
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </FormField>

                                                            <FormField label="Operação">
                                                                <Select
                                                                    value={
                                                                        component.operation
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateComponent(
                                                                            component.id,
                                                                            {
                                                                                operation:
                                                                                    value as FinancialAmountOperation,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {componentOperationOptions.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </FormField>

                                                            <FormField
                                                                label="Rótulo"
                                                                className="min-[520px]:col-span-2"
                                                            >
                                                                <Input
                                                                    value={
                                                                        component.label
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateComponent(
                                                                            component.id,
                                                                            {
                                                                                label: event
                                                                                    .target
                                                                                    .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    placeholder="Ex.: desconto comercial, ISS, taxa bancária..."
                                                                />
                                                            </FormField>

                                                            <FormField label="Valor">
                                                                <InputMoney
                                                                    value={
                                                                        component.amount
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateComponent(
                                                                            component.id,
                                                                            {
                                                                                amount: value,
                                                                            },
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                />
                                                            </FormField>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ) : null}
                                </div>

                                <div className="space-y-4 rounded-xl border bg-muted/30 p-4">
                                    <div className="space-y-2">
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <span className="text-sm leading-none font-semibold">
                                                {meta.counterpartyLabel}{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </span>

                                            {data.direction === 'payable' ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full justify-center sm:w-auto"
                                                    disabled={
                                                        processing ||
                                                        !data.company_id
                                                    }
                                                    onClick={() =>
                                                        setSupplierQuickCreateOpen(
                                                            true,
                                                        )
                                                    }
                                                    title={
                                                        data.company_id
                                                            ? 'Criar fornecedor'
                                                            : 'Selecione uma empresa antes'
                                                    }
                                                >
                                                    <Plus className="size-4" />
                                                    Criar fornecedor
                                                </Button>
                                            ) : null}
                                        </div>

                                        <AsyncSelect
                                            key={`${data.direction}-${data.company_id}`}
                                            endpoint={meta.counterpartyEndpoint}
                                            value={data.counterparty_id || null}
                                            options={
                                                createdSupplierOption
                                                    ? [createdSupplierOption]
                                                    : undefined
                                            }
                                            params={counterpartyParams}
                                            onChange={(value) => {
                                                setCreatedSupplierOption(null);
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
                                                processing || !data.company_id
                                            }
                                            transformOption={optionFromResource}
                                        />

                                        {counterpartyError ? (
                                            <p className="text-sm text-destructive">
                                                {counterpartyError}
                                            </p>
                                        ) : null}
                                    </div>

                                    <Separator className="opacity-30" />

                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            {data.direction === 'payable'
                                                ? 'Valor Líquido a Pagar'
                                                : 'Valor Líquido a Receber'}
                                        </span>
                                        <span
                                            className={cn(
                                                'text-xl font-semibold tracking-tight tabular-nums',
                                                hasNegativeNetAmount
                                                    ? 'text-destructive'
                                                    : data.direction ===
                                                        'payable'
                                                      ? 'text-rose-600'
                                                      : 'text-emerald-800',
                                            )}
                                        >
                                            {formatMoneyFromCents(
                                                displayNetAmount,
                                            )}
                                        </span>
                                    </div>
                                    {hasNegativeNetAmount ? (
                                        <p className="mt-2 text-right text-xs text-destructive">
                                            O líquido não pode ficar negativo.
                                        </p>
                                    ) : null}
                                </div>

                                <SeparatorWithText label="DATAS" />

                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                    <FormField
                                        label="Emissão"
                                        error={errors.issue_date}
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
                                        label="Competência (Mês Ref.)"
                                        error={errors.competence_date}
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
                                    >
                                        <DatePicker
                                            value={stringToDate(data.due_date)}
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

                                <FormField label="Observações">
                                    <Textarea
                                        value={data.notes}
                                        onChange={(event) =>
                                            setData('notes', event.target.value)
                                        }
                                        disabled={processing}
                                        rows={2}
                                        placeholder="Informações adicionais..."
                                        className="resize-none"
                                    />
                                </FormField>

                                <SeparatorWithText label="PARCELAMENTO" />

                                <InstallmentsConfig
                                    totalAmount={netValue}
                                    initialDueDate={data.due_date}
                                    confirmedInstallments={data.installments}
                                    onConfirm={(installments) =>
                                        setData('installments', installments)
                                    }
                                    disabled={processing || netAmount <= 0}
                                />
                            </div>
                        ) : null}
                    </CardContent>

                    {stepComplete ? (
                        <div className="sticky bottom-0 mt-auto border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/10">
                            <div className="w-full px-5 pt-5 pb-3">
                                <div className="flex items-center justify-end gap-3">
                                    <div className="hidden flex-col sm:flex">
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
                                        {hasNegativeNetAmount ? (
                                            <span className="text-xs text-destructive">
                                                Líquido não pode ser negativo
                                            </span>
                                        ) : null}
                                        {hasConfirmedInstallments &&
                                        !installmentsAreValid ? (
                                            <span className="text-xs text-destructive">
                                                Parcelas não fecham com o
                                                líquido
                                            </span>
                                        ) : null}
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link href={index.url()}>Cancelar</Link>
                                    </Button>

                                    <Button type="submit" disabled={!canSubmit}>
                                        <Save className="mr-2 size-4" />
                                        {processing ? 'Salvando...' : 'Criar'}
                                    </Button>
                                    {shouldShowValidationBadge ? (
                                        <FormValidationBadge
                                            messages={validationMessages}
                                        />
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    ) : null}
                </Card>
            </form>

            <SupplierQuickCreateDialog
                companyId={selectedCompanyId}
                open={supplierQuickCreateOpen}
                onOpenChange={setSupplierQuickCreateOpen}
                onCreated={selectCreatedSupplier}
            />
        </>
    );
}

import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import Header from '@/components/dashboard/header';
import FinancialCategoryTreeSelect from '@/components/financial/financial-category-tree-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    FinancialAmountComponentCode,
    FinancialAmountOperation,
    FinancialDirection,
    FinancialEntryOption,
} from '@/types/app/financial-entry';

interface Props {
    directionOptions: FinancialEntryOption[];
    componentCodeOptions: FinancialEntryOption[];
    componentOperationOptions: FinancialEntryOption[];
    counterpartyTypeOptions: FinancialEntryOption[];
}

function cents(value: string): number {
    const trimmed = value.trim();

    if (trimmed === '') {
        return 0;
    }

    const normalized = trimmed.includes(',')
        ? trimmed
              .replace(/[^\d,.-]/g, '')
              .replace(/\./g, '')
              .replace(',', '.')
        : trimmed.replace(/[^\d.-]/g, '');
    const parsed = Number.parseFloat(normalized || '0');

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.round(parsed * 100);
}

interface AmountComponentDraft {
    key: string;
    code: FinancialAmountComponentCode;
    label: string;
    operation: FinancialAmountOperation;
    amount: string;
}

function makeComponent(
    code: FinancialAmountComponentCode,
    operation: FinancialAmountOperation,
    label = '',
): AmountComponentDraft {
    return {
        key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        code,
        label,
        operation,
        amount: '0.00',
    };
}

export default function CreateFinancialEntry({
    directionOptions,
    componentCodeOptions,
    componentOperationOptions,
    counterpartyTypeOptions,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Lançamentos financeiros', href: index.url() },
            { title: 'Novo lancamento', href: create.url() },
        ],
    });

    const [processing, setProcessing] = useState(false);
    const [direction, setDirection] = useState<FinancialDirection>('receivable');
    const [companyId, setCompanyId] = useState('');
    const [counterpartyType, setCounterpartyType] = useState('');
    const [counterpartyId, setCounterpartyId] = useState('');
    const [categoryId, setCategoryId] = useState<string | null>(null);
    const [description, setDescription] = useState('');
    const [originReference, setOriginReference] = useState('');
    const [dueDate, setDueDate] = useState<Date | undefined>();
    const [components, setComponents] = useState<AmountComponentDraft[]>([
        makeComponent('principal', 'add', 'Principal'),
    ]);

    const categoryType = direction === 'receivable' ? 'income' : 'expense';
    const totals = useMemo(() => {
        return components.reduce(
            (carry, component) => {
                const amount = cents(component.amount);

                if (component.operation === 'add') {
                    return {
                        ...carry,
                        grossAmount: carry.grossAmount + amount,
                        netAmount: carry.netAmount + amount,
                    };
                }

                return {
                    ...carry,
                    subtractAmount: carry.subtractAmount + amount,
                    netAmount: carry.netAmount - amount,
                };
            },
            { grossAmount: 0, subtractAmount: 0, netAmount: 0 },
        );
    }, [components]);

    function updateComponent(
        key: string,
        patch: Partial<Omit<AmountComponentDraft, 'key'>>,
    ): void {
        setComponents((current) =>
            current.map((component) =>
                component.key === key ? { ...component, ...patch } : component,
            ),
        );
    }

    function addComponent(): void {
        setComponents((current) => [
            ...current,
            makeComponent('other', 'add', 'Outro componente'),
        ]);
    }

    function removeComponent(key: string): void {
        setComponents((current) =>
            current.length === 1
                ? current
                : current.filter((component) => component.key !== key),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        setProcessing(true);

        const submittedComponents = components
            .map((component) => ({
                code: component.code,
                label: component.label || null,
                operation: component.operation,
                amount: cents(component.amount),
            }))
            .filter((component) => component.amount > 0)
            .map((component, position) => ({ ...component, position }));

        router.post(
            store.url(),
            {
                company_id: Number(companyId),
                direction,
                counterparty_type: counterpartyType || null,
                counterparty_id: counterpartyId ? Number(counterpartyId) : null,
                origin_reference: originReference || null,
                category_id: categoryId ? Number(categoryId) : null,
                description,
                due_date: dueDate?.toISOString().slice(0, 10) ?? null,
                currency: 'BRL',
                components: submittedComponents,
            },
            {
                preserveScroll: true,
                onError: () => toast.error('Verifique os campos obrigatorios.'),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title="Novo lancamento financeiro" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Novo lancamento financeiro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Entrada manual independente de vendas
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-md">
                <form onSubmit={submit} className="space-y-4 p-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados do lancamento</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="company_id">Empresa ID *</Label>
                                <Input
                                    id="company_id"
                                    inputMode="numeric"
                                    value={companyId}
                                    onChange={(event) => setCompanyId(event.target.value)}
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Direcao *</Label>
                                <Select
                                    value={direction}
                                    onValueChange={(value) => {
                                        setDirection(value as FinancialDirection);
                                        setCategoryId(null);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {directionOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="description">Descricao *</Label>
                                <Textarea
                                    id="description"
                                    value={description}
                                    onChange={(event) => setDescription(event.target.value)}
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Categoria</Label>
                                <FinancialCategoryTreeSelect
                                    value={categoryId}
                                    onChange={setCategoryId}
                                    type={categoryType}
                                    companyId={companyId ? Number(companyId) : null}
                                    mode="entry"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Vencimento</Label>
                                <DatePicker value={dueDate} onChange={setDueDate} />
                            </div>

                            <div className="space-y-2">
                                <Label>Tipo da contraparte</Label>
                                <Select
                                    value={counterpartyType || 'none'}
                                    onValueChange={(value) =>
                                        setCounterpartyType(value === 'none' ? '' : value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sem contraparte" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sem contraparte</SelectItem>
                                        {counterpartyTypeOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="counterparty_id">ID da contraparte</Label>
                                <Input
                                    id="counterparty_id"
                                    inputMode="numeric"
                                    value={counterpartyId}
                                    onChange={(event) =>
                                        setCounterpartyId(event.target.value)
                                    }
                                    disabled={!counterpartyType}
                                />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="origin_reference">Referencia manual</Label>
                                <Input
                                    id="origin_reference"
                                    value={originReference}
                                    onChange={(event) =>
                                        setOriginReference(event.target.value)
                                    }
                                    placeholder="Pedido, documento interno ou observacao curta"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle>Componentes de valor</CardTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addComponent}
                                >
                                    <Plus className="size-4" />
                                    Adicionar
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                {components.map((component, index) => (
                                    <div
                                        key={component.key}
                                        className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1.1fr_1fr_1fr_1fr_auto]"
                                    >
                                        <div className="space-y-2">
                                            <Label>Tipo</Label>
                                            <Select
                                                value={component.code}
                                                onValueChange={(value) =>
                                                    updateComponent(component.key, {
                                                        code: value as FinancialAmountComponentCode,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {componentCodeOptions.map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Operação</Label>
                                            <Select
                                                value={component.operation}
                                                onValueChange={(value) =>
                                                    updateComponent(component.key, {
                                                        operation:
                                                            value as FinancialAmountOperation,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {componentOperationOptions.map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Rótulo</Label>
                                            <Input
                                                value={component.label}
                                                onChange={(event) =>
                                                    updateComponent(component.key, {
                                                        label: event.target.value,
                                                    })
                                                }
                                                placeholder={`Componente ${index + 1}`}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Valor *</Label>
                                            <InputMoney
                                                value={component.amount}
                                                onChange={(value) =>
                                                    updateComponent(component.key, {
                                                        amount: value,
                                                    })
                                                }
                                            />
                                        </div>

                                        <div className="flex items-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={components.length === 1}
                                                onClick={() => removeComponent(component.key)}
                                                title="Remover componente"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="grid gap-3 rounded-md bg-muted p-3 sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-muted-foreground">Bruto</p>
                                    <p className="text-sm font-semibold">
                                        {new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                        }).format(totals.grossAmount / 100)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Subtrações
                                    </p>
                                    <p className="text-sm font-semibold">
                                        {new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                        }).format(totals.subtractAmount / 100)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Líquido calculado
                                    </p>
                                    <p className="text-sm font-semibold">
                                        {new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                        }).format(totals.netAmount / 100)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                        <div className="flex items-center justify-between gap-3 py-3">
                            <p className="text-xs text-muted-foreground">
                                O liquido e sempre calculado pelos componentes de valor.
                            </p>
                            <div className="flex items-center gap-2">
                                <Button type="button" variant="ghost" size="sm" asChild>
                                    <Link href={index.url()}>Cancelar</Link>
                                </Button>
                                <Button type="submit" size="sm" disabled={processing}>
                                    <Save />
                                    {processing ? 'Salvando...' : 'Salvar'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

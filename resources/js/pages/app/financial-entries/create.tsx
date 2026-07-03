import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
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
    const normalized = value.replace(/\./g, '').replace(',', '.');
    const parsed = Number.parseFloat(normalized || '0');

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.round(parsed * 100);
}

export default function CreateFinancialEntry({
    directionOptions,
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
    const [principal, setPrincipal] = useState('0.00');
    const [discount, setDiscount] = useState('0.00');

    const categoryType = direction === 'receivable' ? 'income' : 'expense';
    const netAmount = useMemo(
        () => Math.max(cents(principal) - cents(discount), 0),
        [discount, principal],
    );

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        setProcessing(true);

        const components = [
            {
                code: 'principal',
                label: direction === 'receivable' ? 'Receita principal' : 'Despesa principal',
                operation: 'add',
                amount: cents(principal),
                position: 0,
            },
        ];

        if (cents(discount) > 0) {
            components.push({
                code: 'discount',
                label: 'Desconto',
                operation: 'subtract',
                amount: cents(discount),
                position: 1,
            });
        }

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
                components,
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
                            <CardTitle>Valores</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-2">
                                <Label>Valor principal *</Label>
                                <InputMoney value={principal} onChange={setPrincipal} />
                            </div>
                            <div className="space-y-2">
                                <Label>Desconto</Label>
                                <InputMoney value={discount} onChange={setDiscount} />
                            </div>
                            <div className="space-y-2">
                                <Label>Liquido calculado</Label>
                                <div className="flex h-10 items-center rounded-md border px-3 text-sm font-medium">
                                    {new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    }).format(netAmount / 100)}
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

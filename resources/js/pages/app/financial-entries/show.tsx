import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CircleDollarSign } from 'lucide-react';

import {
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import Header from '@/components/dashboard/header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FinancialEntryResource } from '@/types/app/financial-entry';

interface Props {
    entryData: FinancialEntryResource;
}

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

function Info({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="text-sm">{value || '-'}</p>
        </div>
    );
}

export default function ShowFinancialEntry({ entryData }: Props) {
    const entry = entryData.data;
    const components = entry.amount_components?.data ?? [];
    const installments = entry.installments?.data ?? [];

    setLayoutProps({
        breadcrumbs: [
            { title: 'Lançamentos financeiros', href: index.url() },
            { title: entry.description, href: show.url(entry.uuid) },
        ],
    });

    return (
        <>
            <Head title={entry.description} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{entry.description}</h1>
                        <p className="text-sm text-muted-foreground">
                            {entry.direction_label} - {entry.origin_label}
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[1fr_22rem]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between gap-3">
                                <CardTitle>Resumo</CardTitle>
                                <StatusBadge presenter={entry.status_presenter} />
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-3">
                                <Info label="Direcao" value={entry.direction_label} />
                                <Info
                                    label="Contraparte"
                                    value={entry.counterparty_label ?? 'Sem contraparte'}
                                />
                                <Info label="Origem" value={entry.origin_display_name} />
                                <Info label="Vencimento" value={entry.due_date} />
                                <Info
                                    label="Categoria"
                                    value={entry.category?.data?.full_path ?? null}
                                />
                                <Info label="Empresa" value={`#${entry.company_id}`} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Componentes de valor</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {components.map((component) => (
                                    <div
                                        key={component.uuid}
                                        className="flex items-center justify-between rounded-md border p-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {component.label ?? component.code_label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {component.operation_label}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold">
                                            {money(component.amount, entry.currency)}
                                        </span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Parcelas</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {installments.map((installment) => (
                                    <div
                                        key={installment.uuid}
                                        className="grid gap-3 rounded-md border p-3 sm:grid-cols-2 xl:grid-cols-6"
                                    >
                                        <Info
                                            label="Parcela"
                                            value={String(installment.installment_number)}
                                        />
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                Status
                                            </p>
                                            <StatusBadge presenter={installment.status_presenter} />
                                        </div>
                                        <Info
                                            label="Vencimento"
                                            value={installment.due_date}
                                        />
                                        <Info
                                            label="Competencia"
                                            value={installment.competence_date}
                                        />
                                        <Info
                                            label="Original"
                                            value={money(
                                                installment.original_amount,
                                                installment.currency,
                                            )}
                                        />
                                        <Info
                                            label="Saldo"
                                            value={money(
                                                installment.balance_amount,
                                                installment.currency,
                                            )}
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Valores</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Bruto
                                    </span>
                                    <span className="font-medium">
                                        {money(entry.gross_amount, entry.currency)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Liquido
                                    </span>
                                    <span className="font-medium">
                                        {money(entry.net_amount, entry.currency)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Liquidado
                                    </span>
                                    <span className="font-medium">
                                        {money(entry.settled_amount, entry.currency)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 rounded-md bg-muted px-3 py-2">
                                    <span className="text-sm font-medium">Saldo</span>
                                    <span className="font-semibold">
                                        {money(entry.balance_amount, entry.currency)}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Contexto</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-start gap-3">
                                    <CalendarDays className="mt-0.5 size-4 text-muted-foreground" />
                                    <span>
                                        Competencia: {entry.competence_date ?? '-'}
                                    </span>
                                </div>
                                <div className="flex items-start gap-3">
                                    <CircleDollarSign className="mt-0.5 size-4 text-muted-foreground" />
                                    <Badge variant="outline">{entry.currency}</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

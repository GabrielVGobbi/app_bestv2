import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    Landmark,
    Pencil,
    Power,
    PowerOff,
    Trash2,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';

import {
    destroy,
    edit,
    index,
    show,
    updateStatus,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialAccountController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FinancialAccountResource } from '@/types/app/financial-account';

import { FinancialAccountDeleteDialog } from './partials/financial-account-delete-dialog';

interface Props {
    financialAccountData: FinancialAccountResource;
}

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

export default function ShowFinancialAccount({ financialAccountData }: Props) {
    const financialAccount = financialAccountData.data;
    const [deleteOpen, setDeleteOpen] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Contas financeiras', href: index.url() },
            {
                title: financialAccount.name,
                href: show.url(financialAccount.uuid),
            },
        ],
    });

    function toggleStatus(): void {
        router.patch(
            updateStatus.url(financialAccount.uuid),
            { active: !financialAccount.active },
            { preserveScroll: true },
        );
    }

    function deleteFinancialAccount(): void {
        router.delete(destroy.url(financialAccount.uuid), {
            preserveScroll: true,
            onSuccess: () => setDeleteOpen(false),
        });
    }

    return (
        <>
            <Head title={financialAccount.name} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            {financialAccount.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {financialAccount.type_label}
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
                                <Badge
                                    variant={
                                        financialAccount.active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {financialAccount.active ? 'Ativa' : 'Inativa'}
                                </Badge>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="Saldo atual"
                                    value={money(
                                        financialAccount.current_balance_amount,
                                        financialAccount.currency,
                                    )}
                                />
                                <Info
                                    label="Saldo inicial"
                                    value={money(
                                        financialAccount.opening_balance_amount,
                                        financialAccount.currency,
                                    )}
                                />
                                <Info label="Tipo" value={financialAccount.type_label} />
                                <Info
                                    label="Empresa"
                                    value={`Empresa #${financialAccount.company_id}`}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Identificação bancária</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="Instituição"
                                    value={
                                        financialAccount.institution_name ??
                                        'Conta não informada'
                                    }
                                />
                                <Info
                                    label="Agência"
                                    value={
                                        financialAccount.agency_number ??
                                        'Conta não informada'
                                    }
                                />
                                <Info
                                    label="Conta"
                                    value={
                                        financialAccount.account_number
                                            ? `${financialAccount.account_number}${financialAccount.account_digit ? `-${financialAccount.account_digit}` : ''}`
                                            : 'Conta não informada'
                                    }
                                />
                                <Info
                                    label="Documento"
                                    value={
                                        financialAccount.document_number ??
                                        'Conta não informada'
                                    }
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Operação</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <div className="flex items-start gap-3 rounded-md border p-4">
                                    <WalletCards className="mt-0.5 size-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Saldo negativo
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {financialAccount.allows_negative_balance
                                                ? 'Permitido para novas saídas.'
                                                : 'Bloqueado quando a saída excede o saldo.'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3 rounded-md border p-4">
                                    <Landmark className="mt-0.5 size-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Conciliação
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Extratos e movimentos só conciliam dentro
                                            desta mesma conta.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Ações</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button className="w-full justify-start" asChild>
                                    <Link href={edit.url(financialAccount.uuid)}>
                                        <Pencil className="size-4" />
                                        Editar
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full justify-start"
                                    onClick={toggleStatus}
                                >
                                    {financialAccount.active ? (
                                        <PowerOff className="size-4" />
                                    ) : (
                                        <Power className="size-4" />
                                    )}
                                    {financialAccount.active ? 'Inativar' : 'Ativar'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    className="w-full justify-start"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    <Trash2 className="size-4" />
                                    Excluir
                                </Button>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>

            <FinancialAccountDeleteDialog
                financialAccount={deleteOpen ? financialAccount : null}
                onCancel={() => setDeleteOpen(false)}
                onConfirm={deleteFinancialAccount}
            />
        </>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="text-sm">{value}</p>
        </div>
    );
}

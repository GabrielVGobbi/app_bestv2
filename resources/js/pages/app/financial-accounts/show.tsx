import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
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

            <Header
                title={financialAccount.name}
                subtitle={financialAccount.type_label}
                backHref={index.url()}
            >
                <Button asChild>
                    <Link href={edit.url(financialAccount.uuid)}>
                        <Pencil className="size-4" aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
                <Button type="button" variant="outline" onClick={toggleStatus}>
                    {financialAccount.active ? (
                        <PowerOff className="size-4" aria-hidden="true" />
                    ) : (
                        <Power className="size-4" aria-hidden="true" />
                    )}
                    {financialAccount.active ? 'Inativar' : 'Ativar'}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    onClick={() => setDeleteOpen(true)}
                >
                    <Trash2 className="size-4" aria-hidden="true" />
                    Excluir
                </Button>
            </Header>

            <div className="page-wrapper-lg">
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
                            <Info
                                label="Tipo"
                                value={financialAccount.type_label}
                            />
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
                            <div className="flex items-start gap-3 rounded-lg border border-border bg-muted p-4">
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
                            <div className="flex items-start gap-3 rounded-lg border border-border bg-muted p-4">
                                <Landmark className="mt-0.5 size-4 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">
                                        Conciliação
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Extratos e movimentos só conciliam
                                        dentro desta mesma conta.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
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
            <p className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm text-foreground tabular-nums">{value}</p>
        </div>
    );
}

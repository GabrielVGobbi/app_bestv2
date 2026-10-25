import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    CreditCard,
    FileText,
    Pencil,
    Power,
    PowerOff,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

import {
    destroy,
    edit,
    index,
    show,
    updateStatus,
} from '@/actions/App/Modules/Finance/Http/Controllers/PaymentMethodController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaymentMethodResource } from '@/types/app/payment-method';

import { PaymentMethodDeleteDialog } from './partials/payment-method-delete-dialog';

interface Props {
    paymentMethodData: PaymentMethodResource;
}

export default function ShowPaymentMethod({ paymentMethodData }: Props) {
    const paymentMethod = paymentMethodData.data;
    const [deleteOpen, setDeleteOpen] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Métodos de pagamento', href: index.url() },
            { title: paymentMethod.name, href: show.url(paymentMethod.uuid) },
        ],
    });

    function toggleStatus(): void {
        router.patch(
            updateStatus.url(paymentMethod.uuid),
            { is_active: !paymentMethod.is_active },
            { preserveScroll: true },
        );
    }

    function deletePaymentMethod(): void {
        router.delete(destroy.url(paymentMethod.uuid), {
            preserveScroll: true,
            onSuccess: () => setDeleteOpen(false),
        });
    }

    return (
        <>
            <Head title={paymentMethod.name} />

            <Header
                title={paymentMethod.name}
                subtitle={paymentMethod.type_label}
                backHref={index.url()}
            >
                <Button asChild>
                    <Link href={edit.url(paymentMethod.uuid)}>
                        <Pencil className="size-4" aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
                <Button type="button" variant="outline" onClick={toggleStatus}>
                    {paymentMethod.is_active ? (
                        <PowerOff className="size-4" aria-hidden="true" />
                    ) : (
                        <Power className="size-4" aria-hidden="true" />
                    )}
                    {paymentMethod.is_active ? 'Inativar' : 'Ativar'}
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
                                    paymentMethod.is_active
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {paymentMethod.is_active ? 'Ativo' : 'Inativo'}
                            </Badge>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Info
                                label="Tipo"
                                value={paymentMethod.type_label}
                            />
                            <Info label="Código" value={paymentMethod.code} />
                            <Info
                                label="Escopo"
                                value={
                                    paymentMethod.company_id
                                        ? `Empresa #${paymentMethod.company_id}`
                                        : 'Global'
                                }
                            />
                            <Info
                                label="Ordenação"
                                value={String(paymentMethod.sort_order)}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Uso futuro em baixas</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <div className="flex items-start gap-3 rounded-lg border border-border bg-muted p-4">
                                <FileText className="mt-0.5 size-4 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">
                                        Referência
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {paymentMethod.requires_reference
                                            ? 'Obrigatória na baixa futura.'
                                            : 'Opcional na baixa futura.'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3 rounded-lg border border-border bg-muted p-4">
                                <CreditCard className="mt-0.5 size-4 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">
                                        Histórico
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Inativar deve preservar baixas já
                                        registradas quando o v2 entrar.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <PaymentMethodDeleteDialog
                paymentMethod={deleteOpen ? paymentMethod : null}
                onCancel={() => setDeleteOpen(false)}
                onConfirm={deletePaymentMethod}
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

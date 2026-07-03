import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
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
            { title: 'Metodos de pagamento', href: index.url() },
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

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            {paymentMethod.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {paymentMethod.type_label} - {paymentMethod.code}
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
                                        paymentMethod.is_active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {paymentMethod.is_active ? 'Ativo' : 'Inativo'}
                                </Badge>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Info label="Tipo" value={paymentMethod.type_label} />
                                <Info label="Codigo" value={paymentMethod.code} />
                                <Info
                                    label="Escopo"
                                    value={
                                        paymentMethod.company_id
                                            ? `Empresa #${paymentMethod.company_id}`
                                            : 'Global'
                                    }
                                />
                                <Info
                                    label="Ordenacao"
                                    value={String(paymentMethod.sort_order)}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Uso futuro em baixas</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <div className="flex items-start gap-3 rounded-md border p-4">
                                    <FileText className="mt-0.5 size-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Referencia
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {paymentMethod.requires_reference
                                                ? 'Obrigatoria na baixa futura.'
                                                : 'Opcional na baixa futura.'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3 rounded-md border p-4">
                                    <CreditCard className="mt-0.5 size-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Historico
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Inativar deve preservar baixas ja
                                            registradas quando o v2 entrar.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Acoes</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button className="w-full justify-start" asChild>
                                    <Link href={edit.url(paymentMethod.uuid)}>
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
                                    {paymentMethod.is_active ? (
                                        <PowerOff className="size-4" />
                                    ) : (
                                        <Power className="size-4" />
                                    )}
                                    {paymentMethod.is_active ? 'Inativar' : 'Ativar'}
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
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="text-sm">{value}</p>
        </div>
    );
}

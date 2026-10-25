import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Mail,
    Phone,
    Save,
    UserRound,
} from 'lucide-react';
import { toast } from 'sonner';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn, formatDate } from '@/lib/utils';
import type { Supplier, SupplierResource } from '@/types/app/supplier';

import SupplierFormFields from './partials/supplier-form-fields';

interface Props {
    supplierData: SupplierResource;
}

const statusLabels: Record<Supplier['status'], string> = {
    active: 'Ativo',
    inactive: 'Inativo',
    archived: 'Arquivado',
};

function cleanValue(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '-';
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function SummaryItem({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof UserRound;
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground">
            <Icon className="size-4 shrink-0" />
            <span className="sr-only">{label}</span>
            <span className="truncate">{cleanValue(value)}</span>
        </div>
    );
}

export default function EditSupplier({ supplierData }: Props) {
    const supplier = supplierData.data;
    const supplierName = String(supplier.name ?? supplier.uuid);
    const initials = getInitials(supplierName);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Fornecedores', href: index.url() },
            { title: supplierName, href: show.url(supplier.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title="Editar fornecedor" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={show.url(supplier.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">{supplierName}</h1>
                        <p className="text-sm text-muted-foreground">
                            {supplier.uuid}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <DeleteButton
                        url={destroy.url(supplier.uuid)}
                        resourceName={supplierName}
                        resourceType="fornecedor"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-lg border bg-card">
                            <div className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-secondary text-3xl font-semibold text-primary">
                                    {initials}
                                </div>

                                <div className="mt-4 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <h2 className="truncate text-base font-semibold">
                                            {supplierName}
                                        </h2>
                                        <Badge
                                            variant={
                                                supplier.status === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className="h-5 rounded-sm px-1.5 text-[10px]"
                                        >
                                            {statusLabels[supplier.status]}
                                        </Badge>
                                    </div>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {cleanValue(
                                            supplier.company_name ??
                                                supplier.email,
                                        )}
                                    </p>
                                </div>

                                <div className="mt-5 space-y-1">
                                    <SummaryItem
                                        icon={UserRound}
                                        label="Fornecedor"
                                        value={supplierName}
                                    />
                                    <SummaryItem
                                        icon={FileText}
                                        label="Documento"
                                        value={supplier.document}
                                    />
                                    <SummaryItem
                                        icon={Mail}
                                        label="Email"
                                        value={supplier.email}
                                    />
                                    <SummaryItem
                                        icon={Phone}
                                        label="Telefone"
                                        value={supplier.phone}
                                    />
                                </div>
                            </div>

                            <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                                <p>
                                    Criado - {formatDate(supplier.created_at)}
                                </p>
                                <p className="mt-1">
                                    Última edição -{' '}
                                    {formatDate(supplier.updated_at)}
                                </p>
                            </div>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        <Form
                            action={update.url(supplier.uuid)}
                            method="put"
                            options={{ preserveScroll: true }}
                            onError={() =>
                                toast.error('Verifique os campos obrigatórios.')
                            }
                            className="flex h-full flex-col"
                        >
                            {({ errors, processing }) => (
                                <div className="flex flex-1 flex-col gap-4">
                                    <Card variant="form">
                                        <CardContent>
                                            <SupplierFormFields
                                                supplier={supplier}
                                                errors={errors}
                                            />
                                        </CardContent>
                                    </Card>

                                    <div
                                        className={cn(
                                            'sticky bottom-0 -mx-4 mt-auto border-t bg-background/95 px-4 backdrop-blur',
                                            'supports-backdrop-filter:bg-background/80',
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-3 py-3">
                                            <p className="text-xs text-muted-foreground">
                                                Campos marcados com{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>{' '}
                                                são obrigatórios.
                                            </p>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                    disabled={processing}
                                                >
                                                    <Link
                                                        href={show.url(
                                                            supplier.uuid,
                                                        )}
                                                    >
                                                        Cancelar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    <Save />
                                                    {processing
                                                        ? 'Salvando...'
                                                        : 'Salvar'}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </main>
                </div>
            </div>
        </>
    );
}

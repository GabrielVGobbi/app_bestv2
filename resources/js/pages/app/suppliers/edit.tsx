import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { FileText, Mail, Phone, Save, UserRound, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import {
    SidebarContactRow,
    isBlank,
} from '@/pages/app/clients/partials/client-show-helpers';
import type { SupplierResource } from '@/types/app/supplier';

import { SupplierStatusBadge } from './components/supplier-status-menu';
import SupplierFormFields from './partials/supplier-form-fields';

interface Props {
    supplierData: SupplierResource;
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

export default function EditSupplier({ supplierData }: Props) {
    const supplier = supplierData.data;
    const supplierName = String(supplier.name ?? supplier.uuid);
    const initials = getInitials(supplierName);
    const summary =
        [supplier.company_name, supplier.email].find(
            (value) => !isBlank(value),
        ) ?? '-';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Fornecedores', href: index.url() },
            { title: supplierName, href: show.url(supplier.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Editar fornecedor" />

            <Header title={supplierName} backHref={show.url(supplier.uuid)}>
                <DeleteButton
                    url={destroy.url(supplier.uuid)}
                    resourceName={supplierName}
                    resourceType="fornecedor"
                    redirectTo={index.url()}
                />
            </Header>

            <Form
                action={update.url(supplier.uuid)}
                method="put"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-lg flex-1 pb-8">
                            <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                                <aside className="lg:sticky lg:top-4 lg:self-start">
                                    <Card className="gap-0 py-0">
                                        <CardContent className="p-5">
                                            <div className="flex h-24 items-center justify-center rounded-lg bg-primary-soft text-3xl font-semibold text-primary-soft-foreground">
                                                {initials}
                                            </div>

                                            <div className="mt-4 space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h2 className="truncate text-base font-semibold">
                                                        {supplierName}
                                                    </h2>
                                                    <SupplierStatusBadge
                                                        status={supplier.status}
                                                    />
                                                </div>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {summary}
                                                </p>
                                            </div>

                                            <div className="mt-5 space-y-0.5">
                                                <SidebarContactRow
                                                    icon={UserRound}
                                                    label="Fornecedor"
                                                    value={supplierName}
                                                />
                                                <SidebarContactRow
                                                    icon={FileText}
                                                    label="Documento"
                                                    value={formatCpfCnpj(
                                                        supplier.document,
                                                    )}
                                                />
                                                <SidebarContactRow
                                                    icon={Mail}
                                                    label="E-mail"
                                                    value={supplier.email}
                                                />
                                                <SidebarContactRow
                                                    icon={Phone}
                                                    label="Telefone"
                                                    value={formatPhone(
                                                        supplier.phone,
                                                    )}
                                                />
                                            </div>
                                        </CardContent>

                                        <div className="border-t border-border px-5 py-4 text-xs text-muted-foreground tabular-nums">
                                            <p>
                                                Criado em{' '}
                                                {formatDate(
                                                    supplier.created_at,
                                                )}
                                            </p>
                                            <p className="mt-1">
                                                Última edição em{' '}
                                                {formatDate(
                                                    supplier.updated_at,
                                                )}
                                            </p>
                                        </div>
                                    </Card>
                                </aside>

                                <main className="min-w-0">
                                    <Card>
                                        <CardContent>
                                            <SupplierFormFields
                                                supplier={supplier}
                                                errors={errors}
                                            />
                                        </CardContent>
                                    </Card>
                                </main>
                            </div>
                        </div>

                        {/* Encaixado na base da área de conteúdo: visível com formulário curto ou longo. */}
                        <div className="sticky bottom-0 z-10 border-t border-border bg-card/95">
                            <div className="page-wrapper-lg mb-0 flex items-center justify-between gap-3 px-4 py-3">
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    Campos marcados com{' '}
                                    <span className="text-destructive">*</span>{' '}
                                    são obrigatórios
                                </p>
                                <div className="ml-auto flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link href={show.url(supplier.uuid)}>
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cancelar
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save aria-hidden="true" />
                                        {processing ? 'Salvando...' : 'Salvar'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

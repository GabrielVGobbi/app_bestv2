import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    CircleDollarSign,
    FolderTree,
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
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialCategoryController';
import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FinancialCategoryResource } from '@/types/app/financial-category';

import { FinancialCategoryDeleteDialog } from './partials/financial-category-delete-dialog';

interface Props {
    categoryData: FinancialCategoryResource;
}

const typeLabels: Record<string, string> = {
    income: 'Receita',
    expense: 'Despesa',
    transfer: 'Transferência',
};

export default function ShowFinancialCategory({ categoryData }: Props) {
    const category = categoryData.data;
    const [deleteOpen, setDeleteOpen] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Categorias financeiras', href: index.url() },
            { title: category.name, href: show.url(category.uuid) },
        ],
    });

    function toggleStatus(): void {
        router.patch(
            updateStatus.url(category.uuid),
            { is_active: !category.is_active },
            { preserveScroll: true },
        );
    }

    function deleteCategory(): void {
        router.delete(destroy.url(category.uuid), {
            preserveScroll: true,
            onSuccess: () => setDeleteOpen(false),
        });
    }

    return (
        <>
            <Head title={category.name} />

            <Header
                title={category.name}
                subtitle={category.full_path}
                backHref={index.url()}
            >
                <Button asChild>
                    <Link href={edit.url(category.uuid)}>
                        <Pencil className="size-4" aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
                <Button type="button" variant="outline" onClick={toggleStatus}>
                    {category.is_active ? (
                        <PowerOff className="size-4" aria-hidden="true" />
                    ) : (
                        <Power className="size-4" aria-hidden="true" />
                    )}
                    {category.is_active ? 'Inativar' : 'Ativar'}
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
                <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between gap-3">
                                <CardTitle>Resumo</CardTitle>
                                <Badge
                                    variant={
                                        category.is_active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {category.is_active ? 'Ativa' : 'Inativa'}
                                </Badge>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="Tipo"
                                    value={typeLabels[category.type]}
                                />
                                <Info
                                    label="Código"
                                    value={category.code ?? '-'}
                                />
                                <Info
                                    label="Profundidade"
                                    value={String(category.depth)}
                                />
                                <Info
                                    label="Empresa"
                                    value={
                                        category.company_id
                                            ? `#${category.company_id}`
                                            : 'Global'
                                    }
                                />
                                <div className="sm:col-span-2">
                                    <Info
                                        label="Descrição"
                                        value={category.description ?? '-'}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Subcategorias</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {category.children &&
                                category.children.length > 0 ? (
                                    <div className="space-y-2">
                                        {category.children.map((child) => (
                                            <Link
                                                key={child.uuid}
                                                href={show.url(child.uuid)}
                                                className="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                                            >
                                                <span
                                                    className="size-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            child.color ??
                                                            '#64748b',
                                                    }}
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">
                                                        {child.name}
                                                    </span>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {child.full_path}
                                                    </span>
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={FolderTree}
                                        title="Nenhuma subcategoria cadastrada"
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Estrutura</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center gap-2">
                                    <FolderTree className="size-4 text-muted-foreground" />
                                    <span>{category.full_path}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <CircleDollarSign className="size-4 text-muted-foreground" />
                                    <span>
                                        {category.has_children
                                            ? 'Categoria agrupadora'
                                            : 'Categoria folha'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>

            <FinancialCategoryDeleteDialog
                category={deleteOpen ? category : null}
                onCancel={() => setDeleteOpen(false)}
                onConfirm={deleteCategory}
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
            <p className="text-sm text-foreground">{value}</p>
        </div>
    );
}

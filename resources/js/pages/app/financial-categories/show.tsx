import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
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

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">{category.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {category.full_path}
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-8 lg:grid-cols-[1fr_22rem]">
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
                                                className="flex items-center gap-3 rounded-md border p-3 transition-colors hover:bg-accent"
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
                                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Nenhuma subcategoria cadastrada.
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Ações</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button
                                    className="w-full justify-start"
                                    asChild
                                >
                                    <Link href={edit.url(category.uuid)}>
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
                                    {category.is_active ? (
                                        <PowerOff className="size-4" />
                                    ) : (
                                        <Power className="size-4" />
                                    )}
                                    {category.is_active ? 'Inativar' : 'Ativar'}
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
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm">{value}</p>
        </div>
    );
}

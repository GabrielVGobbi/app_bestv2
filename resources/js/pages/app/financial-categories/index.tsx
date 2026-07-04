import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    ChevronDown,
    ChevronRight,
    Eye,
    Filter,
    FolderTree,
    Layers3,
    Leaf,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
} from 'lucide-react';
import {
    useCallback,
    useDeferredValue,
    useMemo,
    useState,
} from 'react';

import {
    create,
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialCategoryController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpGet } from '@/lib/http';
import { cn } from '@/lib/utils';
import type {
    FinancialCategory,
    FinancialCategoryCollectionResource,
    FinancialCategoryType,
    FinancialCategoryTypeOption,
} from '@/types/app/financial-category';

import { FinancialCategoryDeleteDialog } from './partials/financial-category-delete-dialog';

interface Props {
    typeOptions: FinancialCategoryTypeOption[];
}

type TypeFilter = FinancialCategoryType | 'all';
type StatusFilter = 'active' | 'inactive' | 'all';

interface TreeStats {
    total: number;
    roots: number;
    leaves: number;
    maxDepth: number;
}

interface CategoryTreeNodeProps {
    category: FinancialCategory;
    depth?: number;
    expandedIds: Set<number>;
    typeLabels: Record<FinancialCategoryType, string>;
    onToggle: (categoryId: number) => void;
    onDelete: (category: FinancialCategory) => void;
}

function flattenCategories(categories: FinancialCategory[]): FinancialCategory[] {
    return categories.flatMap((category) => [
        category,
        ...flattenCategories(category.children ?? []),
    ]);
}

function normalizeTreeData(
    data: FinancialCategoryCollectionResource | undefined,
): FinancialCategory[] {
    return Array.isArray(data?.data) ? data.data : [];
}

function normalizeSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .trim();
}

function categoryMatchesFilters(
    category: FinancialCategory,
    search: string,
    status: StatusFilter,
): boolean {
    const matchesStatus =
        status === 'all' ||
        (status === 'active' && category.is_active) ||
        (status === 'inactive' && !category.is_active);

    if (!matchesStatus) {
        return false;
    }

    if (!search) {
        return true;
    }

    const haystack = normalizeSearch(
        `${category.name} ${category.code ?? ''} ${category.full_path}`,
    );

    return haystack.includes(search);
}

function filterTree(
    categories: FinancialCategory[],
    search: string,
    status: StatusFilter,
): FinancialCategory[] {
    return categories.reduce<FinancialCategory[]>((filtered, category) => {
        const children = filterTree(category.children ?? [], search, status);
        const isMatch = categoryMatchesFilters(category, search, status);

        if (!isMatch && children.length === 0) {
            return filtered;
        }

        filtered.push({
            ...category,
            children,
        });

        return filtered;
    }, []);
}

function getExpandedIds(categories: FinancialCategory[]): Set<number> {
    const ids = new Set<number>();

    const visit = (category: FinancialCategory): void => {
        if ((category.children ?? []).length > 0) {
            ids.add(category.id);
        }

        for (const child of category.children ?? []) {
            visit(child);
        }
    };

    for (const category of categories) {
        visit(category);
    }

    return ids;
}

function buildTreeUrl(typeFilter: TypeFilter, statusFilter: StatusFilter): string {
    const params = new URLSearchParams();

    if (typeFilter !== 'all') {
        params.set('type', typeFilter);
    }

    if (statusFilter === 'active') {
        params.set('active', '1');
    }

    const queryString = params.toString();

    return queryString
        ? `${apiRoutes.financialCategories.tree}?${queryString}`
        : apiRoutes.financialCategories.tree;
}

function getTreeStats(categories: FinancialCategory[]): TreeStats {
    const flattened = flattenCategories(categories);

    return {
        total: flattened.length,
        roots: categories.length,
        leaves: flattened.filter(
            (category) => (category.children ?? []).length === 0,
        ).length,
        maxDepth: flattened.reduce(
            (max, category) => Math.max(max, category.depth),
            0,
        ),
    };
}

function CategoryStatusBadge({ category }: { category: FinancialCategory }) {
    return (
        <Badge variant={category.is_active ? 'default' : 'secondary'}>
            {category.is_active ? 'Ativa' : 'Inativa'}
        </Badge>
    );
}

function CategoryTreeNode({
    category,
    depth = 0,
    expandedIds,
    typeLabels,
    onToggle,
    onDelete,
}: CategoryTreeNodeProps) {
    const children = category.children ?? [];
    const hasChildren = children.length > 0;
    const isExpanded = expandedIds.has(category.id);
    const canUpdate = category.permissions.can_update;
    const canDelete = category.permissions.can_delete;

    return (
        <div className="relative">
            <div
                className={cn(
                    'group rounded-md border bg-card p-3 shadow-xs transition-colors',
                    'hover:border-primary/25 hover:bg-accent/20',
                    depth > 0 && 'ml-4 border-l-4',
                )}
                style={{
                    borderLeftColor:
                        depth > 0 ? (category.color ?? '#64748b') : undefined,
                }}
            >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        <button
                            type="button"
                            onClick={() => hasChildren && onToggle(category.id)}
                            disabled={!hasChildren}
                            aria-label={
                                hasChildren
                                    ? isExpanded
                                        ? 'Recolher categoria'
                                        : 'Expandir categoria'
                                    : 'Categoria folha'
                            }
                            className={cn(
                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border transition-colors',
                                hasChildren
                                    ? 'cursor-pointer hover:bg-background'
                                    : 'cursor-default bg-muted/50 text-muted-foreground',
                            )}
                        >
                            {hasChildren ? (
                                isExpanded ? (
                                    <ChevronDown className="size-4" />
                                ) : (
                                    <ChevronRight className="size-4" />
                                )
                            ) : (
                                <Leaf className="size-4" />
                            )}
                        </button>

                        <div className="min-w-0 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    className="size-2.5 rounded-full"
                                    style={{
                                        backgroundColor:
                                            category.color ?? '#64748b',
                                    }}
                                />
                                <h3 className="truncate text-sm font-semibold md:text-base">
                                    {category.name}
                                </h3>
                                <CategoryStatusBadge category={category} />
                                <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
                                    Nível {category.depth}
                                </span>
                            </div>

                            <p className="line-clamp-1 text-xs text-muted-foreground">
                                {category.full_path}
                            </p>

                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span>{typeLabels[category.type]}</span>
                                <span aria-hidden="true">•</span>
                                <span>{category.code ?? 'Sem código'}</span>
                                {hasChildren ? (
                                    <>
                                        <span aria-hidden="true">•</span>
                                        <span>
                                            {children.length} subcategoria
                                            {children.length === 1 ? '' : 's'}
                                        </span>
                                    </>
                                ) : null}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 hover:bg-primary/10 hover:text-primary"
                            asChild
                        >
                            <Link
                                href={show.url(category.uuid)}
                                aria-label="Visualizar categoria"
                            >
                                <Eye className="size-4" />
                            </Link>
                        </Button>

                        {canUpdate ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 hover:bg-blue-500/10 hover:text-blue-600"
                                asChild
                            >
                                <Link
                                    href={edit.url(category.uuid)}
                                    aria-label="Editar categoria"
                                >
                                    <Pencil className="size-4" />
                                </Link>
                            </Button>
                        ) : null}

                        {canDelete ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 hover:bg-destructive/10 hover:text-destructive"
                                onClick={() => onDelete(category)}
                                aria-label="Excluir categoria"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                </div>
            </div>

            {hasChildren && isExpanded ? (
                <div className="mt-2 space-y-2 border-l border-dashed border-border pl-2">
                    {children.map((child) => (
                        <CategoryTreeNode
                            key={child.uuid}
                            category={child}
                            depth={depth + 1}
                            expandedIds={expandedIds}
                            typeLabels={typeLabels}
                            onToggle={onToggle}
                            onDelete={onDelete}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export default function FinancialCategoriesIndex({ typeOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Categorias financeiras', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState<TypeFilter>('all');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('active');
    const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());
    const [categoryToDelete, setCategoryToDelete] =
        useState<FinancialCategory | null>(null);

    const deferredSearch = useDeferredValue(search);
    const normalizedSearch = normalizeSearch(deferredSearch);

    const typeLabels = useMemo(
        () =>
            Object.fromEntries(
                typeOptions.map((option) => [option.value, option.label]),
            ) as Record<FinancialCategoryType, string>,
        [typeOptions],
    );

    const treeUrl = useMemo(
        () => buildTreeUrl(typeFilter, statusFilter),
        [typeFilter, statusFilter],
    );

    const categoriesQuery = useQuery({
        queryKey: ['financial-categories-tree-index', typeFilter, statusFilter],
        queryFn: () => httpGet<FinancialCategoryCollectionResource>(treeUrl),
    });

    const rootCategories = normalizeTreeData(categoriesQuery.data);
    const filteredTree = useMemo(
        () => filterTree(rootCategories, normalizedSearch, statusFilter),
        [rootCategories, normalizedSearch, statusFilter],
    );
    const displayExpandedIds = normalizedSearch
        ? getExpandedIds(filteredTree)
        : expandedIds;
    const stats = useMemo(() => getTreeStats(filteredTree), [filteredTree]);
    const isRefreshing =
        categoriesQuery.isFetching && !categoriesQuery.isLoading;

    const handleToggle = useCallback((categoryId: number) => {
        setExpandedIds((current) => {
            const next = new Set(current);

            if (next.has(categoryId)) {
                next.delete(categoryId);
            } else {
                next.add(categoryId);
            }

            return next;
        });
    }, []);

    const handleExpandAll = useCallback(() => {
        setExpandedIds(getExpandedIds(filteredTree));
    }, [filteredTree]);

    const handleCollapseAll = useCallback(() => {
        setExpandedIds(new Set());
    }, []);

    const handleRefresh = useCallback(() => {
        void categoriesQuery.refetch();
    }, [categoriesQuery]);

    function deleteCategory(): void {
        if (!categoryToDelete) {
            return;
        }

        router.delete(destroy.url(categoryToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setCategoryToDelete(null);
                void queryClient.invalidateQueries({
                    queryKey: ['financial-categories-tree-index'],
                });
                void queryClient.invalidateQueries({
                    queryKey: ['financial-categories-tree'],
                });
            },
        });
    }

    return (
        <>
            <Head title="Categorias financeiras" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <Heading
                            title="Categorias financeiras"
                            description="Navegue pelo plano de receitas, despesas e transferências em árvore"
                        />

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleRefresh}
                                disabled={
                                    isRefreshing || categoriesQuery.isLoading
                                }
                            >
                                <RefreshCw
                                    className={cn(
                                        'size-4',
                                        isRefreshing && 'animate-spin',
                                    )}
                                />
                                {isRefreshing ? 'Atualizando...' : 'Atualizar'}
                            </Button>

                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus className="size-4" />
                                    Nova categoria
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Card>
                            <CardContent className="flex items-center justify-between p-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Categorias
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {stats.total}
                                    </p>
                                </div>
                                <Layers3 className="size-5 text-primary" />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center justify-between p-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Raízes
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {stats.roots}
                                    </p>
                                </div>
                                <FolderTree className="size-5 text-sky-600" />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center justify-between p-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Categorias folha
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {stats.leaves}
                                    </p>
                                </div>
                                <Leaf className="size-5 text-emerald-600" />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center justify-between p-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Profundidade
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {stats.total > 0 ? stats.maxDepth + 1 : 0}
                                    </p>
                                </div>
                                <Filter className="size-5 text-amber-600" />
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Filter className="size-4" />
                                Filtros da árvore
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Buscar por nome, código ou caminho..."
                                    className="pl-9"
                                />
                            </div>

                            <Select
                                value={typeFilter}
                                onValueChange={(value) =>
                                    setTypeFilter(value as TypeFilter)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos os tipos
                                    </SelectItem>
                                    {typeOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={statusFilter}
                                onValueChange={(value) =>
                                    setStatusFilter(value as StatusFilter)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Ativas</SelectItem>
                                    <SelectItem value="inactive">
                                        Inativas
                                    </SelectItem>
                                    <SelectItem value="all">Todas</SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleExpandAll}
                                >
                                    Abrir tudo
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={handleCollapseAll}
                                >
                                    Fechar
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b pb-4">
                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <CardTitle className="text-base">
                                    Estrutura hierárquica
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    {filteredTree.length} raiz
                                    {filteredTree.length === 1 ? '' : 'es'}{' '}
                                    exibida
                                    {filteredTree.length === 1 ? '' : 's'}
                                </p>
                            </div>
                        </CardHeader>
                        <CardContent className="p-4">
                            {categoriesQuery.isLoading ? (
                                <div className="grid gap-3">
                                    {[1, 2, 3].map((item) => (
                                        <div
                                            key={item}
                                            className="h-20 animate-pulse rounded-md bg-muted"
                                        />
                                    ))}
                                </div>
                            ) : null}

                            {categoriesQuery.isError ? (
                                <div className="rounded-md border border-destructive/20 bg-destructive/5 p-6 text-center text-sm text-destructive">
                                    Não foi possível carregar a árvore de
                                    categorias.
                                </div>
                            ) : null}

                            {!categoriesQuery.isLoading &&
                            !categoriesQuery.isError &&
                            filteredTree.length === 0 ? (
                                <div className="rounded-md border border-dashed p-10 text-center">
                                    <FolderTree className="mx-auto mb-3 size-8 text-muted-foreground" />
                                    <p className="font-medium">
                                        Nenhuma categoria encontrada
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Ajuste os filtros ou cadastre uma nova
                                        categoria.
                                    </p>
                                </div>
                            ) : null}

                            {!categoriesQuery.isLoading &&
                            !categoriesQuery.isError &&
                            filteredTree.length > 0 ? (
                                <div className="space-y-3">
                                    {filteredTree.map((category) => (
                                        <CategoryTreeNode
                                            key={category.uuid}
                                            category={category}
                                            expandedIds={displayExpandedIds}
                                            typeLabels={typeLabels}
                                            onToggle={handleToggle}
                                            onDelete={setCategoryToDelete}
                                        />
                                    ))}
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    <FinancialCategoryDeleteDialog
                        category={categoryToDelete}
                        onCancel={() => setCategoryToDelete(null)}
                        onConfirm={deleteCategory}
                    />
                </div>
            </div>
        </>
    );
}

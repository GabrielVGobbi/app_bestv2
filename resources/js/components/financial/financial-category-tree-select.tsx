import { useQuery } from '@tanstack/react-query';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    FolderTree,
    Loader2,
    Search,
    X,
} from 'lucide-react';
import type { MouseEvent, TouchEvent, WheelEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpGet } from '@/lib/http';
import { cn } from '@/lib/utils';
import type {
    FinancialCategory,
    FinancialCategoryCollectionResource,
    FinancialCategoryType,
} from '@/types/app/financial-category';

interface FinancialCategoryTreeSelectProps {
    name?: string;
    value?: string | null;
    onChange?: (value: string | null) => void;
    type?: FinancialCategoryType;
    companyId?: number | null;
    mode?: 'entry' | 'filter';
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    clearable?: boolean;
    initialLabel?: string;
    className?: string;
}

function flattenCategories(categories: FinancialCategory[]): FinancialCategory[] {
    return categories.flatMap((category) => [
        category,
        ...flattenCategories(category.children ?? []),
    ]);
}

function buildUrl(type?: FinancialCategoryType, companyId?: number | null): string {
    const params = new URLSearchParams();
    params.set('active', '1');

    if (type) {
        params.set('type', type);
    }

    if (companyId) {
        params.set('company_id', String(companyId));
    }

    return `${apiRoutes.financialCategories.tree}?${params.toString()}`;
}

function CategoryPath({ category }: { category: FinancialCategory }) {
    const path =
        category.breadcrumb.length > 0
            ? category.breadcrumb.map((item) => item.name)
            : [category.name];

    return (
        <span className="flex min-w-0 flex-wrap items-center gap-1">
            {path.map((item, index) => (
                <span key={`${item}-${index}`} className="inline-flex min-w-0 items-center gap-1">
                    {index > 0 ? (
                        <span className="text-muted-foreground/50">/</span>
                    ) : null}
                    <span
                        className={cn(
                            index === path.length - 1
                                ? 'font-medium'
                                : 'text-muted-foreground',
                        )}
                    >
                        {item}
                    </span>
                </span>
            ))}
        </span>
    );
}

function stopScrollPropagation(
    event: WheelEvent<HTMLDivElement> | TouchEvent<HTMLDivElement>,
) {
    event.stopPropagation();
}

export default function FinancialCategoryTreeSelect({
    name,
    value,
    onChange,
    type,
    companyId,
    mode = 'entry',
    placeholder = 'Selecione uma categoria',
    disabled = false,
    required = false,
    clearable = true,
    initialLabel,
    className,
}: FinancialCategoryTreeSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [path, setPath] = useState<FinancialCategory[]>([]);
    const autoNavigatedForValue = useRef<string | null>(null);

    const url = useMemo(() => buildUrl(type, companyId), [type, companyId]);

    const { data, isError, isLoading } = useQuery({
        queryKey: ['financial-categories-tree', type, companyId],
        queryFn: () => httpGet<FinancialCategoryCollectionResource>(url),
        enabled: isOpen,
    });

    const rootCategories = Array.isArray(data?.data) ? data.data : [];
    const allCategories = useMemo(
        () => flattenCategories(rootCategories),
        [rootCategories],
    );
    const currentParent = path.at(-1);
    const currentOptions = currentParent?.children ?? rootCategories;
    const selectedCategory = allCategories.find(
        (category) => String(category.id) === value,
    );

    useEffect(() => {
        if (!isOpen) {
            autoNavigatedForValue.current = null;
            return;
        }

        if (!value || allCategories.length === 0) {
            return;
        }

        if (autoNavigatedForValue.current === value || !selectedCategory) {
            return;
        }

        const ancestors = selectedCategory.breadcrumb
            .slice(0, -1)
            .map((item) =>
                allCategories.find((category) => category.id === item.id),
            )
            .filter(
                (category): category is FinancialCategory =>
                    category !== undefined,
            );

        setPath(ancestors);
        autoNavigatedForValue.current = value;
    }, [allCategories, isOpen, selectedCategory, value]);

    const searchedOptions = useMemo(() => {
        const needle = search.trim().toLowerCase();

        if (!needle) {
            return [];
        }

        return allCategories.filter(
            (category) =>
                category.full_path.toLowerCase().includes(needle) ||
                category.name.toLowerCase().includes(needle),
        );
    }, [allCategories, search]);

    const isSearching = search.trim() !== '';
    const options = isSearching ? searchedOptions : currentOptions;
    const canSelectParent = mode === 'filter';
    const displayLabel = selectedCategory?.full_path || initialLabel;

    function handleSelect(category: FinancialCategory): void {
        const hasChildren =
            category.has_children && (category.children?.length ?? 0) > 0;

        if (hasChildren && !canSelectParent && !isSearching) {
            setPath((current) => [...current, category]);
            return;
        }

        if (hasChildren && !canSelectParent && isSearching) {
            setPath(
                category.breadcrumb
                    .slice(0, -1)
                    .map((breadcrumb) =>
                        allCategories.find((item) => item.id === breadcrumb.id),
                    )
                    .filter(
                        (item): item is FinancialCategory => item !== undefined,
                    ),
            );
            setSearch('');
            return;
        }

        onChange?.(String(category.id));
        setIsOpen(false);
        setSearch('');
    }

    function handleClear(event: MouseEvent): void {
        event.stopPropagation();
        onChange?.(null);
    }

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    role="combobox"
                    aria-expanded={isOpen}
                    aria-haspopup="tree"
                    aria-required={required || undefined}
                    disabled={disabled}
                    className={cn(
                        'flex h-10 w-full cursor-pointer items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-left text-sm shadow-xs transition-colors',
                        'hover:border-primary/40 focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/20 focus-visible:outline-none',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        className,
                    )}
                >
                    <span className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                        <FolderTree className="size-4 shrink-0 text-muted-foreground" />
                        {displayLabel ? (
                            <span className="truncate">{displayLabel}</span>
                        ) : (
                            <span className="truncate text-muted-foreground">
                                {placeholder}
                            </span>
                        )}
                    </span>

                    {clearable && value && !disabled ? (
                        <span
                            role="button"
                            tabIndex={0}
                            aria-label="Limpar categoria"
                            onClick={handleClear}
                            className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <X className="size-3.5" />
                        </span>
                    ) : null}
                </button>
            </PopoverTrigger>

            <PopoverContent
                align="start"
                className="min-w-80 p-0 sm:w-[420px]"
                onWheelCapture={stopScrollPropagation}
                onTouchMoveCapture={stopScrollPropagation}
            >
                <div className="border-b bg-muted/30 p-2">
                    <div className="flex items-center gap-2 rounded-md border bg-background px-2">
                        <Search className="size-4 text-muted-foreground" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar na arvore..."
                            className="h-9 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </div>
                </div>

                {!isSearching && path.length > 0 ? (
                    <div className="flex items-center gap-2 border-b px-2 py-2 text-xs text-muted-foreground">
                        <button
                            type="button"
                            onClick={() => setPath((current) => current.slice(0, -1))}
                            className="inline-flex items-center gap-1 rounded-md px-2 py-1 transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <ChevronLeft className="size-3.5" />
                            Voltar
                        </button>
                        <span className="truncate">
                            {path.map((item) => item.name).join(' / ')}
                        </span>
                    </div>
                ) : null}

                <div
                    role="tree"
                    className="max-h-80 overflow-y-auto overscroll-contain p-1"
                    onWheel={stopScrollPropagation}
                    onTouchMove={stopScrollPropagation}
                >
                    {isLoading ? (
                        <div className="flex items-center justify-center py-6">
                            <Loader2 className="size-5 animate-spin text-muted-foreground" />
                        </div>
                    ) : null}

                    {isError ? (
                        <div className="px-3 py-6 text-center text-sm text-destructive">
                            Não foi possível carregar as categorias.
                        </div>
                    ) : null}

                    {!isLoading && !isError && options.length === 0 ? (
                        <div className="px-3 py-6 text-center text-sm text-muted-foreground">
                            Nenhuma categoria encontrada.
                        </div>
                    ) : null}

                    {!isLoading && !isError
                        ? options.map((category) => {
                              const isSelected = String(category.id) === value;
                              const hasChildren =
                                  category.has_children &&
                                  (category.children?.length ?? 0) > 0;
                              const canSelect = canSelectParent || !hasChildren;

                              return (
                                  <button
                                      key={category.id}
                                      type="button"
                                      role="treeitem"
                                      aria-selected={isSelected}
                                      onClick={() => handleSelect(category)}
                                      className={cn(
                                          'flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors',
                                          'hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:outline-none',
                                          isSelected &&
                                              'bg-primary/10 text-primary',
                                      )}
                                  >
                                      <span
                                          className="size-2.5 shrink-0 rounded-full"
                                          style={{
                                              backgroundColor:
                                                  category.color ?? '#64748b',
                                          }}
                                      />

                                      <span className="min-w-0 flex-1">
                                          {isSearching ? (
                                              <CategoryPath category={category} />
                                          ) : (
                                              <span className="block truncate font-medium">
                                                  {category.name}
                                              </span>
                                          )}
                                          {hasChildren && canSelectParent ? (
                                              <span className="block text-xs text-muted-foreground">
                                                  Inclui subcategorias
                                              </span>
                                          ) : null}
                                      </span>

                                      {isSelected ? (
                                          <Check className="size-4 shrink-0" />
                                      ) : null}
                                      {!isSelected && hasChildren && !canSelect ? (
                                          <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                      ) : null}
                                  </button>
                              );
                          })
                        : null}
                </div>
            </PopoverContent>

            {name ? <input type="hidden" name={name} value={value ?? ''} /> : null}
        </Popover>
    );
}

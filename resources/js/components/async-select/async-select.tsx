import { Check, ChevronDown, Loader2, Search, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { MouseEvent, ReactNode, UIEvent } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export interface AsyncSelectOption {
    value: string;
    label: string;
    description?: string;
    disabled?: boolean;
    imageUrl?: string | null;
    avatarInitials?: string | null;
    avatarColor?: string | null;
}

interface AsyncSelectResponse {
    data: unknown[];
    meta?: {
        current_page?: number;
        last_page?: number;
    };
}

interface AsyncSelectBaseProps {
    placeholder?: string;
    fieldLabel?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    clearable?: boolean;
    className?: string;
    endpoint?: string;
    options?: AsyncSelectOption[];
    params?: Record<string, string | number | boolean | null | undefined>;
    pageSize?: number;
    debounceMs?: number;
    maxVisibleSelected?: number;
    selectedOverflowLabel?: (hiddenCount: number) => ReactNode;
    transformOption?: (item: unknown) => AsyncSelectOption;
    renderOption?: (option: AsyncSelectOption) => ReactNode;
}

interface AsyncSelectSingleProps extends AsyncSelectBaseProps {
    multiple?: false;
    value: string | null;
    initialLabel?: string;
    onChange: (value: string | null) => void;
}

interface AsyncSelectMultipleProps extends AsyncSelectBaseProps {
    multiple: true;
    value: string[];
    initialLabel?: Record<string, string>;
    onChange: (value: string[]) => void;
}

export type AsyncSelectProps =
    | AsyncSelectSingleProps
    | AsyncSelectMultipleProps;

function textOrUndefined(value: unknown): string | undefined {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return undefined;
    }

    const text = String(value).trim();

    return text === '' ? undefined : text;
}

function defaultTransformOption(item: unknown): AsyncSelectOption {
    if (typeof item === 'object' && item !== null) {
        const record = item as Record<string, unknown>;

        return {
            value: String(record.value ?? record.id ?? record.uuid ?? ''),
            label:
                textOrUndefined(record.label) ??
                textOrUndefined(record.name) ??
                textOrUndefined(record.email) ??
                '',
            description: textOrUndefined(record.description),
        };
    }

    return {
        value: String(item),
        label: String(item),
    };
}

function selectedValues(value: AsyncSelectProps['value']): string[] {
    return Array.isArray(value) ? value : value ? [value] : [];
}

const avatarColorClasses: Record<string, string> = {
    rose: 'border-rose-200 bg-rose-50 text-rose-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    sky: 'border-sky-200 bg-sky-50 text-sky-700',
    violet: 'border-violet-200 bg-violet-50 text-violet-700',
    slate: 'border-slate-200 bg-slate-50 text-slate-700',
};

function OptionAvatar({
    option,
    className,
}: {
    option: AsyncSelectOption;
    className?: string;
}) {
    if (!option.imageUrl && !option.avatarInitials) {
        return null;
    }

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-md border text-[10px] font-semibold leading-none',
                option.imageUrl
                    ? 'border-border bg-muted'
                    : (avatarColorClasses[option.avatarColor ?? ''] ??
                        avatarColorClasses.rose),
                className,
            )}
            aria-hidden="true"
        >
            {option.imageUrl ? (
                <img
                    src={option.imageUrl}
                    alt=""
                    className="size-full object-cover"
                    loading="lazy"
                />
            ) : (
                option.avatarInitials
            )}
        </span>
    );
}

function useDebouncedValue(value: string, delay: number): string {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timeout = window.setTimeout(
            () => setDebouncedValue(value),
            delay,
        );

        return () => window.clearTimeout(timeout);
    }, [delay, value]);

    return debouncedValue;
}

export function AsyncSelect(props: AsyncSelectProps) {
    const {
        placeholder = 'Selecione...',
        fieldLabel,
        searchPlaceholder = 'Buscar...',
        emptyMessage = 'Nenhuma opcao encontrada.',
        disabled = false,
        clearable = false,
        className,
        endpoint,
        options,
        params,
        pageSize = 40,
        debounceMs = 300,
        maxVisibleSelected,
        selectedOverflowLabel,
        transformOption = defaultTransformOption,
        renderOption,
    } = props;

    const [open, setOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [asyncOptions, setAsyncOptions] = useState<AsyncSelectOption[]>([]);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [labelCache, setLabelCache] = useState<Record<string, string>>({});
    const [optionCache, setOptionCache] = useState<
        Record<string, AsyncSelectOption>
    >({});
    const scrollAreaRef = useRef<HTMLDivElement | null>(null);
    const loadMoreRef = useRef<HTMLDivElement | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const hydrateAbortRef = useRef<AbortController | null>(null);
    const hydratedValuesRef = useRef<Set<string>>(new Set());
    const isLoadingRef = useRef(false);
    const debouncedSearch = useDebouncedValue(searchTerm, debounceMs);

    const isAsync = Boolean(endpoint);
    const selected = selectedValues(props.value);

    const visibleOptions = useMemo(() => {
        if (isAsync) {
            return asyncOptions;
        }

        const normalizedSearch = searchTerm.trim().toLowerCase();

        if (!normalizedSearch) {
            return options ?? [];
        }

        return (options ?? []).filter((option) =>
            [option.label, option.description]
                .filter(Boolean)
                .some((value) =>
                    value?.toLowerCase().includes(normalizedSearch),
                ),
        );
    }, [asyncOptions, isAsync, options, searchTerm]);

    const selectedLabelMap = useMemo(() => {
        const labels = new Map<string, string>();

        for (const [value, label] of Object.entries(labelCache)) {
            labels.set(value, label);
        }

        for (const option of [...(options ?? []), ...asyncOptions]) {
            labels.set(option.value, option.label);
        }

        if (!props.multiple && props.value && props.initialLabel) {
            labels.set(props.value, props.initialLabel);
        }

        if (props.multiple && props.initialLabel) {
            for (const [value, label] of Object.entries(props.initialLabel)) {
                labels.set(value, label);
            }
        }

        return labels;
    }, [asyncOptions, labelCache, options, props]);

    const selectedOptionMap = useMemo(() => {
        const optionMap = new Map<string, AsyncSelectOption>();

        for (const option of Object.values(optionCache)) {
            optionMap.set(option.value, option);
        }

        for (const option of [...(options ?? []), ...asyncOptions]) {
            optionMap.set(option.value, option);
        }

        if (
            !props.multiple &&
            props.value &&
            props.initialLabel &&
            !optionMap.has(props.value)
        ) {
            optionMap.set(props.value, {
                value: props.value,
                label: props.initialLabel,
            });
        }

        if (props.multiple && props.initialLabel) {
            for (const [value, label] of Object.entries(props.initialLabel)) {
                if (!optionMap.has(value)) {
                    optionMap.set(value, { value, label });
                }
            }
        }

        return optionMap;
    }, [asyncOptions, optionCache, options, props]);

    const loadOptions = useCallback(
        async (nextPage: number, append = false): Promise<void> => {
            if (!endpoint || (append && isLoadingRef.current)) {
                return;
            }

            if (!append) {
                abortRef.current?.abort();
            }

            const controller = new AbortController();
            abortRef.current = controller;
            isLoadingRef.current = true;
            setIsLoading(true);
            setError(null);

            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('search', debouncedSearch);
            url.searchParams.set('page', String(nextPage));
            url.searchParams.set('limit', String(pageSize));
            url.searchParams.set('per_page', String(pageSize));

            for (const [key, value] of Object.entries(params ?? {})) {
                if (value !== null && value !== undefined && value !== '') {
                    url.searchParams.set(key, String(value));
                }
            }

            try {
                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Nao foi possivel carregar as opcoes.');
                }

                const payload = (await response.json()) as AsyncSelectResponse;
                const nextOptions = payload.data.map(transformOption);
                const currentPage = payload.meta?.current_page ?? nextPage;
                const lastPage = payload.meta?.last_page ?? currentPage;
                const nextLabels = Object.fromEntries(
                    nextOptions.map((option) => [option.value, option.label]),
                );

                setAsyncOptions((currentOptions) =>
                    append ? [...currentOptions, ...nextOptions] : nextOptions,
                );
                setLabelCache((currentLabels) => ({
                    ...currentLabels,
                    ...nextLabels,
                }));
                setOptionCache((currentOptions) => ({
                    ...currentOptions,
                    ...Object.fromEntries(
                        nextOptions.map((option) => [option.value, option]),
                    ),
                }));
                setPage(currentPage);
                setHasMore(currentPage < lastPage);
            } catch (caughtError) {
                if (!(caughtError instanceof DOMException)) {
                    setError(
                        caughtError instanceof Error
                            ? caughtError.message
                            : 'Nao foi possivel carregar as opcoes.',
                    );
                }
            } finally {
                isLoadingRef.current = false;
                setIsLoading(false);
            }
        },
        [debouncedSearch, endpoint, pageSize, params, transformOption],
    );

    const hydrateSelectedOptions = useCallback(
        async (missingValues: string[]): Promise<void> => {
            if (!endpoint || missingValues.length === 0) {
                return;
            }

            hydrateAbortRef.current?.abort();

            const controller = new AbortController();
            hydrateAbortRef.current = controller;

            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('ids', missingValues.join(','));
            url.searchParams.set('values', missingValues.join(','));
            url.searchParams.set('limit', String(missingValues.length));
            url.searchParams.set(
                'per_page',
                String(Math.max(missingValues.length, 1)),
            );

            for (const value of missingValues) {
                url.searchParams.append('ids[]', value);
                url.searchParams.append('values[]', value);
            }

            for (const [key, value] of Object.entries(params ?? {})) {
                if (value !== null && value !== undefined && value !== '') {
                    url.searchParams.set(key, String(value));
                }
            }

            try {
                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Nao foi possivel carregar as opcoes.');
                }

                const payload = (await response.json()) as AsyncSelectResponse;
                const nextOptions = payload.data.map(transformOption);
                const nextLabels = Object.fromEntries(
                    nextOptions.map((option) => [option.value, option.label]),
                );

                setLabelCache((currentLabels) => ({
                    ...currentLabels,
                    ...nextLabels,
                }));
                setOptionCache((currentOptions) => ({
                    ...currentOptions,
                    ...Object.fromEntries(
                        nextOptions.map((option) => [option.value, option]),
                    ),
                }));
            } catch (caughtError) {
                if (caughtError instanceof DOMException) {
                    return;
                }

                for (const value of missingValues) {
                    hydratedValuesRef.current.delete(value);
                }
            }
        },
        [endpoint, params, transformOption],
    );

    useEffect(() => {
        const missingValues = selected.filter(
            (value) =>
                !labelCache[value] && !hydratedValuesRef.current.has(value),
        );

        if (missingValues.length === 0) {
            return;
        }

        for (const value of missingValues) {
            hydratedValuesRef.current.add(value);
        }

        void hydrateSelectedOptions(missingValues);
    }, [hydrateSelectedOptions, labelCache, selected]);

    useEffect(() => {
        if (open && endpoint) {
            const timeout = window.setTimeout(() => void loadOptions(1), 0);

            return () => window.clearTimeout(timeout);
        }
    }, [endpoint, loadOptions, open]);

    useEffect(() => {
        const target = loadMoreRef.current;
        const scrollArea = scrollAreaRef.current;

        if (!open || !endpoint || !hasMore || !target || !scrollArea) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting && !isLoadingRef.current) {
                    void loadOptions(page + 1, true);
                }
            },
            {
                root: scrollArea,
                rootMargin: '80px',
            },
        );

        observer.observe(target);

        return () => observer.disconnect();
    }, [endpoint, hasMore, loadOptions, open, page]);

    const selectedLabels = selected.map(
        (value) => selectedLabelMap.get(value) ?? value,
    );
    const selectedOptions = selected.map(
        (value, index) =>
            selectedOptionMap.get(value) ?? {
                value,
                label: selectedLabels[index] ?? value,
            },
    );
    const selectedDisplayLimit =
        typeof maxVisibleSelected === 'number'
            ? Math.max(0, maxVisibleSelected)
            : null;
    const visibleSelectedLabels =
        selectedDisplayLimit === null
            ? selectedLabels
            : selectedLabels.slice(0, selectedDisplayLimit);
    const hiddenSelectedCount =
        selectedDisplayLimit === null
            ? 0
            : Math.max(selectedLabels.length - selectedDisplayLimit, 0);

    function isSelected(option: AsyncSelectOption): boolean {
        return selected.includes(option.value);
    }

    function selectOption(option: AsyncSelectOption): void {
        if (option.disabled) {
            return;
        }

        setLabelCache((currentLabels) => ({
            ...currentLabels,
            [option.value]: option.label,
        }));
        setOptionCache((currentOptions) => ({
            ...currentOptions,
            [option.value]: option,
        }));

        if (props.multiple) {
            props.onChange(
                isSelected(option)
                    ? props.value.filter((value) => value !== option.value)
                    : [...props.value, option.value],
            );

            return;
        }

        props.onChange(option.value);
        setOpen(false);
    }

    function clearValue(event?: MouseEvent): void {
        event?.stopPropagation();

        if (props.multiple) {
            props.onChange([]);

            return;
        }

        props.onChange(null);
    }

    function loadNextPage(): void {
        if (!endpoint || !hasMore || isLoadingRef.current) {
            return;
        }

        void loadOptions(page + 1, true);
    }

    function handleScroll(event: UIEvent<HTMLDivElement>): void {
        const target = event.currentTarget;
        const distanceFromBottom =
            target.scrollHeight - target.scrollTop - target.clientHeight;

        if (distanceFromBottom <= 48) {
            loadNextPage();
        }
    }

    const hasSelection = selected.length > 0;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'h-auto min-h-9 w-full justify-between px-3 py-2 font-normal',
                        !hasSelection && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 text-left">
                        {hasSelection ? (
                            props.multiple ? (
                                <>
                                    {fieldLabel && (
                                        <span className="text-muted-foreground">
                                            {fieldLabel}
                                            <span className="px-1">·</span>
                                        </span>
                                    )}
                                    {visibleSelectedLabels.map(
                                        (label, index) => (
                                            <Badge
                                                key={selected[index]}
                                                variant="secondary"
                                                className="max-w-40 truncate"
                                            >
                                                {label}
                                            </Badge>
                                        ),
                                    )}
                                    {hiddenSelectedCount > 0 && (
                                        <Badge
                                            variant="outline"
                                            className="shrink-0"
                                        >
                                            {selectedOverflowLabel
                                                ? selectedOverflowLabel(
                                                    hiddenSelectedCount,
                                                )
                                                : `+${hiddenSelectedCount}`}
                                        </Badge>
                                    )}
                                </>
                            ) : (
                                <span className="flex min-w-0 items-center gap-2">
                                    {selectedOptions[0] && (
                                        <OptionAvatar
                                            option={selectedOptions[0]}
                                            className="size-6"
                                        />
                                    )}
                                    <span className="min-w-0 truncate">
                                        {fieldLabel ? (
                                            <>
                                                <span className="text-muted-foreground">
                                                    {fieldLabel}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' · '}
                                                </span>
                                                <span className="font-medium">
                                                    {selectedLabels[0]}
                                                </span>
                                            </>
                                        ) : (
                                            selectedLabels[0]
                                        )}
                                    </span>
                                </span>
                            )
                        ) : (
                            placeholder
                        )}
                    </span>
                    <span className="flex shrink-0 items-center gap-1">
                        {clearable && hasSelection && !disabled && (
                            <span
                                role="button"
                                aria-label="Limpar seleção"
                                tabIndex={0}
                                className="rounded-sm p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                onClick={clearValue}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ' '
                                    ) {
                                        clearValue();
                                    }
                                }}
                            >
                                <X className="size-4" />
                            </span>
                        )}
                        <ChevronDown className="size-4 opacity-50" />
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="z-[70] w-(--radix-popover-trigger-width) p-0"
            >
                <div className="border-b p-2">
                    <div className="relative">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={searchTerm}
                            placeholder={searchPlaceholder}
                            className="h-9 pl-8"
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                        />
                    </div>
                </div>

                <div
                    ref={scrollAreaRef}
                    className="max-h-72 overflow-y-auto overscroll-contain p-1"
                    onScroll={handleScroll}
                >
                    {visibleOptions.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            disabled={option.disabled}
                            className={cn(
                                'flex w-full items-center gap-2 rounded-sm px-2 py-2 text-left text-sm outline-none hover:bg-accent disabled:pointer-events-none disabled:opacity-50',
                                isSelected(option) && 'bg-accent',
                            )}
                            onClick={() => selectOption(option)}
                        >
                            {isSelected(option) && (
                                <span className="flex size-4 items-center justify-center">
                                    <Check className="size-4 text-primary" />
                                </span>
                            )}
                            {!renderOption && (
                                <OptionAvatar option={option} className="size-7" />
                            )}
                            <span className="min-w-0 flex-1">
                                {renderOption ? (
                                    renderOption(option)
                                ) : (
                                    <>
                                        <span className="block truncate">
                                            {option.label}
                                        </span>
                                        {option.description && (
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {option.description}
                                            </span>
                                        )}
                                    </>
                                )}
                            </span>
                        </button>
                    ))}

                    {!isLoading && visibleOptions.length === 0 && (
                        <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                            {error ?? emptyMessage}
                        </div>
                    )}

                    {isLoading && (
                        <div className="flex items-center justify-center gap-2 px-3 py-4 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Carregando...
                        </div>
                    )}

                    {isAsync && hasMore && (
                        <div
                            ref={loadMoreRef}
                            className="flex min-h-8 items-center justify-center px-3 py-2 text-xs text-muted-foreground"
                        >
                            {isLoading ? (
                                <>
                                    <Loader2 className="mr-2 size-3 animate-spin" />
                                    Carregando mais...
                                </>
                            ) : (
                                'Role para carregar mais'
                            )}
                        </div>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}

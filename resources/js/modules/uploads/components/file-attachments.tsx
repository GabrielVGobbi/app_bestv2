import {
    Archive,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileX,
    Loader2,
    Paperclip,
    Plus,
    CheckSquare,
    Upload as UploadIcon,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import { DeleteConfirmModal } from '@/components/delete-confirm-modal';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { getErrorMessage, toApiError } from '@/lib/http';
import { cn } from '@/lib/utils';

import { useBulkDeleteUploads } from '../api/mutations';
import type { ModelId, TagSuggestion, Upload } from '../api/types';
import { useUploadQueue } from '../hooks/use-upload-queue';
import type { UploadTask } from '../hooks/use-upload-queue';
import { useUploads } from '../hooks/use-uploads';
import { useZipRequest } from '../hooks/use-zip-request';
import type { UploadRejection } from '../lib/filter-files';
import { BulkActionBar } from './bulk-action-bar';
import { DropOverlay } from './drop-overlay';
import { UploadDropzone } from './upload-dropzone';
import { UploadItem } from './upload-item';
import { UploadQueueItem } from './upload-queue-item';
import { UploadQuickPreview } from './upload-quick-preview';

export type FileAttachmentsVariant =
    | 'inline'
    | 'modal'
    | 'drop-overlay'
    | 'toggle';

export interface FileAttachmentsProps {
    modelType: string;
    modelId: ModelId;
    tag?: string | null;
    collection?: string | null;
    defaultTag?: string | null;
    maxFiles?: number;
    maxSize?: number;
    accept?: string;
    showCoverOption?: boolean;
    readOnly?: boolean;
    title?: string | null;
    description?: string | null;
    className?: string;
    variant?: FileAttachmentsVariant;
    attachLabel?: string;
    concurrency?: number;
    listMaxHeight?: string | number;
}

const EMPTY_UPLOADS: Upload[] = [];

export function FileAttachments(props: FileAttachmentsProps) {
    const scopeKey = JSON.stringify([
        props.modelType,
        String(props.modelId),
        props.tag ?? null,
        props.collection ?? null,
        props.defaultTag ?? null,
    ]);

    return <FileAttachmentsBody key={scopeKey} {...props} />;
}

function FileAttachmentsBody({
    modelType,
    modelId,
    tag,
    collection,
    defaultTag,
    maxFiles,
    maxSize = 50,
    accept,
    showCoverOption = false,
    readOnly = false,
    title = 'Anexos',
    description,
    className,
    variant = 'inline',
    attachLabel = 'Anexar',
    concurrency = 3,
    listMaxHeight = 'min(32rem, 60vh)',
}: FileAttachmentsProps) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const { list, tags, update, remove, setCover, toggleFavorite } = useUploads(
        modelType,
        modelId,
        {
            tag: tag ?? undefined,
            collection: collection ?? undefined,
            search: search.trim() || undefined,
            page,
            per_page: 50,
        },
    );

    const queue = useUploadQueue({
        modelType,
        modelId,
        tag: tag ?? null,
        defaultTag: defaultTag ?? null,
        collection: collection ?? null,
        concurrency,
    });

    const bulkDelete = useBulkDeleteUploads(modelType, modelId);
    const { createZip, isPending: isZipping } = useZipRequest();

    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);
    const [isPickerOpen, setPickerOpen] = useState(false);
    const [isToggleOpen, setToggleOpen] = useState(false);
    const [isSelectionMode, setSelectionMode] = useState(false);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [previewId, setPreviewId] = useState<number | null>(null);

    const rawItems = list.data?.data ?? EMPTY_UPLOADS;
    const total = list.data?.meta?.total ?? rawItems.length;
    const lastPage = Math.max(1, list.data?.meta?.last_page ?? 1);
    const suggestions = tags.data?.data ?? [];
    const [previousItems, setPreviousItems] = useState(rawItems);

    if (previousItems !== rawItems) {
        const validIds = new Set(rawItems.map((item) => item.id));
        setPreviousItems(rawItems);
        setSelectedIds((previous) => {
            const next = new Set(
                [...previous].filter((id) => validIds.has(id)),
            );

            return next.size === previous.size ? previous : next;
        });
    }

    if (list.isSuccess && !list.isFetching && page > lastPage) {
        setPage(lastPage);
        setSelectedIds(new Set());
        setPreviewId(null);
        setPendingDeleteId(null);
        setBulkDeleteOpen(false);
    }

    const items = rawItems;

    function changePage(nextPage: number) {
        setSelectedIds(new Set());
        setPreviewId(null);
        setPendingDeleteId(null);
        setBulkDeleteOpen(false);
        setPage(nextPage);
    }

    function changeSearch(value: string) {
        setSearch(value);
        changePage(1);
    }

    function handleUpload(files: File[], rejected: UploadRejection[]) {
        if (rejected.length > 0) {
            queue.enqueueRejected(rejected);
        }

        if (files.length > 0) {
            queue.enqueue(files);
        }

        if (files.length > 0) {
            setPickerOpen(false);
            setToggleOpen(false);
        }
    }

    function handleRename(id: number, name: string) {
        update.mutate({ id, data: { name } });
    }

    function handleChangeTag(id: number, next: string | null) {
        update.mutate({ id, data: { tag: next } });
    }

    function confirmDelete() {
        if (pendingDeleteId === null) {
            return;
        }

        const id = pendingDeleteId;
        setPendingDeleteId(null);
        remove.mutate(id);
    }

    function handleSelect(id: number, checked: boolean) {
        setSelectedIds((prev) => {
            const next = new Set(prev);

            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });
    }

    function handleSelectAll() {
        setSelectedIds(new Set(items.map((i) => i.id)));
    }

    function handleClearSelection() {
        setSelectedIds(new Set());
    }

    function exitSelectionMode() {
        setSelectionMode(false);
        setSelectedIds(new Set());
    }

    function confirmBulkDelete() {
        const ids = Array.from(selectedIds);
        setBulkDeleteOpen(false);
        bulkDelete.mutate(ids, {
            onSuccess: () => exitSelectionMode(),
        });
    }

    function handleBulkDownload() {
        createZip({
            ids: Array.from(selectedIds),
            label: `${selectedIds.size} arquivo${selectedIds.size !== 1 ? 's' : ''}`,
        });
    }

    function handleDownloadAll() {
        if (total === 0 || total > 200 || list.isFetching || list.isError) {
            return;
        }

        createZip({
            all_matching: {
                parentable_type: modelType,
                parentable_id: modelId,
                tag: tag ?? undefined,
                collection: collection ?? undefined,
                search: search.trim() || undefined,
            },
            label: title ?? 'Download de anexos',
        });
    }

    const isListLoading = list.isLoading;
    const isEmpty =
        !isListLoading &&
        !list.isError &&
        items.length === 0 &&
        queue.items.length === 0;
    const showHeader = title !== null;
    const downloadAllLabel =
        search.trim() !== ''
            ? 'Baixar todos os resultados da busca como ZIP'
            : 'Baixar todos os arquivos filtrados como ZIP';
    const showHeaderActionButton =
        !readOnly && (variant === 'modal' || variant === 'toggle');
    const hasSelection = selectedIds.size > 0;

    const previewIndex =
        previewId === null ? -1 : rawItems.findIndex((i) => i.id === previewId);
    const previewUpload = previewIndex >= 0 ? rawItems[previewIndex] : null;

    const queueBlock = queue.items.length > 0 && (
        <UploadQueueBlock
            items={queue.items}
            onCancel={queue.cancel}
            onRetry={queue.retry}
            hasErrors={queue.hasErrors}
            onClearErrors={queue.clearErrors}
        />
    );

    const listBlock = list.isError ? (
        <div
            role="alert"
            className="space-y-2 rounded-lg border border-destructive/30 p-3 text-sm"
        >
            <p>
                Não foi possível carregar os arquivos.{' '}
                {getErrorMessage(toApiError(list.error))}
            </p>
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => void list.refetch()}
                disabled={list.isFetching}
            >
                Tentar novamente
            </Button>
        </div>
    ) : (
        <AttachmentsListBlock
            items={items}
            isLoading={isListLoading}
            isEmpty={isEmpty}
            readOnly={readOnly}
            showCoverOption={showCoverOption}
            suggestions={suggestions}
            onRename={handleRename}
            onChangeTag={handleChangeTag}
            onRequestDelete={(id) => setPendingDeleteId(id)}
            onSetCover={(id) => setCover.mutate(id)}
            onToggleFavorite={(id) => toggleFavorite.mutate(id)}
            isUpdatingId={
                update.isPending ? (update.variables?.id ?? null) : null
            }
            isDeletingId={
                remove.isPending
                    ? ((remove.variables as number | undefined) ?? null)
                    : null
            }
            isTogglingFavoriteId={
                toggleFavorite.isPending
                    ? ((toggleFavorite.variables as number | undefined) ?? null)
                    : null
            }
            emptyHint={
                variant === 'drop-overlay'
                    ? 'Arraste arquivos para cá para anexar.'
                    : undefined
            }
            selectable={isSelectionMode}
            selectedIds={selectedIds}
            onSelect={handleSelect}
            onPreview={(id) => setPreviewId(id)}
        />
    );

    const dropzoneBlock = !readOnly && (
        <UploadDropzone
            onDrop={handleUpload}
            accept={accept}
            maxSizeMb={maxSize}
            maxFiles={maxFiles}
        />
    );

    const bulkBar = isSelectionMode && hasSelection && (
        <BulkActionBar
            count={selectedIds.size}
            total={items.length}
            onSelectAll={handleSelectAll}
            onClearSelection={handleClearSelection}
            onBulkDelete={() => setBulkDeleteOpen(true)}
            onBulkDownload={handleBulkDownload}
            isDeleting={bulkDelete.isPending}
            isZipping={isZipping}
        />
    );

    const scrollableListBlock = (
        <>
            <AttachmentsScrollArea maxHeight={listMaxHeight}>
                {isSelectionMode && (
                    <p className="text-xs text-muted-foreground">
                        A seleção inclui apenas os arquivos desta página.
                    </p>
                )}
                {bulkBar}
                {queueBlock}
                {listBlock}
            </AttachmentsScrollArea>
            {list.isSuccess && (
                <div
                    className="flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-xs text-muted-foreground"
                    aria-live="polite"
                >
                    <span>
                        Página {page} de {lastPage} · {total} arquivo
                        {total !== 1 ? 's' : ''}
                    </span>
                    <div className="flex gap-1">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            aria-label="Página anterior"
                            disabled={page <= 1 || list.isFetching}
                            onClick={() => changePage(page - 1)}
                        >
                            <ChevronLeft className="size-3.5" /> Anterior
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            aria-label="Próxima página"
                            disabled={page >= lastPage || list.isFetching}
                            onClick={() => changePage(page + 1)}
                        >
                            Próxima <ChevronRight className="size-3.5" />
                        </Button>
                    </div>
                    {total > 200 && (
                        <p className="w-full">
                            O ZIP aceita até 200 arquivos. Refine a busca para
                            baixar todos os resultados.
                        </p>
                    )}
                </div>
            )}
        </>
    );

    return (
        <div
            className={cn(
                'rounded-xl border bg-card',
                variant === 'drop-overlay' && 'overflow-hidden',
                className,
            )}
        >
            {showHeader && (
                <div className="flex items-start justify-between gap-4 border-b px-4 py-3">
                    <div className="min-w-0">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                            <Paperclip className="size-4 text-muted-foreground" />
                            {title}
                            {total > 0 && (
                                <span className="rounded-full bg-muted px-2 py-0.5 text-2xs font-medium text-muted-foreground">
                                    {total}
                                </span>
                            )}
                        </h3>
                        {description && (
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-1.5">
                        {queue.hasActive && (
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Loader2 className="size-3.5 animate-spin" />
                                Enviando {queue.activeCount}
                                {queue.items.length > queue.activeCount
                                    ? `/${queue.items.length}`
                                    : ''}
                                …
                            </div>
                        )}

                        {total > 0 && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7 text-muted-foreground"
                                onClick={handleDownloadAll}
                                disabled={
                                    isZipping ||
                                    list.isFetching ||
                                    list.isError ||
                                    total > 200
                                }
                                title={downloadAllLabel}
                                aria-label={downloadAllLabel}
                            >
                                {isZipping ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Archive className="size-3.5" />
                                )}
                            </Button>
                        )}

                        {!readOnly && rawItems.length > 0 && (
                            <Button
                                type="button"
                                size="icon"
                                variant={
                                    isSelectionMode ? 'secondary' : 'ghost'
                                }
                                className="size-7 text-muted-foreground"
                                onClick={() =>
                                    isSelectionMode
                                        ? exitSelectionMode()
                                        : setSelectionMode(true)
                                }
                                title={
                                    isSelectionMode
                                        ? 'Cancelar seleção'
                                        : 'Selecionar arquivos'
                                }
                            >
                                {isSelectionMode ? (
                                    <X className="size-3.5" />
                                ) : (
                                    <CheckSquare className="size-3.5" />
                                )}
                            </Button>
                        )}

                        {showHeaderActionButton && variant === 'modal' && (
                            <Dialog
                                open={isPickerOpen}
                                onOpenChange={setPickerOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="gap-1.5"
                                    >
                                        <Plus className="size-3.5" />
                                        {attachLabel}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle className="flex items-center gap-2 text-base">
                                            <UploadIcon className="size-4 text-muted-foreground" />
                                            {attachLabel}
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div className="grid-cols-1 grid-rows-1">
                                        {dropzoneBlock}
                                    </div>
                                </DialogContent>
                            </Dialog>
                        )}

                        {showHeaderActionButton && variant === 'toggle' && (
                            <Button
                                type="button"
                                size="sm"
                                variant={isToggleOpen ? 'secondary' : 'outline'}
                                className="gap-1.5"
                                onClick={() => setToggleOpen((v) => !v)}
                            >
                                {isToggleOpen ? (
                                    <X className="size-3.5" />
                                ) : (
                                    <Plus className="size-3.5" />
                                )}
                                {isToggleOpen ? 'Fechar' : attachLabel}
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {variant === 'drop-overlay' ? (
                <DropOverlay
                    onDrop={handleUpload}
                    accept={accept}
                    maxSizeMb={maxSize}
                    maxFiles={maxFiles}
                    disabled={readOnly}
                    overlayText={`Solte para ${attachLabel.toLowerCase()}`}
                >
                    <div className="space-y-3 p-4">
                        <Input
                            placeholder="Buscar por nome, tag ou extensão…"
                            value={search}
                            onChange={(e) => changeSearch(e.target.value)}
                            className="h-9"
                        />
                        {scrollableListBlock}
                    </div>
                </DropOverlay>
            ) : (
                <div className="space-y-3 p-4">
                    <Input
                        placeholder="Buscar por nome, tag ou extensão…"
                        value={search}
                        onChange={(e) => changeSearch(e.target.value)}
                        className="h-9"
                    />
                    {variant === 'inline' && dropzoneBlock}

                    {variant === 'toggle' && !readOnly && (
                        <Collapsible
                            open={isToggleOpen}
                            onOpenChange={setToggleOpen}
                        >
                            <CollapsibleContent>
                                <div className="pb-3">{dropzoneBlock}</div>
                            </CollapsibleContent>
                        </Collapsible>
                    )}

                    {!showHeader &&
                        showHeaderActionButton &&
                        variant === 'toggle' && (
                            <ToggleTriggerInline
                                open={isToggleOpen}
                                onToggle={() => setToggleOpen((v) => !v)}
                                label={attachLabel}
                            />
                        )}

                    {scrollableListBlock}
                </div>
            )}

            <DeleteConfirmModal
                isOpen={pendingDeleteId !== null}
                onClose={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
                isLoading={remove.isPending}
                title="Excluir arquivo"
                description="Esta ação não pode ser desfeita. O arquivo será removido permanentemente."
            />

            <DeleteConfirmModal
                isOpen={bulkDeleteOpen}
                onClose={() => setBulkDeleteOpen(false)}
                onConfirm={confirmBulkDelete}
                isLoading={bulkDelete.isPending}
                title={`Excluir ${selectedIds.size} arquivo${selectedIds.size !== 1 ? 's' : ''}`}
                description="Esta ação não pode ser desfeita. Os arquivos selecionados serão removidos permanentemente."
            />

            <UploadQuickPreview
                open={previewUpload !== null}
                upload={previewUpload}
                canGoPrev={previewIndex > 0}
                canGoNext={
                    previewIndex >= 0 && previewIndex < rawItems.length - 1
                }
                onOpenChange={(next) => {
                    if (!next) {
                        setPreviewId(null);
                    }
                }}
                onPrev={() => {
                    if (previewIndex <= 0) {
                        return;
                    }

                    setPreviewId(rawItems[previewIndex - 1]?.id ?? null);
                }}
                onNext={() => {
                    if (
                        previewIndex < 0 ||
                        previewIndex >= rawItems.length - 1
                    ) {
                        return;
                    }

                    setPreviewId(rawItems[previewIndex + 1]?.id ?? null);
                }}
                onToggleFavorite={(id) => toggleFavorite.mutate(id)}
                isTogglingFavorite={toggleFavorite.isPending}
            />
        </div>
    );
}

interface UploadQueueBlockProps {
    items: UploadTask[];
    onCancel: (id: string) => void;
    onRetry: (id: string) => void;
    hasErrors: boolean;
    onClearErrors: () => void;
}

function AttachmentsScrollArea({
    children,
    maxHeight,
}: {
    children: ReactNode;
    maxHeight: string | number;
}) {
    return (
        <div
            className="min-h-0 overflow-y-auto overscroll-contain pr-1"
            style={{ maxHeight }}
        >
            <div className="space-y-3">{children}</div>
        </div>
    );
}

function UploadQueueBlock({
    items,
    onCancel,
    onRetry,
    hasErrors,
    onClearErrors,
}: UploadQueueBlockProps) {
    return (
        <div className="space-y-2">
            {hasErrors && (
                <div className="flex items-center justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-7 gap-1 text-xs text-muted-foreground hover:text-foreground"
                        onClick={onClearErrors}
                    >
                        <X className="size-3" />
                        Limpar erros
                    </Button>
                </div>
            )}
            {items.map((task) => (
                <UploadQueueItem
                    key={task.id}
                    task={task}
                    onCancel={onCancel}
                    onRetry={onRetry}
                />
            ))}
        </div>
    );
}

interface AttachmentsListBlockProps {
    items: Upload[];
    isLoading: boolean;
    isEmpty: boolean;
    readOnly: boolean;
    showCoverOption: boolean;
    suggestions: TagSuggestion[];
    onRename: (id: number, name: string) => void;
    onChangeTag: (id: number, tag: string | null) => void;
    onRequestDelete: (id: number) => void;
    onSetCover: (id: number) => void;
    onToggleFavorite: (id: number) => void;
    isUpdatingId: number | null;
    isDeletingId: number | null;
    isTogglingFavoriteId: number | null;
    emptyHint?: string;
    selectable?: boolean;
    selectedIds?: Set<number>;
    onSelect?: (id: number, checked: boolean) => void;
    onPreview?: (id: number) => void;
}

function AttachmentsListBlock({
    items,
    isLoading,
    isEmpty,
    readOnly,
    showCoverOption,
    suggestions,
    onRename,
    onChangeTag,
    onRequestDelete,
    onSetCover,
    onToggleFavorite,
    isUpdatingId,
    isDeletingId,
    isTogglingFavoriteId,
    emptyHint,
    selectable = false,
    selectedIds = new Set(),
    onSelect,
    onPreview,
}: AttachmentsListBlockProps) {
    if (isLoading) {
        return (
            <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                Carregando arquivos…
            </div>
        );
    }

    if (isEmpty) {
        return (
            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-8 text-center text-sm text-muted-foreground">
                <FileX className="size-6" />
                Nenhum arquivo anexado ainda.
                {emptyHint && (
                    <span className="text-2xs text-muted-foreground/80">
                        {emptyHint}
                    </span>
                )}
            </div>
        );
    }

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {items.map((item) => (
                <UploadItem
                    key={item.id}
                    upload={item}
                    suggestions={suggestions}
                    readOnly={readOnly}
                    showCoverOption={showCoverOption}
                    onRename={onRename}
                    onChangeTag={onChangeTag}
                    onDelete={onRequestDelete}
                    onRequestDelete={onRequestDelete}
                    onSetCover={onSetCover}
                    onToggleFavorite={onToggleFavorite}
                    isUpdating={isUpdatingId === item.id}
                    isDeleting={isDeletingId === item.id}
                    isTogglingFavorite={isTogglingFavoriteId === item.id}
                    selectable={selectable}
                    selected={selectedIds.has(item.id)}
                    onSelect={onSelect}
                    onPreview={onPreview}
                />
            ))}
        </div>
    );
}

interface ToggleTriggerInlineProps {
    open: boolean;
    onToggle: () => void;
    label: string;
}

function ToggleTriggerInline({
    open,
    onToggle,
    label,
}: ToggleTriggerInlineProps) {
    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            className="w-full justify-between"
            onClick={onToggle}
        >
            <span className="inline-flex items-center gap-1.5">
                <Plus className="size-3.5" />
                {label}
            </span>
            <ChevronDown
                className={cn(
                    'size-3.5 transition-transform',
                    open && 'rotate-180',
                )}
            />
        </Button>
    );
}

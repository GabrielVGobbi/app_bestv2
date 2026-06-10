import {
    File,
    FileText,
    Loader2,
    Paperclip,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import { useRef, useState } from 'react';

import { DeleteConfirmModal } from '@/components/delete-confirm-modal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Upload } from '@/modules/uploads/api/types';
import { TagCombobox } from '@/modules/uploads/components/tag-combobox';
import { UploadQuickPreview } from '@/modules/uploads/components/upload-quick-preview';
import { useUploads } from '@/modules/uploads/hooks/use-uploads';

interface TimelineCommentAttachmentsProps {
    commentUuid: string;
    initialAttachments: Upload[];
    readOnly?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    showTrigger?: boolean;
}

export function TimelineCommentAttachments({
    commentUuid,
    initialAttachments,
    readOnly = false,
    open,
    onOpenChange,
    showTrigger = true,
}: TimelineCommentAttachmentsProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const uploads = useUploads('timeline-comment', commentUuid, {
        collection: 'attachments',
    });
    const [internalOpen, setInternalOpen] = useState(false);
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);

    const isOpen = open ?? internalOpen;
    const setOpen = onOpenChange ?? setInternalOpen;
    const items = uploads.list.data?.data ?? initialAttachments;
    const suggestions = uploads.tags.data?.data ?? [];
    const uploadCount = items.length;
    const isUploading = uploads.upload.isPending;
    const previewIndex =
        previewId === null
            ? -1
            : items.findIndex((item) => item.id === previewId);
    const previewUpload = previewIndex >= 0 ? items[previewIndex] : null;

    function handleFiles(files: FileList | null) {
        if (!files || files.length === 0 || readOnly) {
            return;
        }

        uploads.upload.mutate(
            {
                files: Array.from(files),
                collection: 'attachments',
            },
            {
                onSuccess: () => setOpen(false),
            },
        );
    }

    function confirmDelete() {
        if (pendingDeleteId === null) {
            return;
        }

        uploads.remove.mutate(pendingDeleteId, {
            onSettled: () => setPendingDeleteId(null),
        });
    }

    if (readOnly && uploadCount === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                {!readOnly && showTrigger && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 text-muted-foreground hover:text-foreground"
                        onClick={() => setOpen(true)}
                        title={
                            uploadCount > 0
                                ? 'Gerenciar anexos'
                                : 'Anexar arquivo'
                        }
                        aria-label={
                            uploadCount > 0
                                ? 'Gerenciar anexos'
                                : 'Anexar arquivo'
                        }
                    >
                        <Paperclip className="size-4" />
                    </Button>
                )}

                {uploadCount > 0 && (
                    <ul className="flex min-w-0 flex-wrap gap-1.5 text-xs">
                        {items.map((upload) => (
                            <li key={upload.id} className="min-w-0">
                                <button
                                    type="button"
                                    className="flex max-w-full items-center gap-1.5 rounded-full border border-border/70 bg-muted/30 px-2 py-1 text-left text-muted-foreground transition-colors hover:border-primary/30 hover:bg-primary/5 hover:text-primary"
                                    onClick={() => setPreviewId(upload.id)}
                                    title={upload.name}
                                >
                                    <FileText className="size-3.5 shrink-0" />
                                    <span className="truncate">
                                        {upload.name}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Dialog open={isOpen} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <Paperclip className="size-4 text-muted-foreground" />
                            Anexos do comentario
                        </DialogTitle>
                        <DialogDescription>
                            Envie arquivos e ajuste os dados exibidos na
                            timeline.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {!readOnly && (
                            <div className="flex items-center justify-between gap-3 rounded-md border border-dashed border-border/80 bg-muted/20 px-3 py-2">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        Adicionar arquivo
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        O anexo fica vinculado a este
                                        comentario.
                                    </p>
                                </div>
                                <input
                                    ref={inputRef}
                                    type="file"
                                    multiple
                                    className="hidden"
                                    onChange={(event) => {
                                        handleFiles(event.target.files);
                                        event.target.value = '';
                                    }}
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={() => inputRef.current?.click()}
                                    disabled={isUploading}
                                >
                                    {isUploading ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <UploadCloud className="size-4" />
                                    )}
                                    Anexar
                                </Button>
                            </div>
                        )}

                        <div className="max-h-[360px] space-y-2 overflow-y-auto pr-1">
                            {uploads.list.isLoading ? (
                                <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                    Carregando anexos...
                                </div>
                            ) : uploadCount === 0 ? (
                                <div className="rounded-md border border-dashed border-border/80 bg-muted/20 py-6 text-center text-sm text-muted-foreground">
                                    Nenhum anexo neste comentario.
                                </div>
                            ) : (
                                items.map((upload) => (
                                    <TimelineAttachmentEditor
                                        key={upload.id}
                                        upload={upload}
                                        suggestions={suggestions}
                                        readOnly={readOnly}
                                        isUpdating={
                                            uploads.update.isPending &&
                                            uploads.update.variables?.id ===
                                                upload.id
                                        }
                                        onPreview={() =>
                                            setPreviewId(upload.id)
                                        }
                                        onRename={(name) =>
                                            uploads.update.mutate({
                                                id: upload.id,
                                                data: { name },
                                            })
                                        }
                                        onChangeTag={(tag) =>
                                            uploads.update.mutate({
                                                id: upload.id,
                                                data: { tag },
                                            })
                                        }
                                        onDelete={() =>
                                            setPendingDeleteId(upload.id)
                                        }
                                    />
                                ))
                            )}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <UploadQuickPreview
                open={previewUpload !== null}
                upload={previewUpload}
                canGoPrev={previewIndex > 0}
                canGoNext={previewIndex >= 0 && previewIndex < items.length - 1}
                onOpenChange={(next) => {
                    if (!next) {
                        setPreviewId(null);
                    }
                }}
                onPrev={() => {
                    if (previewIndex <= 0) {
                        return;
                    }

                    setPreviewId(items[previewIndex - 1]?.id ?? null);
                }}
                onNext={() => {
                    if (previewIndex < 0 || previewIndex >= items.length - 1) {
                        return;
                    }

                    setPreviewId(items[previewIndex + 1]?.id ?? null);
                }}
            />

            <DeleteConfirmModal
                isOpen={pendingDeleteId !== null}
                onClose={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
                isLoading={uploads.remove.isPending}
                title="Excluir anexo"
                description="Esta acao remove o arquivo deste comentario."
            />
        </div>
    );
}

interface TimelineAttachmentEditorProps {
    upload: Upload;
    suggestions: Array<{ value: string; label: string }>;
    readOnly: boolean;
    isUpdating: boolean;
    onPreview: () => void;
    onRename: (name: string) => void;
    onChangeTag: (tag: string | null) => void;
    onDelete: () => void;
}

function TimelineAttachmentEditor({
    upload,
    suggestions,
    readOnly,
    isUpdating,
    onPreview,
    onRename,
    onChangeTag,
    onDelete,
}: TimelineAttachmentEditorProps) {
    const [name, setName] = useState(upload.name);

    function commitName() {
        const next = name.trim();

        if (!next || next === upload.name) {
            setName(upload.name);

            return;
        }

        onRename(next);
    }

    return (
        <div className="rounded-md border border-border/70 bg-background/80 px-3 py-2">
            <div className="flex items-start gap-3">
                <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted/70">
                    <File className="size-4 text-muted-foreground" />
                </div>
                <div className="min-w-0 flex-1 space-y-2">
                    <button
                        type="button"
                        className="block max-w-full truncate text-left text-sm font-medium text-primary underline-offset-4 hover:underline"
                        onClick={onPreview}
                        title={upload.name}
                    >
                        {upload.name}
                    </button>
                    <p className="text-xs text-muted-foreground">
                        {upload.size_human}
                        {upload.extension
                            ? ` · ${upload.extension.toUpperCase()}`
                            : ''}
                    </p>

                    {!readOnly && upload.permissions.can_update && (
                        <div className="grid gap-2 sm:grid-cols-1">
                            <Input
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                onBlur={commitName}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.currentTarget.blur();
                                    }
                                }}
                                disabled={isUpdating}
                                aria-label={`Nome do anexo ${upload.name}`}
                                className="h-8 text-xs"
                            />
                            <TagCombobox
                                value={upload.tag}
                                suggestions={suggestions}
                                onChange={onChangeTag}
                                disabled={isUpdating}
                                size="sm"
                                placeholder="Tag"
                            />
                        </div>
                    )}
                </div>

                {!readOnly && upload.permissions.can_delete && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={cn(
                            'size-7 shrink-0 text-muted-foreground hover:text-destructive',
                            isUpdating && 'pointer-events-none opacity-50',
                        )}
                        onClick={onDelete}
                        title="Excluir anexo"
                        aria-label={`Excluir anexo ${upload.name}`}
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                )}
            </div>
        </div>
    );
}

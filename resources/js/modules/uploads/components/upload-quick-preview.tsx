import {
    ChevronLeft,
    ChevronRight,
    Download,
    File,
    FileSpreadsheet,
    FileText,
    Image as ImageIcon,
    Loader2,
    Music,
    Star,
    Video,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

import type { Upload } from '../api/types';

interface UploadQuickPreviewProps {
    open: boolean;
    upload: Upload | null;
    canGoPrev: boolean;
    canGoNext: boolean;
    onOpenChange: (open: boolean) => void;
    onPrev: () => void;
    onNext: () => void;
    onToggleFavorite?: (id: number) => void;
    isTogglingFavorite?: boolean;
}

const ICON_MAP: Record<string, LucideIcon> = {
    image: ImageIcon,
    video: Video,
    music: Music,
    'file-text': FileText,
    'file-spreadsheet': FileSpreadsheet,
    file: File,
};

function isPdf(upload: Upload): boolean {
    const ext = (upload.extension ?? '').toLowerCase();

    return upload.mime_type === 'application/pdf' || ext === 'pdf';
}

function isVideo(upload: Upload): boolean {
    return upload.mime_type.startsWith('video/');
}

function isAudio(upload: Upload): boolean {
    return upload.mime_type.startsWith('audio/');
}

export function UploadQuickPreview({
    open,
    upload,
    canGoPrev,
    canGoNext,
    onOpenChange,
    onPrev,
    onNext,
    onToggleFavorite,
    isTogglingFavorite = false,
}: UploadQuickPreviewProps) {
    const Icon = upload ? (ICON_MAP[upload.icon] ?? File) : File;
    const previewUrl = upload?.preview_url || upload?.url || '#';
    const downloadUrl = upload?.download_url || upload?.url || '#';

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (target) {
                const tag = target.tagName;
                const isTypingField =
                    tag === 'INPUT' ||
                    tag === 'TEXTAREA' ||
                    tag === 'SELECT' ||
                    target.isContentEditable;

                if (isTypingField) {
                    return;
                }
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                onOpenChange(false);

                return;
            }

            if (event.key === 'ArrowLeft' && canGoPrev) {
                event.preventDefault();
                onPrev();

                return;
            }

            if (event.key === 'ArrowRight' && canGoNext) {
                event.preventDefault();
                onNext();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, canGoPrev, canGoNext, onOpenChange, onPrev, onNext]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="h-screen w-screen !max-w-none translate-x-[-50%] translate-y-[-50%] overflow-hidden rounded-none border-0 p-0 shadow-none">
                <div className="flex h-full min-h-0 flex-col">
                    <DialogHeader className="shrink-0 border-b px-4 py-3 pr-14">
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <DialogTitle className="truncate text-sm font-semibold">
                                    {upload?.name ?? 'Visualização rápida'}
                                </DialogTitle>
                                <DialogDescription className="mt-1 text-xs">
                                    {upload
                                        ? `${upload.size_human} ${upload.extension ? `· ${upload.extension.toUpperCase()}` : ''}`
                                        : 'Abra um anexo para visualizar'}
                                </DialogDescription>
                            </div>

                            <div className="flex items-center gap-1 pr-2">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    onClick={onPrev}
                                    disabled={!canGoPrev}
                                    title="Arquivo anterior"
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    onClick={onNext}
                                    disabled={!canGoNext}
                                    title="Próximo arquivo"
                                >
                                    <ChevronRight className="size-4" />
                                </Button>

                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className={cn(
                                        'size-8',
                                        upload?.is_favorite &&
                                            'text-warning-soft-foreground hover:text-warning-soft-foreground',
                                    )}
                                    disabled={
                                        !upload ||
                                        isTogglingFavorite ||
                                        !onToggleFavorite
                                    }
                                    title={
                                        upload?.is_favorite
                                            ? 'Remover dos favoritos'
                                            : 'Adicionar aos favoritos'
                                    }
                                    onClick={() => {
                                        if (upload) {
                                            onToggleFavorite?.(upload.id);
                                        }
                                    }}
                                >
                                    {isTogglingFavorite ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Star
                                            className={cn(
                                                'size-4',
                                                upload?.is_favorite &&
                                                    'fill-current',
                                            )}
                                        />
                                    )}
                                </Button>

                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    asChild
                                >
                                    <a
                                        href={downloadUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        title="Baixar arquivo"
                                        aria-disabled={!upload}
                                    >
                                        <Download className="size-4" />
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="flex min-h-0 max-w-full flex-1 items-center justify-center bg-muted/30 p-3 md:p-4">
                        {!upload ? (
                            <div className="text-sm text-muted-foreground">
                                Nenhum arquivo selecionado.
                            </div>
                        ) : upload.is_image ? (
                            <img
                                src={previewUrl}
                                alt={upload.name}
                                className="max-h-full max-w-full rounded-md object-contain shadow-sm"
                            />
                        ) : isPdf(upload) ? (
                            <div className="h-full w-full overflow-auto rounded-md border bg-background">
                                <iframe
                                    title={upload.name}
                                    src={previewUrl}
                                    className="h-full min-h-[70vh] w-full"
                                />
                            </div>
                        ) : isVideo(upload) ? (
                            <video
                                src={previewUrl}
                                controls
                                className="h-full max-h-full w-full rounded-md bg-black object-contain"
                            />
                        ) : isAudio(upload) ? (
                            <div className="flex w-full max-w-xl flex-col items-center gap-4 rounded-lg border bg-background p-6">
                                <Icon className="size-10 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    Prévia de áudio
                                </p>
                                <audio
                                    src={previewUrl}
                                    controls
                                    className="w-full"
                                />
                            </div>
                        ) : (
                            <div className="flex w-full max-w-xl flex-col items-center gap-3 rounded-lg border bg-background p-8 text-center">
                                <div className="flex size-16 items-center justify-center rounded-full bg-muted">
                                    <Icon className="size-8 text-muted-foreground" />
                                </div>
                                <p className="text-sm font-medium">
                                    {upload.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Pré-visualização não suportada para este
                                    tipo de arquivo.
                                </p>
                                <Button asChild>
                                    <a
                                        href={downloadUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Download
                                            className={cn('mr-1.5 size-4')}
                                        />
                                        Baixar arquivo
                                    </a>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

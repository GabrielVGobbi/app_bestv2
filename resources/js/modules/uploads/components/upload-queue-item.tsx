import {
    AlertCircle,
    CheckCircle2,
    File as FileIcon,
    FileSpreadsheet,
    FileText,
    Image as ImageIcon,
    Loader2,
    Music,
    RotateCcw,
    Video,
    X,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import type { UploadTask } from '../hooks/use-upload-queue';

interface UploadQueueItemProps {
    task: UploadTask;
    onCancel: (id: string) => void;
    onRetry: (id: string) => void;
}

const UNITS = ['B', 'KB', 'MB', 'GB'] as const;

function formatSize(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < UNITS.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${UNITS[unit]}`;
}

function FileTypeIcon({ mime }: { mime: string }) {
    const className = 'size-4 text-muted-foreground';

    if (mime.startsWith('image/')) {
        return <ImageIcon className={className} />;
    }

    if (mime.startsWith('video/')) {
        return <Video className={className} />;
    }

    if (mime.startsWith('audio/')) {
        return <Music className={className} />;
    }

    if (
        mime.includes('pdf') ||
        mime.includes('word') ||
        mime.includes('text')
    ) {
        return <FileText className={className} />;
    }

    if (
        mime.includes('sheet') ||
        mime.includes('excel') ||
        mime.includes('csv')
    ) {
        return <FileSpreadsheet className={className} />;
    }

    return <FileIcon className={className} />;
}

export function UploadQueueItem({
    task,
    onCancel,
    onRetry,
}: UploadQueueItemProps) {
    const isUploading = task.status === 'uploading';
    const isPending = task.status === 'pending';
    const isError = task.status === 'error';
    const isSuccess = task.status === 'success';

    const progress = isSuccess
        ? 100
        : isUploading
          ? task.progress
          : isPending
            ? 5
            : 0;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-lg border bg-card px-3 py-2 text-sm transition-colors',
                isError && 'border-destructive/40 bg-destructive/5',
                isSuccess &&
                    'border-success-soft-foreground/20 bg-success-soft',
            )}
            aria-live="polite"
        >
            <div
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-md bg-muted',
                    isError && 'bg-destructive/10 text-destructive',
                    isSuccess && 'bg-success-soft text-success-soft-foreground',
                )}
            >
                {isError ? (
                    <AlertCircle className="size-4" />
                ) : isSuccess ? (
                    <CheckCircle2 className="size-4" />
                ) : (
                    <FileTypeIcon mime={task.file.type} />
                )}
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex min-w-0 items-center justify-between gap-2">
                    <p
                        className="truncate text-sm font-medium"
                        title={task.file.name}
                    >
                        {task.file.name}
                    </p>
                    <span className="shrink-0 text-2xs text-muted-foreground tabular-nums">
                        {isUploading
                            ? `${task.progress}%`
                            : formatSize(task.file.size)}
                    </span>
                </div>

                {!isError ? (
                    <div
                        className={cn(
                            'h-1 w-full overflow-hidden rounded-full bg-muted',
                            isSuccess && 'bg-success-soft',
                        )}
                    >
                        <div
                            className={cn(
                                'h-full rounded-full transition-[width] duration-200 ease-out',
                                isSuccess
                                    ? 'bg-success'
                                    : isUploading
                                      ? 'bg-primary'
                                      : 'bg-primary/40',
                            )}
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                ) : (
                    <p
                        className="line-clamp-2 text-2xs text-destructive"
                        title={task.error}
                    >
                        {task.error ?? 'Falha ao enviar arquivo.'}
                    </p>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-0.5">
                {isError && (
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        onClick={() => onRetry(task.id)}
                        aria-label="Tentar novamente"
                        title="Tentar novamente"
                    >
                        <RotateCcw className="size-3.5" />
                    </Button>
                )}

                {(isUploading || isPending) && (
                    <Loader2 className="size-3.5 animate-spin text-muted-foreground" />
                )}

                {!isSuccess && (
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        onClick={() => onCancel(task.id)}
                        aria-label={isError ? 'Descartar' : 'Cancelar'}
                        title={isError ? 'Descartar' : 'Cancelar'}
                    >
                        <X className="size-3.5" />
                    </Button>
                )}
            </div>
        </div>
    );
}

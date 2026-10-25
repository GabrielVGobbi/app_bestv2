import { Link } from '@inertiajs/react';
import { Check, Download, ExternalLink, Loader2, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { StoredExport } from '@/types/export';

interface DataTableExportStatusProps {
    activeExports: StoredExport[];
    onDownload: (uuid: string, expectedDownloadUrl?: string) => boolean | void;
}

function ExportProgressBar({
    processed,
    total,
}: {
    processed: number;
    total: number | null;
}) {
    if (!total) {
        return (
            <div className="h-1 w-full animate-pulse rounded-full bg-muted" />
        );
    }

    const pct = Math.min(100, Math.round((processed / total) * 100));

    return (
        <div className="h-1 w-full overflow-hidden rounded-full bg-muted">
            <div
                className="h-full bg-primary transition-all duration-500"
                style={{ width: `${pct}%` }}
            />
        </div>
    );
}

function ExportStatusIcon({ status }: { status: StoredExport['status'] }) {
    if (status === 'completed') {
        return <Check className="size-3.5 shrink-0 text-success" />;
    }

    if (status === 'failed') {
        return <X className="size-3.5 shrink-0 text-destructive" />;
    }

    return <Loader2 className="size-3.5 shrink-0 animate-spin text-info" />;
}

export function DataTableExportStatus({
    activeExports,
    onDownload,
}: DataTableExportStatusProps) {
    if (activeExports.length === 0) {
        return null;
    }

    const inProgressCount = activeExports.filter(
        (e) => e.status === 'pending' || e.status === 'processing',
    ).length;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="relative gap-1.5"
                >
                    {inProgressCount > 0 ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <Download className="size-4" />
                    )}
                    <span>
                        {inProgressCount > 0
                            ? `${inProgressCount} exportação${inProgressCount > 1 ? 'ões' : ''} em andamento`
                            : 'Exportações'}
                    </span>
                    {inProgressCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-primary text-2xs text-primary-foreground">
                            {inProgressCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
                <div className="border-b px-3 py-2">
                    <p className="text-sm font-medium">Exportações recentes</p>
                </div>
                <div className="divide-y">
                    {activeExports.map((exp) => (
                        <div key={exp.uuid} className="space-y-1.5 px-3 py-2.5">
                            <div className="flex items-start gap-2">
                                <ExportStatusIcon status={exp.status} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {exp.file_name ?? 'Preparando...'}
                                    </p>
                                    <div className="flex items-center gap-1.5">
                                        <Badge
                                            variant="secondary"
                                            className="text-2xs"
                                        >
                                            {exp.format.toUpperCase()}
                                        </Badge>
                                        {exp.status === 'processing' &&
                                            exp.total_records && (
                                                <span className="text-2xs text-muted-foreground tabular-nums">
                                                    {exp.processed_records} /{' '}
                                                    {exp.total_records}{' '}
                                                    registros
                                                </span>
                                            )}
                                        {exp.status === 'failed' && (
                                            <span className="text-2xs text-destructive-soft-foreground">
                                                Falhou
                                            </span>
                                        )}
                                        {exp.status === 'completed' &&
                                            exp.total_records && (
                                                <span className="text-2xs text-success-soft-foreground tabular-nums">
                                                    {exp.total_records}{' '}
                                                    registros
                                                </span>
                                            )}
                                    </div>
                                </div>
                            </div>

                            {(exp.status === 'pending' ||
                                exp.status === 'processing') && (
                                <ExportProgressBar
                                    processed={exp.processed_records}
                                    total={exp.total_records}
                                />
                            )}

                            {exp.status === 'completed' && exp.download_url && (
                                <a
                                    href={exp.download_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                    onClick={(event) => {
                                        if (
                                            onDownload(
                                                exp.uuid,
                                                exp.download_url ?? undefined,
                                            ) === false
                                        ) {
                                            event.preventDefault();
                                        }
                                    }}
                                >
                                    <Download className="size-3" />
                                    Baixar arquivo
                                </a>
                            )}
                        </div>
                    ))}
                </div>
                <div className="border-t px-3 py-2">
                    <Link
                        href="/exportacoes"
                        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ExternalLink className="size-3" />
                        Ver histórico completo
                    </Link>
                </div>
            </PopoverContent>
        </Popover>
    );
}

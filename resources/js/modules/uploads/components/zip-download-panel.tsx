import { AlertCircle, Archive, CheckCircle2, Loader2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import { useZipPoller } from '../hooks/use-zip-poller';
import { useZipDownloadStore } from '../stores/zip-download-store';

/**
 * Global floating panel (bottom-right) that shows active and ready ZIP downloads.
 * Mount once in AppLayout. Internally runs the poller hook so it stays alive
 * across Inertia page navigations.
 */
export function ZipDownloadPanel() {
    useZipPoller();

    const jobs = useZipDownloadStore((s) => s.jobs);
    const removeJob = useZipDownloadStore((s) => s.removeJob);

    if (jobs.length === 0) {
        return null;
    }

    return (
        <div className="fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
            {jobs.map((job) => (
                <div
                    key={job.uuid}
                    className={cn(
                        'flex items-start gap-3 rounded-xl border bg-card p-3 shadow-lg transition-all',
                        job.status === 'ready' &&
                            'border-success-soft-foreground/20 bg-success-soft',
                        job.status === 'failed' &&
                            'border-destructive/30 bg-destructive/5',
                    )}
                >
                    {/* Icon */}
                    <div className="mt-0.5 shrink-0">
                        {(job.status === 'pending' ||
                            job.status === 'processing') && (
                            <Loader2 className="size-4 animate-spin text-muted-foreground" />
                        )}
                        {job.status === 'ready' && (
                            <CheckCircle2 className="size-4 text-success-soft-foreground" />
                        )}
                        {job.status === 'failed' && (
                            <AlertCircle className="size-4 text-destructive" />
                        )}
                    </div>

                    {/* Content */}
                    <div className="min-w-0 flex-1 space-y-1">
                        <p
                            className="truncate text-sm font-medium"
                            title={job.label}
                        >
                            {job.label}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {job.status === 'pending' && 'Aguardando na fila…'}
                            {job.status === 'processing' &&
                                `Compactando ${job.fileCount} arquivo(s)…`}
                            {job.status === 'ready' &&
                                'ZIP pronto para download.'}
                            {job.status === 'failed' &&
                                (job.errorMessage ?? 'Erro ao gerar ZIP.')}
                        </p>

                        {job.status === 'ready' && job.downloadUrl && (
                            <Button
                                size="sm"
                                className="mt-1 h-7 gap-1.5 text-xs"
                                onClick={() =>
                                    window.open(job.downloadUrl, '_self')
                                }
                            >
                                <Archive className="size-3.5" />
                                Baixar ZIP
                            </Button>
                        )}
                    </div>

                    {/* Dismiss: only for terminal states */}
                    {(job.status === 'ready' || job.status === 'failed') && (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-6 shrink-0 text-muted-foreground"
                            onClick={() => removeJob(job.uuid)}
                            aria-label="Fechar"
                        >
                            <X className="size-3.5" />
                        </Button>
                    )}
                </div>
            ))}
        </div>
    );
}

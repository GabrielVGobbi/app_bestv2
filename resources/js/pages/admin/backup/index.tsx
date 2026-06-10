import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Archive,
    CheckCircle2,
    Database,
    Download,
    Plus,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import {
    DatabaseExportDialog,
    DatabaseExportStatus,
    useDatabaseExport,
} from '@/components/database-export';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DatabaseExportType } from '@/types/database-export';

interface BackupHistoryItem {
    id: number;
    uuid: string;
    disk: string;
    path: string | null;
    file_name: string | null;
    file_size: number | null;
    status: 'completed' | 'failed';
    error_message: string | null;
    backed_up_at: string;
    created_at: string;
}

interface PaginatedBackups {
    data: BackupHistoryItem[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    backups: PaginatedBackups;
}

function formatBytes(bytes: number | null): string {
    if (!bytes) {
        return '-';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const exportTypeLabels: Record<DatabaseExportType, string> = {
    schema: 'Schema',
    data: 'Dados',
    full: 'Completo',
};

export default function BackupIndex({ backups }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Backups', href: '/admin/backups' }],
    });

    const [activeSection, setActiveSection] = useState<'backups' | 'dumps'>(
        'backups',
    );
    const [dialogOpen, setDialogOpen] = useState(false);

    const { activeExports, hasActiveExports, startExport, markAsDownloaded } =
        useDatabaseExport();

    return (
        <>
            <Head title="Backups" />

            <div className="page-wrapper space-y-6 py-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Backups & Dumps
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Histórico de backups automáticos e exportações de
                            banco sob demanda.
                        </p>
                    </div>

                    {activeSection === 'dumps' && (
                        <div className="flex items-center gap-2">
                            {activeExports.length > 0 && (
                                <DatabaseExportStatus
                                    activeExports={activeExports}
                                    onDownload={markAsDownloaded}
                                />
                            )}
                            <Button
                                size="sm"
                                onClick={() => setDialogOpen(true)}
                            >
                                <Plus className="mr-1.5 size-4" />
                                Gerar Dump
                            </Button>
                        </div>
                    )}
                </div>

                {/* Section tabs (manual, no Tabs component available) */}
                <div className="flex border-b">
                    <button
                        type="button"
                        onClick={() => setActiveSection('backups')}
                        className={`px-4 py-2 text-sm font-medium transition-colors ${
                            activeSection === 'backups'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <span className="flex items-center gap-1.5">
                            <Archive className="size-3.5" />
                            Backups Automáticos
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveSection('dumps')}
                        className={`px-4 py-2 text-sm font-medium transition-colors ${
                            activeSection === 'dumps'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <span className="flex items-center gap-1.5">
                            <Database className="size-3.5" />
                            Dumps de Banco
                            {hasActiveExports && (
                                <span className="flex size-4 items-center justify-center rounded-full bg-primary text-[10px] text-white">
                                    {
                                        activeExports.filter(
                                            (e) =>
                                                e.status === 'pending' ||
                                                e.status === 'processing',
                                        ).length
                                    }
                                </span>
                            )}
                        </span>
                    </button>
                </div>

                {/* Backups tab */}
                {activeSection === 'backups' && (
                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Backups automáticos executados diariamente às 02:00.
                            Configurado via{' '}
                            <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                spatie/laravel-backup
                            </code>
                            .
                        </p>

                        {backups.data.length === 0 ? (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <Archive className="mb-3 size-10 text-muted-foreground/40" />
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum backup registrado ainda.
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Execute{' '}
                                        <code className="rounded bg-muted px-1 py-0.5">
                                            php artisan backup:run
                                        </code>{' '}
                                        para testar.
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card>
                                <CardContent className="p-0">
                                    <div className="divide-y">
                                        {backups.data.map((backup) => (
                                            <div
                                                key={backup.uuid}
                                                className="flex items-center justify-between px-4 py-3"
                                            >
                                                <div className="flex items-center gap-3">
                                                    {backup.status ===
                                                    'completed' ? (
                                                        <CheckCircle2 className="size-4 shrink-0 text-green-500" />
                                                    ) : (
                                                        <XCircle className="size-4 shrink-0 text-red-500" />
                                                    )}
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {backup.file_name ??
                                                                backup.path ??
                                                                'Backup'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatDate(
                                                                backup.backed_up_at,
                                                            )}
                                                            {backup.file_size && (
                                                                <>
                                                                    {' '}
                                                                    ·{' '}
                                                                    {formatBytes(
                                                                        backup.file_size,
                                                                    )}
                                                                </>
                                                            )}
                                                            {backup.error_message && (
                                                                <>
                                                                    {' '}
                                                                    ·{' '}
                                                                    <span className="text-red-500">
                                                                        {
                                                                            backup.error_message
                                                                        }
                                                                    </span>
                                                                </>
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            backup.status ===
                                                            'completed'
                                                                ? 'secondary'
                                                                : 'destructive'
                                                        }
                                                    >
                                                        {backup.status ===
                                                        'completed'
                                                            ? 'Concluído'
                                                            : 'Falhou'}
                                                    </Badge>
                                                    {backup.status ===
                                                        'completed' &&
                                                        backup.path && (
                                                            <a
                                                                href={`/admin/backups/${backup.uuid}/download`}
                                                                className="text-muted-foreground hover:text-foreground"
                                                                title="Baixar backup"
                                                            >
                                                                <Download className="size-4" />
                                                            </a>
                                                        )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Dumps tab */}
                {activeSection === 'dumps' && (
                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Gere dumps SQL sob demanda. O arquivo é processado
                            em background e fica disponível para download por
                            24h.
                        </p>

                        {activeExports.length === 0 ? (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <Database className="mb-3 size-10 text-muted-foreground/40" />
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum dump gerado nesta sessão.
                                    </p>
                                    <Button
                                        size="sm"
                                        className="mt-4"
                                        onClick={() => setDialogOpen(true)}
                                    >
                                        <Plus className="mr-1.5 size-4" />
                                        Gerar primeiro dump
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Dumps desta sessão
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="divide-y">
                                        {activeExports.map((exp) => (
                                            <div
                                                key={exp.uuid}
                                                className="flex items-center justify-between px-4 py-3"
                                            >
                                                <div className="flex items-center gap-3">
                                                    {exp.status ===
                                                    'completed' ? (
                                                        <CheckCircle2 className="size-4 shrink-0 text-green-500" />
                                                    ) : exp.status ===
                                                      'failed' ? (
                                                        <XCircle className="size-4 shrink-0 text-red-500" />
                                                    ) : (
                                                        <div className="size-4 shrink-0 animate-pulse rounded-full bg-blue-400" />
                                                    )}
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {exp.file_name ??
                                                                'Gerando...'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Tipo:{' '}
                                                            {
                                                                exportTypeLabels[
                                                                    exp
                                                                        .export_type
                                                                ]
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            exp.status ===
                                                            'completed'
                                                                ? 'secondary'
                                                                : exp.status ===
                                                                    'failed'
                                                                  ? 'destructive'
                                                                  : 'outline'
                                                        }
                                                    >
                                                        {exp.status ===
                                                        'completed'
                                                            ? 'Pronto'
                                                            : exp.status ===
                                                                'failed'
                                                              ? 'Falhou'
                                                              : 'Em andamento'}
                                                    </Badge>
                                                    {exp.status ===
                                                        'completed' &&
                                                        exp.download_url && (
                                                            <a
                                                                href={
                                                                    exp.download_url
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-muted-foreground hover:text-foreground"
                                                                title="Baixar dump"
                                                                onClick={() =>
                                                                    markAsDownloaded(
                                                                        exp.uuid,
                                                                    )
                                                                }
                                                            >
                                                                <Download className="size-4" />
                                                            </a>
                                                        )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>

            <DatabaseExportDialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                onStartExport={startExport}
                onExportStarted={() => setActiveSection('dumps')}
            />
        </>
    );
}

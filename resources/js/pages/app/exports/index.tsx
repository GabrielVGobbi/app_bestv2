import { Head, setLayoutProps } from '@inertiajs/react';
import { Check, Clock, Download, FileText, Loader2, X } from 'lucide-react';

import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';
import type { ExportRecord } from '@/types/export';

interface PageProps {
    exports: ExportRecord[];
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

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatModelClass(modelClass: string | undefined): string {
    if (!modelClass) {
        return '-';
    }

    return modelClass.split('\\').pop() ?? modelClass;
}

function StatusBadge({ status }: { status: ExportRecord['status'] }) {
    if (status === 'completed') {
        return (
            <Badge variant="success">
                <Check />
                Concluído
            </Badge>
        );
    }

    if (status === 'failed') {
        return (
            <Badge variant="destructive">
                <X />
                Falhou
            </Badge>
        );
    }

    if (status === 'processing') {
        return (
            <Badge variant="info">
                <Loader2 className="animate-spin" />
                Processando
            </Badge>
        );
    }

    return (
        <Badge variant="warning">
            <Clock />
            Aguardando
        </Badge>
    );
}

function DownloadCell({ record }: { record: ExportRecord }) {
    if (record.status !== 'completed') {
        return <span className="text-xs text-muted-foreground">-</span>;
    }

    if (!record.download_url) {
        return <span className="text-xs text-muted-foreground">Expirado</span>;
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <a href={record.download_url} target="_blank" rel="noreferrer">
                <Download className="size-3.5" />
                Baixar
            </a>
        </Button>
    );
}

export default function ExportsIndex({ exports }: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Exportações', href: '/exportacoes' }],
    });

    return (
        <>
            <Head title="Exportações" />

            <Header
                title="Exportações"
                subtitle="Os 50 arquivos gerados mais recentes. Cada arquivo fica disponível por uma hora após a conclusão."
            />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    {exports.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-border">
                            <EmptyState
                                icon={FileText}
                                title="Nenhuma exportação"
                                description="Os arquivos gerados aparecem aqui por uma hora após a conclusão."
                            />
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-border bg-card">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted">
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Modelo
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Arquivo
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Registros
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Tamanho
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Criado em
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Expira em
                                        </th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {exports.map((exp, i) => (
                                        <tr
                                            key={exp.uuid}
                                            className={
                                                i % 2 === 0
                                                    ? 'bg-card'
                                                    : 'bg-muted/30'
                                            }
                                        >
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={exp.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="font-medium">
                                                        {formatModelClass(
                                                            exp.model_class,
                                                        )}
                                                    </span>
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-2xs"
                                                    >
                                                        {exp.format.toUpperCase()}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {exp.file_name ?? '-'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {exp.total_records != null ? (
                                                    exp.total_records.toLocaleString(
                                                        'pt-BR',
                                                    )
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                <span className="text-muted-foreground">
                                                    {formatBytes(exp.file_size)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                <span className="text-muted-foreground">
                                                    {formatDate(exp.created_at)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-muted-foreground">
                                                    {exp.expires_at
                                                        ? formatDate(
                                                              exp.expires_at,
                                                          )
                                                        : '-'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <DownloadCell record={exp} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

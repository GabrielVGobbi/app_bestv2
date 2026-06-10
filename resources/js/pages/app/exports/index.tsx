import { Head, setLayoutProps } from '@inertiajs/react';
import { Check, Clock, Download, FileText, Loader2, X } from 'lucide-react';

import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';
import type { ExportRecord } from '@/types/export';

interface PageProps {
    exports: ExportRecord[];
}

function formatBytes(bytes: number | null): string {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatModelClass(modelClass: string | undefined): string {
    if (!modelClass) return '—';
    return modelClass.split('\\').pop() ?? modelClass;
}

function StatusBadge({ status }: { status: ExportRecord['status'] }) {
    if (status === 'completed') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                <Check className="size-3" />
                Concluído
            </span>
        );
    }

    if (status === 'failed') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                <X className="size-3" />
                Falhou
            </span>
        );
    }

    if (status === 'processing') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                <Loader2 className="size-3 animate-spin" />
                Processando
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
            <Clock className="size-3" />
            Aguardando
        </span>
    );
}

function DownloadCell({ record }: { record: ExportRecord }) {
    if (record.status !== 'completed') {
        return <span className="text-muted-foreground text-xs">—</span>;
    }

    if (!record.download_url) {
        return (
            <span className="text-muted-foreground text-xs">Expirado</span>
        );
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
        breadcrumbs: [{ title: 'Histórico de Exportações', href: '/exportacoes' }],
    });

    return (
        <>
            <Head title="Histórico de Exportações" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Heading
                        title="Histórico de Exportações"
                        description="Todos os arquivos gerados nos últimos 50 registros. Arquivos ficam disponíveis por 1 hora após a conclusão."
                    />

                    {exports.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                            <FileText className="text-muted-foreground mb-3 size-10" />
                            <p className="text-muted-foreground text-sm">
                                Nenhuma exportação encontrada.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
                                            Status
                                        </th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
                                            Modelo
                                        </th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
                                            Arquivo
                                        </th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
                                            Registros
                                        </th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
                                            Tamanho
                                        </th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
                                            Criado em
                                        </th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">
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
                                                i % 2 === 0 ? 'bg-background' : 'bg-muted/20'
                                            }
                                        >
                                            <td className="px-4 py-3">
                                                <StatusBadge status={exp.status} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="font-medium">
                                                        {formatModelClass(exp.model_class)}
                                                    </span>
                                                    <Badge variant="secondary" className="text-[10px]">
                                                        {exp.format.toUpperCase()}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-muted-foreground font-mono text-xs">
                                                    {exp.file_name ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {exp.total_records != null ? (
                                                    exp.total_records.toLocaleString('pt-BR')
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                <span className="text-muted-foreground">
                                                    {formatBytes(exp.file_size)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-muted-foreground">
                                                    {formatDate(exp.created_at)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-muted-foreground">
                                                    {exp.expires_at ? formatDate(exp.expires_at) : '—'}
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

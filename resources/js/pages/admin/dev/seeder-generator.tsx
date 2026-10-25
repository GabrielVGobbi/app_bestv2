import { Head, setLayoutProps } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    ChevronRight,
    Database,
    DatabaseZap,
    Factory,
    FileCode2,
    Play,
    RotateCcw,
    Save,
    Search,
    Table2,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import Header from '@/components/dashboard/header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpGet, httpPost, isApiError } from '@/lib/http';

interface TableColumn {
    name: string;
    type: string;
    nullable: boolean;
}

interface TableInfo {
    name: string;
    count: number;
    hasModel: boolean;
    hasFactory: boolean;
    modelClass: string | null;
    columns: TableColumn[];
}

interface PreviewResult {
    code: string;
    class_name: string;
    file_name: string;
    path?: string;
}

interface ExecuteResult {
    success: boolean;
    message: string;
    records: number;
    exit_code: number;
}

interface RunResult {
    output: string;
    exit_code: number;
}

interface Props {
    tables: TableInfo[];
}

type Mode = 'data' | 'factory';

const toStudlyCase = (table: string) =>
    table
        .split('_')
        .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
        .join('');

export default function SeederGenerator({ tables: initialTables }: Props) {
    setLayoutProps({ title: 'Seeder Generator' });

    const [tables, setTables] = useState<TableInfo[]>(initialTables);
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<TableInfo | null>(null);

    const [mode, setMode] = useState<Mode>('data');
    const [limit, setLimit] = useState(50);
    const [truncate, setTruncate] = useState(false);
    const [idempotent, setIdempotent] = useState(false);
    const [uniqueColumn, setUniqueColumn] = useState('');
    const [seederName, setSeederName] = useState('');

    const [preview, setPreview] = useState<PreviewResult | null>(null);
    const [executeResult, setExecuteResult] = useState<ExecuteResult | null>(
        null,
    );
    const [runResult, setRunResult] = useState<RunResult | null>(null);
    const [loading, setLoading] = useState<
        'preview' | 'execute' | 'generate' | 'run' | 'refresh' | null
    >(null);
    const [error, setError] = useState<string | null>(null);

    const filteredTables = useMemo(
        () =>
            tables.filter((t) =>
                t.name.toLowerCase().includes(search.toLowerCase()),
            ),
        [tables, search],
    );

    function selectTable(table: TableInfo) {
        setSelected(table);
        setPreview(null);
        setExecuteResult(null);
        setRunResult(null);
        setError(null);
        setSeederName(toStudlyCase(table.name));
        setUniqueColumn('');
        setMode(table.hasFactory ? 'factory' : 'data');
    }

    function buildPayload() {
        return {
            table: selected!.name,
            mode,
            limit,
            truncate,
            idempotent,
            unique_column: idempotent ? uniqueColumn : null,
            seeder_name: seederName,
        };
    }

    async function refreshTables(quietly = false) {
        if (!quietly) {
            setLoading('refresh');
        }

        try {
            const res = await httpGet<{ data: TableInfo[] }>(
                apiRoutes.seederGenerator.tables,
            );
            setTables(res.data);

            if (selected) {
                const updated = res.data.find((t) => t.name === selected.name);

                if (updated) {
                    setSelected(updated);
                }
            }

            if (!quietly) {
                toast.success('Tabelas atualizadas');
            }
        } catch {
            if (!quietly) {
                toast.error('Erro ao atualizar tabelas');
            }
        } finally {
            if (!quietly) {
                setLoading(null);
            }
        }
    }

    async function handlePreview() {
        if (!selected) {
            return;
        }

        setLoading('preview');
        setError(null);
        setExecuteResult(null);
        setRunResult(null);

        try {
            const res = await httpPost<{ data: PreviewResult }>(
                apiRoutes.seederGenerator.preview,
                buildPayload(),
            );
            setPreview(res.data);
        } catch (e) {
            const msg = isApiError(e) ? e.message : 'Erro ao gerar preview';
            setError(msg);
            toast.error(msg);
        } finally {
            setLoading(null);
        }
    }

    async function handleExecute() {
        if (!selected) {
            return;
        }

        setLoading('execute');
        setError(null);
        setExecuteResult(null);
        setRunResult(null);

        try {
            const res = await httpPost<{
                data: ExecuteResult;
                message: string;
            }>(apiRoutes.seederGenerator.execute, buildPayload());
            setExecuteResult(res.data);

            if (res.data.exit_code === 0) {
                toast.success(res.message);
                refreshTables(true);
            } else {
                toast.error(res.message);
            }
        } catch (e) {
            const msg = isApiError(e) ? e.message : 'Erro ao executar';
            setError(msg);
            toast.error(msg);
        } finally {
            setLoading(null);
        }
    }

    async function handleGenerate() {
        if (!selected) {
            return;
        }

        setLoading('generate');
        setError(null);

        try {
            const res = await httpPost<{
                data: PreviewResult;
                message: string;
            }>(apiRoutes.seederGenerator.generate, buildPayload());
            setPreview(res.data);
            setExecuteResult(null);
            toast.success(res.message ?? 'Arquivo gerado!');
        } catch (e) {
            const msg = isApiError(e) ? e.message : 'Erro ao gerar arquivo';
            setError(msg);
            toast.error(msg);
        } finally {
            setLoading(null);
        }
    }

    async function handleRunFile() {
        if (!preview) {
            return;
        }

        setLoading('run');
        setError(null);
        setRunResult(null);

        try {
            const res = await httpPost<{ data: RunResult; message: string }>(
                apiRoutes.seederGenerator.run,
                { class: preview.class_name },
            );
            setRunResult(res.data);

            if (res.data.exit_code === 0) {
                toast.success(res.message ?? 'Seeder executado!');
                refreshTables(true);
            } else {
                toast.error('O seeder terminou com erros');
            }
        } catch (e) {
            const msg = isApiError(e) ? e.message : 'Erro ao executar seeder';
            setError(msg);
            toast.error(msg);
        } finally {
            setLoading(null);
        }
    }

    const isLoading = loading !== null;

    return (
        <>
            <Head title="Seeder Generator" />

            <Header
                title="Seeder Generator"
                subtitle="Gere e execute seeders diretamente ou salve como arquivo."
            >
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => refreshTables()}
                    disabled={loading === 'refresh'}
                >
                    <RotateCcw
                        className={`mr-2 h-4 w-4 ${loading === 'refresh' ? 'animate-spin' : ''}`}
                    />
                    Atualizar
                </Button>
            </Header>

            <div className="flex h-full flex-col gap-6 p-6">
                <div className="grid flex-1 grid-cols-[280px_1fr] gap-6">
                    {/* Painel esquerdo */}
                    <div className="flex flex-col gap-3">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Buscar tabela..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9"
                            />
                        </div>

                        <div className="flex flex-col gap-1 overflow-y-auto rounded-lg border border-border p-2">
                            {filteredTables.length === 0 && (
                                <p className="p-4 text-center text-sm text-muted-foreground">
                                    Nenhuma tabela encontrada
                                </p>
                            )}
                            {filteredTables.map((table) => {
                                const isSelected =
                                    selected?.name === table.name;

                                return (
                                    <button
                                        key={table.name}
                                        onClick={() => selectTable(table)}
                                        className={`flex w-full flex-col gap-1 rounded-md px-3 py-2 text-left text-sm transition-colors ${
                                            isSelected
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-accent'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="flex items-center gap-2 font-medium">
                                                <Table2 className="h-3.5 w-3.5 shrink-0" />
                                                {table.name}
                                            </span>
                                            <ChevronRight className="h-3.5 w-3.5 shrink-0 opacity-50" />
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge
                                                variant={
                                                    isSelected
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                className="h-4 px-1 text-2xs"
                                            >
                                                {table.count} reg.
                                            </Badge>
                                            {table.hasModel && (
                                                <Badge
                                                    variant={
                                                        isSelected
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                    className="h-4 px-1 text-2xs"
                                                >
                                                    Model
                                                </Badge>
                                            )}
                                            {table.hasFactory && (
                                                <Badge
                                                    variant={
                                                        isSelected
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                    className="h-4 px-1 text-2xs"
                                                >
                                                    Factory
                                                </Badge>
                                            )}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Painel direito */}
                    {!selected ? (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border">
                            <DatabaseZap className="mb-3 h-10 w-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Selecione uma tabela para começar
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {/* Info da tabela */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                        <Database className="h-4 w-4" />
                                        {selected.name}
                                        <Badge variant="secondary">
                                            {selected.count} registros
                                        </Badge>
                                        {selected.modelClass && (
                                            <Badge
                                                variant="outline"
                                                className="font-mono text-xs font-normal"
                                            >
                                                {selected.modelClass}
                                            </Badge>
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {selected.columns.map((col) => (
                                            <div
                                                key={col.name}
                                                className="flex items-center gap-1.5 rounded bg-muted px-2 py-1 text-xs"
                                            >
                                                <span className="font-medium">
                                                    {col.name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {col.type}
                                                </span>
                                                {col.nullable && (
                                                    <span className="text-muted-foreground opacity-50">
                                                        ?
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Configurações */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base">
                                        Configurações
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-x-6 gap-y-4">
                                    {/* Modo */}
                                    <div className="flex flex-col gap-2">
                                        <Label>Modo de geração</Label>
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    mode === 'data'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() => setMode('data')}
                                            >
                                                <Database className="mr-1.5 h-3.5 w-3.5" />
                                                Dados
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    mode === 'factory'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setMode('factory')
                                                }
                                                disabled={!selected.hasFactory}
                                                title={
                                                    !selected.hasFactory
                                                        ? 'Tabela não possui Factory'
                                                        : undefined
                                                }
                                            >
                                                <Factory className="mr-1.5 h-3.5 w-3.5" />
                                                Factory
                                            </Button>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {mode === 'factory'
                                                ? 'Gera dados falsos usando a factory do model.'
                                                : 'Captura registros existentes da tabela.'}
                                        </p>
                                    </div>

                                    {/* Limite */}
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="limit">
                                            {mode === 'factory'
                                                ? 'Quantidade a criar'
                                                : 'Limite de registros'}
                                        </Label>
                                        <Input
                                            id="limit"
                                            type="number"
                                            min={1}
                                            max={1000}
                                            value={limit}
                                            onChange={(e) =>
                                                setLimit(Number(e.target.value))
                                            }
                                            className="w-32"
                                        />
                                    </div>

                                    {/* Nome do seeder (apenas relevante para gerar arquivo) */}
                                    <div className="col-span-2 flex flex-col gap-2">
                                        <Label htmlFor="seeder-name">
                                            Nome do Seeder{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (para gerar arquivo)
                                            </span>
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <Input
                                                id="seeder-name"
                                                value={seederName}
                                                onChange={(e) =>
                                                    setSeederName(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Clientes"
                                                className="max-w-xs"
                                            />
                                            <span className="shrink-0 text-sm text-muted-foreground">
                                                → Generated{seederName || '…'}
                                                Seeder.php
                                            </span>
                                        </div>
                                    </div>

                                    {/* Opções modo data */}
                                    {mode === 'data' && (
                                        <>
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="truncate"
                                                    checked={truncate}
                                                    onCheckedChange={(v) =>
                                                        setTruncate(Boolean(v))
                                                    }
                                                />
                                                <Label
                                                    htmlFor="truncate"
                                                    className="cursor-pointer"
                                                >
                                                    Truncate antes de inserir
                                                </Label>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="idempotent"
                                                    checked={idempotent}
                                                    onCheckedChange={(v) =>
                                                        setIdempotent(
                                                            Boolean(v),
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor="idempotent"
                                                    className="cursor-pointer"
                                                >
                                                    Idempotente (ignorar
                                                    duplicatas)
                                                </Label>
                                            </div>

                                            {idempotent && (
                                                <div className="col-span-2 flex flex-col gap-2">
                                                    <Label>
                                                        Coluna única para
                                                        verificação
                                                    </Label>
                                                    <Select
                                                        value={uniqueColumn}
                                                        onValueChange={
                                                            setUniqueColumn
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className="max-w-xs"
                                                            placeholder="Selecione uma coluna..."
                                                        />
                                                        <SelectContent>
                                                            {selected.columns.map(
                                                                (
                                                                    col,
                                                                    colIndex,
                                                                ) => (
                                                                    <SelectItem
                                                                        key={
                                                                            col.name
                                                                        }
                                                                        index={
                                                                            colIndex
                                                                        }
                                                                        value={
                                                                            col.name
                                                                        }
                                                                    >
                                                                        {
                                                                            col.name
                                                                        }{' '}
                                                                        <span className="text-xs text-muted-foreground">
                                                                            (
                                                                            {
                                                                                col.type
                                                                            }
                                                                            )
                                                                        </span>
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Ações em dois grupos */}
                            <div className="flex flex-wrap items-center gap-3">
                                {/* Grupo 1: sem arquivo */}
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={handlePreview}
                                        disabled={isLoading}
                                    >
                                        <FileCode2 className="mr-2 h-4 w-4" />
                                        {loading === 'preview'
                                            ? 'Gerando…'
                                            : 'Pré-visualizar'}
                                    </Button>
                                    <Button
                                        onClick={handleExecute}
                                        disabled={isLoading}
                                        className="gap-2"
                                    >
                                        <Zap className="h-4 w-4" />
                                        {loading === 'execute'
                                            ? 'Executando…'
                                            : 'Executar Direto'}
                                    </Button>
                                </div>

                                <Separator
                                    orientation="vertical"
                                    className="h-8"
                                />

                                {/* Grupo 2: com arquivo */}
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={handleGenerate}
                                        disabled={!seederName || isLoading}
                                    >
                                        <Save className="mr-2 h-4 w-4" />
                                        {loading === 'generate'
                                            ? 'Salvando…'
                                            : 'Gerar Arquivo'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handleRunFile}
                                        disabled={!preview?.path || isLoading}
                                        title={
                                            !preview?.path
                                                ? 'Gere o arquivo primeiro'
                                                : undefined
                                        }
                                    >
                                        <Play className="mr-2 h-4 w-4" />
                                        {loading === 'run'
                                            ? 'Executando…'
                                            : 'Executar Arquivo'}
                                    </Button>
                                </div>
                            </div>

                            <p className="-mt-1 text-xs text-muted-foreground">
                                <strong>Executar Direto</strong>: roda no banco
                                imediatamente, sem criar arquivo.{' '}
                                <strong>Gerar Arquivo</strong>: salva em{' '}
                                <code>database/seeders/</code> para uso futuro.
                            </p>

                            {/* Erro */}
                            {error && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertTitle>Erro</AlertTitle>
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}

                            {/* Resultado: Executar Direto */}
                            {executeResult && (
                                <Alert
                                    variant={
                                        executeResult.exit_code === 0
                                            ? 'success'
                                            : 'destructive'
                                    }
                                >
                                    {executeResult.exit_code === 0 ? (
                                        <CheckCircle2 className="h-4 w-4" />
                                    ) : (
                                        <AlertCircle className="h-4 w-4" />
                                    )}
                                    <AlertTitle>
                                        {executeResult.exit_code === 0
                                            ? 'Executado com sucesso'
                                            : 'Erro na execução'}
                                    </AlertTitle>
                                    <AlertDescription className="flex items-center gap-3">
                                        {executeResult.message}
                                        {executeResult.records > 0 && (
                                            <Badge variant="secondary">
                                                {executeResult.records}{' '}
                                                registros
                                            </Badge>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Resultado: Executar Arquivo */}
                            {runResult && (
                                <Alert
                                    variant={
                                        runResult.exit_code === 0
                                            ? 'success'
                                            : 'destructive'
                                    }
                                >
                                    {runResult.exit_code === 0 ? (
                                        <CheckCircle2 className="h-4 w-4" />
                                    ) : (
                                        <AlertCircle className="h-4 w-4" />
                                    )}
                                    <AlertTitle>
                                        {runResult.exit_code === 0
                                            ? 'Arquivo executado'
                                            : 'Erro na execução'}
                                    </AlertTitle>
                                    {runResult.output && (
                                        <AlertDescription>
                                            <pre className="mt-2 text-xs whitespace-pre-wrap">
                                                {runResult.output}
                                            </pre>
                                        </AlertDescription>
                                    )}
                                </Alert>
                            )}

                            {/* Preview do código */}
                            {preview && (
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="flex items-center justify-between text-sm">
                                            <span className="flex items-center gap-2">
                                                <FileCode2 className="h-4 w-4" />
                                                {preview.file_name}
                                            </span>
                                            {preview.path && (
                                                <Badge
                                                    variant="secondary"
                                                    className="font-mono text-xs font-normal"
                                                >
                                                    salvo ✓
                                                </Badge>
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                    <Separator />
                                    <CardContent className="p-0">
                                        <pre className="overflow-x-auto rounded-b-lg bg-muted/50 p-4 text-xs leading-relaxed">
                                            <code>{preview.code}</code>
                                        </pre>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

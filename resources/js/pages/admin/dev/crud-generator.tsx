import { Head, setLayoutProps } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    FileCode2,
    Layers3,
    Plus,
    RefreshCw,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpPost, isApiError } from '@/lib/http';

type FieldType =
    | 'string'
    | 'text'
    | 'integer'
    | 'bigInteger'
    | 'decimal'
    | 'float'
    | 'double'
    | 'boolean'
    | 'date'
    | 'dateTime'
    | 'timestamp'
    | 'json';

interface CrudField {
    name: string;
    type: FieldType;
    nullable: boolean;
    unique: boolean;
    searchable: boolean;
    filterable: boolean;
    sortable: boolean;
    exportable: boolean;
    validation: string[];
}

interface CrudPayload {
    model: string;
    target: 'app' | 'module';
    module: string;
    icon: string;
    with_export: boolean;
    with_observer: boolean;
    with_policy: boolean;
    with_factory: boolean;
    with_seeder: boolean;
    with_tests: boolean;
    register_provider: boolean;
    force?: boolean;
    fields: CrudField[];
}

interface GeneratedFile {
    path: string;
    exists: boolean;
    status: 'create' | 'conflict';
}

interface PreviewResult {
    model: string;
    target: 'app' | 'module';
    module: string;
    files: GeneratedFile[];
    changes: string[];
}

const fieldTypes: FieldType[] = [
    'string',
    'text',
    'integer',
    'bigInteger',
    'decimal',
    'boolean',
    'date',
    'dateTime',
    'json',
];

function newField(): CrudField {
    return {
        name: '',
        type: 'string',
        nullable: false,
        unique: false,
        searchable: false,
        filterable: false,
        sortable: true,
        exportable: true,
        validation: [],
    };
}

export default function CrudGenerator() {
    setLayoutProps({
        breadcrumbs: [{ title: 'CRUD Generator', href: '/admin/dev/crud-generator' }],
    });

    const [payload, setPayload] = useState<CrudPayload>({
        model: 'Client',
        target: 'module',
        module: 'Clients',
        icon: 'TableProperties',
        with_export: true,
        with_observer: true,
        with_policy: false,
        with_factory: true,
        with_seeder: true,
        with_tests: false,
        register_provider: false,
        fields: [
            {
                ...newField(),
                name: 'name',
                searchable: true,
                filterable: true,
            },
            {
                ...newField(),
                name: 'email',
                nullable: true,
                unique: true,
                searchable: true,
                filterable: true,
            },
        ],
    });
    const [preview, setPreview] = useState<PreviewResult | null>(null);
    const [loading, setLoading] = useState<'preview' | 'apply' | null>(null);
    const [force, setForce] = useState(false);

    const conflicts = useMemo(
        () => preview?.files.filter((file) => file.exists) ?? [],
        [preview],
    );

    function updateField(index: number, patch: Partial<CrudField>): void {
        setPayload((current) => ({
            ...current,
            fields: current.fields.map((field, fieldIndex) =>
                fieldIndex === index ? { ...field, ...patch } : field,
            ),
        }));
        setPreview(null);
    }

    function removeField(index: number): void {
        setPayload((current) => ({
            ...current,
            fields: current.fields.filter((_, fieldIndex) => fieldIndex !== index),
        }));
        setPreview(null);
    }

    async function runPreview(): Promise<void> {
        setLoading('preview');
        try {
            const response = await httpPost<{ data: PreviewResult }>(
                apiRoutes.crudGenerator.preview,
                payload,
            );
            setPreview(response.data);
            toast.success('Preview gerado');
        } catch (error) {
            toast.error(isApiError(error) ? error.message : 'Erro ao gerar preview.');
        } finally {
            setLoading(null);
        }
    }

    async function applyCrud(): Promise<void> {
        if (!preview) {
            toast.error('Gere o preview antes de aplicar.');
            return;
        }

        setLoading('apply');
        try {
            const response = await httpPost<{ data: PreviewResult; message?: string }>(
                apiRoutes.crudGenerator.apply,
                { ...payload, force },
            );
            setPreview(response.data);
            toast.success(response.message ?? 'CRUD gerado');
        } catch (error) {
            toast.error(isApiError(error) ? error.message : 'Erro ao aplicar CRUD.');
        } finally {
            setLoading(null);
        }
    }

    return (
        <>
            <Head title="CRUD Generator" />

            <div className="page-wrapper">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="CRUD Generator"
                        description="Gere modulos CRUD alinhados ao StartKit"
                    />
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={runPreview}
                            disabled={loading !== null}
                        >
                            {loading === 'preview' ? (
                                <RefreshCw className="size-4 animate-spin" />
                            ) : (
                                <FileCode2 className="size-4" />
                            )}
                            Preview
                        </Button>
                        <Button
                            onClick={applyCrud}
                            disabled={loading !== null || !preview || (conflicts.length > 0 && !force)}
                        >
                            {loading === 'apply' ? (
                                <RefreshCw className="size-4 animate-spin" />
                            ) : (
                                <Wand2 className="size-4" />
                            )}
                            Aplicar
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Layers3 className="size-4 text-muted-foreground" />
                                    Blueprint
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="target">Destino</Label>
                                    <Select
                                        value={payload.target}
                                        onValueChange={(value) => {
                                            const target = value as CrudPayload['target'];

                                            setPayload((current) => ({
                                                ...current,
                                                target,
                                                register_provider:
                                                    target === 'module' ? current.register_provider : false,
                                            }));
                                            setPreview(null);
                                        }}
                                    >
                                        <SelectTrigger id="target" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="module">Modules/</SelectItem>
                                            <SelectItem value="app">App</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="model">Model</Label>
                                    <Input
                                        id="model"
                                        value={payload.model}
                                        onChange={(event) => {
                                            const model = event.target.value;
                                            setPayload((current) => ({
                                                ...current,
                                                model,
                                                module: model ? `${model}s` : current.module,
                                            }));
                                            setPreview(null);
                                        }}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="module">Modulo</Label>
                                    <Input
                                        id="module"
                                        value={payload.module}
                                        disabled={payload.target === 'app'}
                                        onChange={(event) => {
                                            setPayload((current) => ({
                                                ...current,
                                                module: event.target.value,
                                            }));
                                            setPreview(null);
                                        }}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="icon">Icone Lucide</Label>
                                    <Input
                                        id="icon"
                                        value={payload.icon}
                                        onChange={(event) => {
                                            setPayload((current) => ({
                                                ...current,
                                                icon: event.target.value,
                                            }));
                                            setPreview(null);
                                        }}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-4">
                                    <CardTitle className="text-base">Colunas</CardTitle>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setPayload((current) => ({
                                                ...current,
                                                fields: [...current.fields, newField()],
                                            }))
                                        }
                                    >
                                        <Plus className="size-4" />
                                        Campo
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {payload.fields.map((field, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-3 rounded-lg border p-3 lg:grid-cols-[1fr_150px_repeat(6,auto)_36px]"
                                    >
                                        <Input
                                            value={field.name}
                                            placeholder="field_name"
                                            onChange={(event) =>
                                                updateField(index, { name: event.target.value })
                                            }
                                        />
                                        <Select
                                            value={field.type}
                                            onValueChange={(value) =>
                                                updateField(index, { type: value as FieldType })
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {fieldTypes.map((type) => (
                                                    <SelectItem key={type} value={type}>
                                                        {type}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Flag
                                            label="Null"
                                            checked={field.nullable}
                                            onChange={(checked) => updateField(index, { nullable: checked })}
                                        />
                                        <Flag
                                            label="Unique"
                                            checked={field.unique}
                                            onChange={(checked) => updateField(index, { unique: checked })}
                                        />
                                        <Flag
                                            label="Search"
                                            checked={field.searchable}
                                            onChange={(checked) =>
                                                updateField(index, {
                                                    searchable: checked,
                                                    filterable: checked ? true : field.filterable,
                                                })
                                            }
                                        />
                                        <Flag
                                            label="Filter"
                                            checked={field.filterable}
                                            onChange={(checked) => updateField(index, { filterable: checked })}
                                        />
                                        <Flag
                                            label="Sort"
                                            checked={field.sortable}
                                            onChange={(checked) => updateField(index, { sortable: checked })}
                                        />
                                        <Flag
                                            label="Export"
                                            checked={field.exportable}
                                            onChange={(checked) => updateField(index, { exportable: checked })}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeField(index)}
                                            disabled={payload.fields.length === 1}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Preview</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!preview ? (
                                    <Alert>
                                        <AlertCircle className="size-4" />
                                        <AlertTitle>Nenhum preview</AlertTitle>
                                        <AlertDescription>
                                            Gere uma previa antes de aplicar o CRUD.
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    <>
                                        {conflicts.length > 0 ? (
                                            <Alert variant="destructive">
                                                <AlertCircle className="size-4" />
                                                <AlertTitle>Conflitos encontrados</AlertTitle>
                                                <AlertDescription>
                                                    Ative force overwrite apenas se quiser sobrescrever estes arquivos.
                                                </AlertDescription>
                                            </Alert>
                                        ) : (
                                            <Alert>
                                                <CheckCircle2 className="size-4" />
                                                <AlertTitle>Sem conflitos</AlertTitle>
                                                <AlertDescription>
                                                    O plano esta pronto para aplicacao.
                                                </AlertDescription>
                                            </Alert>
                                        )}

                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">Arquivos</p>
                                            <div className="max-h-80 space-y-1 overflow-auto rounded-md border p-2">
                                                {preview.files.map((file) => (
                                                    <div
                                                        key={file.path}
                                                        className="flex items-start justify-between gap-2 rounded px-2 py-1 text-xs"
                                                    >
                                                        <span className="min-w-0 break-all font-mono">
                                                            {file.path}
                                                        </span>
                                                        <Badge variant={file.exists ? 'destructive' : 'secondary'}>
                                                            {file.status}
                                                        </Badge>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">Alteracoes centrais</p>
                                            <ul className="space-y-1 text-sm text-muted-foreground">
                                                {preview.changes.map((change) => (
                                                    <li key={change}>{change}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Recursos</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Option
                                    label="Gerar e registrar ServiceProvider"
                                    checked={payload.register_provider}
                                    disabled={payload.target === 'app'}
                                    onChange={(checked) => {
                                        setPayload((current) => ({ ...current, register_provider: checked }));
                                        setPreview(null);
                                    }}
                                />
                                <Option
                                    label="Export async"
                                    checked={payload.with_export}
                                    onChange={(checked) => {
                                        setPayload((current) => ({ ...current, with_export: checked }));
                                        setPreview(null);
                                    }}
                                />
                                <Option
                                    label="Observer"
                                    checked={payload.with_observer}
                                    onChange={(checked) => {
                                        setPayload((current) => ({ ...current, with_observer: checked }));
                                        setPreview(null);
                                    }}
                                />
                                <Option
                                    label="Policy"
                                    checked={payload.with_policy}
                                    onChange={(checked) => {
                                        setPayload((current) => ({ ...current, with_policy: checked }));
                                        setPreview(null);
                                    }}
                                />
                                <Option
                                    label="Factory"
                                    checked={payload.with_factory}
                                    onChange={(checked) => {
                                        setPayload((current) => ({
                                            ...current,
                                            with_factory: checked,
                                            with_seeder: checked ? current.with_seeder : false,
                                        }));
                                        setPreview(null);
                                    }}
                                />
                                <Option
                                    label="Seeder"
                                    checked={payload.with_seeder}
                                    disabled={!payload.with_factory}
                                    onChange={(checked) => {
                                        setPayload((current) => ({ ...current, with_seeder: checked }));
                                        setPreview(null);
                                    }}
                                />
                                <Option
                                    label="Tests"
                                    checked={payload.with_tests}
                                    onChange={(checked) => {
                                        setPayload((current) => ({ ...current, with_tests: checked }));
                                        setPreview(null);
                                    }}
                                />
                                <Option label="Force overwrite" checked={force} onChange={setForce} />
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

function Option({
    label,
    checked,
    onChange,
    disabled = false,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
}) {
    return (
        <label className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm has-disabled:opacity-60">
            <Checkbox
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(Boolean(value))}
            />
            <span>{label}</span>
        </label>
    );
}

function Flag({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex min-w-16 items-center gap-1.5 text-xs text-muted-foreground">
            <Checkbox checked={checked} onCheckedChange={(value) => onChange(Boolean(value))} />
            {label}
        </label>
    );
}

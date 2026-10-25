import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Archive,
    BookOpen,
    CheckSquare,
    CloudUpload,
    Code,
    Database,
    Eye,
    Layers,
    LockKeyhole,
    Settings,
    Shield,
    Star,
    Workflow,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    components,
    fileAttachments,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Header from '@/components/dashboard/header';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { FileAttachments } from '@/modules/uploads';
import type { FileAttachmentsVariant } from '@/modules/uploads';

const CODE_BASIC = `import { FileAttachments } from '@/modules/uploads'

<FileAttachments
  modelType="client"
  modelId={client.uuid}
  title="Documentos do cliente"
/>`;

const CODE_COLLECTION = `<FileAttachments
  modelType="client"
  modelId={client.uuid}
  collection="documents"
  accept="application/pdf"
  title="Documentos do cliente"
/>

<FileAttachments
  modelType="client"
  modelId={client.uuid}
  collection="images"
  accept="image/*"
  showCoverOption
  title="Imagens"
/>`;

const CODE_VARIANTS = `<FileAttachments variant="inline" />
<FileAttachments variant="modal" attachLabel="Anexar" />
<FileAttachments variant="drop-overlay" />
<FileAttachments variant="toggle" attachLabel="Anexar comprovante" />`;

const CODE_ENDPOINTS = `GET    /api/v1/uploads
POST   /api/v1/uploads
GET    /api/v1/uploads/{upload}
GET    /api/v1/uploads/{upload}/preview
GET    /api/v1/uploads/{upload}/download
PUT    /api/v1/uploads/{upload}
DELETE /api/v1/uploads/{upload}
POST   /api/v1/uploads/{upload}/set-cover
POST   /api/v1/uploads/{upload}/toggle-favorite
DELETE /api/v1/uploads/bulk
POST   /api/v1/uploads/zip-requests
GET    /api/v1/uploads/zip-requests/{uuid}
GET    /api/v1/uploads/zip-requests/{uuid}/download`;

const CODE_MODEL = `use App\\Modules\\Uploads\\Contracts\\HasUploadTags;
use App\\Modules\\Uploads\\Traits\\HasUploads;

class Client extends Model implements HasUploadTags
{
    use HasUploads;

    public static function uploadTagSuggestions(): array
    {
        return [
            'coa'        => 'COA',
            'msds'       => 'MSDS',
            'spec_sheet' => 'Spec Sheet',
            'image'      => 'Imagem',
            'other'      => 'Outro',
        ];
    }
}

// config/uploads.php
'aliases' => [
    'client' => \\App\\Modules\\Clients\\Models\\Client::class,
],`;

const MODEL_ALIASES = [{ value: 'client', label: 'client' }] as const;

function CodeBlock({ code }: { code: string }) {
    return (
        <pre className="max-h-[min(28rem,50vh)] overflow-auto rounded-lg bg-muted p-4 text-xs leading-relaxed">
            <code>{code}</code>
        </pre>
    );
}

function PropRow({
    name,
    type,
    required = false,
    defaultValue,
    description,
}: {
    name: string;
    type: string;
    required?: boolean;
    defaultValue?: string;
    description: string;
}) {
    return (
        <div className="flex flex-col gap-1 border-b py-3 last:border-b-0">
            <div className="flex flex-wrap items-center gap-2">
                <code className="text-sm font-semibold text-primary">
                    {name}
                </code>
                {required && (
                    <Badge variant="outline" className="px-1.5 py-0 text-2xs">
                        obrigatório
                    </Badge>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <code className="rounded bg-muted px-1.5 py-0.5">{type}</code>
                {defaultValue && (
                    <span>
                        default: <code>{defaultValue}</code>
                    </span>
                )}
            </div>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

function FeatureCard({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <div className="flex gap-3 rounded-lg border p-4">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <Icon className="size-4 text-primary" />
            </div>
            <div>
                <h4 className="font-medium">{title}</h4>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

export default function FileAttachmentsDevReference() {
    const [modelType, setModelType] = useState<string>('client');
    const [modelId, setModelId] = useState<string>('1');
    const [variant, setVariant] = useState<FileAttachmentsVariant>('inline');
    const [readOnly, setReadOnly] = useState(false);
    const [hideTitle, setHideTitle] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'File Attachments', href: fileAttachments.url() },
        ],
    });

    const modelIdValue = useMemo(() => {
        const trimmed = modelId.trim();

        if (!trimmed) {
            return 1;
        }

        return /^\d+$/.test(trimmed) ? Number(trimmed) : trimmed;
    }, [modelId]);

    return (
        <>
            <Head title="FileAttachments - Referência" />

            <Header
                title="FileAttachments"
                subtitle="Módulo de uploads com fila por arquivo, rotas protegidas, preview, favoritos, seleção múltipla e ZIP em background."
            />

            <div className="mx-auto w-full max-w-7xl space-y-8 px-4 pb-12 lg:px-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <FeatureCard
                        icon={CloudUpload}
                        title="Fila resiliente"
                        description="Um POST por arquivo, progresso individual, retry e cancelamento."
                    />
                    <FeatureCard
                        icon={LockKeyhole}
                        title="Acesso protegido"
                        description="Preview e download passam por policy no backend."
                    />
                    <FeatureCard
                        icon={Star}
                        title="Favoritos"
                        description="Destaque independente de capa, com mutation própria."
                    />
                    <FeatureCard
                        icon={Archive}
                        title="ZIP seguro"
                        description="IDs autorizados por item e geração pelo worker."
                    />
                </div>

                <Tabs defaultValue="playground" className="space-y-6">
                    <TabsList
                        aria-label="Seções da página"
                        className="max-w-full overflow-x-auto"
                    >
                        <TabItem value="playground" label="Playground" />
                        <TabItem value="variants" label="Variantes" />
                        <TabItem value="queue" label="Fila e estados" />
                        <TabItem value="bulk-zip" label="Seleção e ZIP" />
                        <TabItem value="security" label="Segurança" />
                        <TabItem value="props" label="Props" />
                        <TabItem value="backend" label="Backend" />
                        <TabItem value="recipes" label="Receitas" />
                    </TabsList>

                    <TabPanel value="playground" className="space-y-6">
                        <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
                            <Card className="xl:sticky xl:top-4 xl:self-start">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings className="size-5" />
                                        Controles
                                    </CardTitle>
                                    <CardDescription>
                                        Use um registro existente na base. O
                                        campo aceita ID numérico ou UUID.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>modelType</Label>
                                            <Select
                                                value={modelType}
                                                onValueChange={setModelType}
                                            >
                                                <SelectTrigger />
                                                <SelectContent>
                                                    {MODEL_ALIASES.map(
                                                        (m, aliasIndex) => (
                                                            <SelectItem
                                                                key={m.value}
                                                                index={
                                                                    aliasIndex
                                                                }
                                                                value={m.value}
                                                            >
                                                                {m.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="model-id">
                                                modelId
                                            </Label>
                                            <Input
                                                id="model-id"
                                                value={modelId}
                                                onChange={(e) =>
                                                    setModelId(e.target.value)
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>variant</Label>
                                        <Select
                                            value={variant}
                                            onValueChange={(v) =>
                                                setVariant(
                                                    v as FileAttachmentsVariant,
                                                )
                                            }
                                        >
                                            <SelectTrigger />
                                            <SelectContent>
                                                <SelectItem
                                                    index={0}
                                                    value="inline"
                                                >
                                                    inline
                                                </SelectItem>
                                                <SelectItem
                                                    index={1}
                                                    value="modal"
                                                >
                                                    modal
                                                </SelectItem>
                                                <SelectItem
                                                    index={2}
                                                    value="drop-overlay"
                                                >
                                                    drop-overlay
                                                </SelectItem>
                                                <SelectItem
                                                    index={3}
                                                    value="toggle"
                                                >
                                                    toggle
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                        <div>
                                            <Label>readOnly</Label>
                                            <p className="text-xs text-muted-foreground">
                                                Oculta upload, edição, favoritos
                                                e exclusão.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={readOnly}
                                            onCheckedChange={setReadOnly}
                                        />
                                    </div>

                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                        <div>
                                            <Label>title = null</Label>
                                            <p className="text-xs text-muted-foreground">
                                                Remove cabeçalho e deixa o bloco
                                                mais compacto.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={hideTitle}
                                            onCheckedChange={setHideTitle}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="min-w-0 rounded-xl">
                                <FileAttachments
                                    key={`${modelType}-${modelIdValue}-${variant}-${hideTitle}`}
                                    modelType={modelType}
                                    modelId={modelIdValue}
                                    variant={variant}
                                    readOnly={readOnly}
                                    title={
                                        hideTitle ? null : 'Anexos (playground)'
                                    }
                                    description="Dados reais conforme modelId na API."
                                    concurrency={3}
                                    maxSize={25}
                                    accept="image/*,application/pdf"
                                    className="shadow-sm"
                                />
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="variants" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Layers className="size-5" />
                                    Variantes de apresentação
                                </CardTitle>
                                <CardDescription>
                                    A API de dados é a mesma; só muda como o
                                    dropzone aparece.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={CODE_VARIANTS} />
                            </CardContent>
                        </Card>
                    </TabPanel>

                    <TabPanel value="queue" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CloudUpload className="size-5" />
                                    Fila e estados
                                </CardTitle>
                                <CardDescription>
                                    Cada arquivo vira uma request isolada. Uma
                                    falha não derruba o lote.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-muted-foreground">
                                <p>
                                    Estados: <code>pending</code>,{' '}
                                    <code>uploading</code>, <code>success</code>{' '}
                                    e <code>error</code>.
                                </p>
                                <p>
                                    <code>concurrency</code> controla quantos
                                    arquivos sobem ao mesmo tempo. O padrão é 3.
                                </p>
                                <Separator />
                                <CodeBlock code={CODE_COLLECTION} />
                            </CardContent>
                        </Card>
                    </TabPanel>

                    <TabPanel value="bulk-zip" className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <FeatureCard
                                icon={CheckSquare}
                                title="Seleção múltipla"
                                description="Checkbox por item, clique no card para alternar seleção, excluir em lote e ZIP dos selecionados."
                            />
                            <FeatureCard
                                icon={Archive}
                                title="Download ZIP"
                                description="O job valida os IDs autorizados, gera o arquivo no servidor e notifica pelo painel global."
                            />
                        </div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Workflow className="size-5" />
                                    Fluxo do ZIP
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    1. UI envia IDs para{' '}
                                    <code>/zip-requests</code>.
                                </p>
                                <p>
                                    2. Backend autoriza cada upload com{' '}
                                    <code>UploadedPolicy::view</code>.
                                </p>
                                <p>
                                    3. Worker gera ZIP sem carregar todos os
                                    arquivos em memória.
                                </p>
                                <p>
                                    4. <code>ZipDownloadPanel</code> faz polling
                                    e libera o botão de download.
                                </p>
                            </CardContent>
                        </Card>
                    </TabPanel>

                    <TabPanel value="security" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="size-5" />
                                    Política de acesso
                                </CardTitle>
                                <CardDescription>
                                    Preview, download, ZIP, exclusão e favorito
                                    passam por autorização.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm text-muted-foreground">
                                <p>
                                    Para v1, <code>view</code>,{' '}
                                    <code>update</code> e <code>delete</code>{' '}
                                    usam dono/uploader do arquivo.
                                </p>
                                <p>
                                    O frontend recebe <code>preview_url</code> e{' '}
                                    <code>download_url</code>. A URL pública
                                    original permanece por compatibilidade, mas
                                    o componente usa as rotas protegidas.
                                </p>
                                <p>
                                    Favorito é independente de capa: vários
                                    anexos podem estar marcados como favoritos
                                    ao mesmo tempo.
                                </p>
                            </CardContent>
                        </Card>
                    </TabPanel>

                    <TabPanel value="props" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Props principais</CardTitle>
                                <CardDescription>
                                    Implementação em{' '}
                                    <code>
                                        resources/js/modules/uploads/components/file-attachments.tsx
                                    </code>
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <PropRow
                                    name="modelType"
                                    type="string"
                                    required
                                    description="Alias registrado em config/uploads.php. Ex: 'client'."
                                />
                                <PropRow
                                    name="modelId"
                                    type="number | string"
                                    required
                                    description="ID numérico ou UUID do registro pai."
                                />
                                <PropRow
                                    name="collection"
                                    type="string | null"
                                    description="Agrupa uploads por contexto dentro do mesmo parent. Ex: 'images', 'documents'."
                                />
                                <PropRow
                                    name="variant"
                                    type="FileAttachmentsVariant"
                                    defaultValue="'inline'"
                                    description="inline, modal, drop-overlay ou toggle."
                                />
                                <PropRow
                                    name="tag"
                                    type="string | null"
                                    description="Filtra a lista e força essa tag em novos uploads."
                                />
                                <PropRow
                                    name="showCoverOption"
                                    type="boolean"
                                    defaultValue="false"
                                    description="Habilita botão para definir imagem como capa."
                                />
                                <PropRow
                                    name="concurrency"
                                    type="number"
                                    defaultValue="3"
                                    description="Quantidade de uploads simultâneos."
                                />
                                <PropRow
                                    name="readOnly"
                                    type="boolean"
                                    defaultValue="false"
                                    description="Modo somente leitura: mantém preview e download, oculta edição."
                                />
                            </CardContent>
                        </Card>
                    </TabPanel>

                    <TabPanel value="backend" className="space-y-4">
                        <div className="grid gap-4 xl:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Database className="size-5" />
                                        Endpoints
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CodeBlock code={CODE_ENDPOINTS} />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <BookOpen className="size-5" />
                                        Habilitar no model
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CodeBlock code={CODE_MODEL} />
                                </CardContent>
                            </Card>
                        </div>
                    </TabPanel>

                    <TabPanel value="recipes" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Code className="size-5" />
                                    Uso mínimo
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={CODE_BASIC} />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Eye className="size-5" />
                                    Atalhos do preview
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    <code>←</code> e <code>→</code> navegam
                                    entre arquivos. <code>Esc</code> fecha.
                                </p>
                                <p>
                                    O botão de estrela alterna favorito; o botão
                                    de download usa a rota protegida.
                                </p>
                            </CardContent>
                        </Card>
                    </TabPanel>
                </Tabs>
            </div>
        </>
    );
}

import { Head, setLayoutProps } from '@inertiajs/react';
import {
    BookOpen,
    Braces,
    Database,
    LockKeyhole,
    MessageSquare,
    Paperclip,
    Route,
    Shield,
    Workflow,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    activityTimeline,
    components,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ActivityTimeline } from '@/modules/timeline';

interface Props {
    defaultSubject: {
        type: string;
        id: string | number;
        label: string;
    } | null;
}

const BREADCRUMBS = [
    { title: 'Components', href: components.url() },
    { title: 'Activity Timeline', href: activityTimeline.url() },
];

const CODE_MODEL = `use App\\Modules\\Timeline\\Contracts\\TimelineSubject;
use App\\Modules\\Timeline\\Traits\\HasComments;
use App\\Modules\\Timeline\\Traits\\HasTimeline;

class Client extends Model implements TimelineSubject
{
    use HasTimeline;
    use HasComments;
}`;

const CODE_ENDPOINTS = `GET    /api/v1/timeline/{type}/{id}
POST   /api/v1/timeline/{type}/{id}/comments
PUT    /api/v1/timeline/comments/{comment}
DELETE /api/v1/timeline/comments/{comment}
POST   /api/v1/timeline/comments/{comment}/replies`;

const CODE_FRONTEND = `import { ActivityTimeline } from '@/modules/timeline'

<ActivityTimeline
  modelType="client"
  modelId={client.uuid}
  allowAttachments
/>

<ActivityTimeline
  modelType="client"
  modelId={client.uuid}
  showHeader={false}
/>`;

const CODE_UPLOADS = `<FileAttachments
  modelType="timeline-comment"
  modelId={comment.uuid}
  collection="attachments"
  title={null}
  variant="toggle"
  attachLabel="Anexar"
/>`;

function CodeBlock({ code }: { code: string }) {
    return (
        <pre className="max-h-[min(28rem,50vh)] overflow-auto rounded-lg bg-muted p-4 text-xs leading-relaxed">
            <code>{code}</code>
        </pre>
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
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10">
                <Icon className="size-4 text-primary" />
            </div>
            <div>
                <h3 className="text-sm font-medium">{title}</h3>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

export default function ActivityTimelineDevReference({
    defaultSubject,
}: Props) {
    const [activeTab, setActiveTab] = useState('playground');
    const [modelType, setModelType] = useState(
        defaultSubject?.type ?? 'client',
    );
    const [modelId, setModelId] = useState(String(defaultSubject?.id ?? 1));
    const [showHeader, setShowHeader] = useState(true);
    const [readOnly, setReadOnly] = useState(false);
    const [allowAttachments, setAllowAttachments] = useState(true);
    const [pageSize, setPageSize] = useState('50');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: BREADCRUMBS,
        });
    }, []);

    const modelIdValue = useMemo(() => {
        const trimmed = modelId.trim();

        if (!trimmed) {
            return 1;
        }

        return /^\d+$/.test(trimmed) ? Number(trimmed) : trimmed;
    }, [modelId]);

    return (
        <>
            <Head title="Activity Timeline - Referencia" />

            <main className="page-wrapper-lg">
                <div className="page-section">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <Heading
                            title="Activity Timeline"
                            description="Componente operacional para comentarios, replies, anexos e eventos por model."
                        />
                        <Badge variant="secondary" className="w-fit">
                            @/modules/timeline
                        </Badge>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <FeatureCard
                            icon={Route}
                            title="Alias seguro"
                            description="O frontend envia aliases como client; o backend resolve pela config."
                        />
                        <FeatureCard
                            icon={MessageSquare}
                            title="Comentarios"
                            description="Raiz, replies de um nivel, edicao e soft delete por policy."
                        />
                        <FeatureCard
                            icon={Paperclip}
                            title="Anexos"
                            description="Reutiliza FileAttachments com o alias timeline-comment."
                        />
                        <FeatureCard
                            icon={Shield}
                            title="Policies"
                            description="Permissoes calculadas no servidor e refletidas no JSON."
                        />
                    </div>

                    <Tabs
                        value={activeTab}
                        onValueChange={setActiveTab}
                        className="space-y-6"
                    >
                        <TabsList className="flex h-auto w-full flex-wrap gap-1">
                            <TabsTrigger value="playground">
                                Playground
                            </TabsTrigger>
                            <TabsTrigger value="architecture">
                                Arquitetura
                            </TabsTrigger>
                            <TabsTrigger value="backend">Backend</TabsTrigger>
                            <TabsTrigger value="frontend">Frontend</TabsTrigger>
                            <TabsTrigger value="uploads">Uploads</TabsTrigger>
                            <TabsTrigger value="security">
                                Seguranca
                            </TabsTrigger>
                            <TabsTrigger value="checklist">
                                Checklist
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="playground" className="space-y-6">
                            <div className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)] xl:items-start">
                                <Card className="rounded-lg xl:sticky xl:top-4">
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Controles
                                        </CardTitle>
                                        <CardDescription>
                                            Use um registro existente. ID
                                            numerico e UUID sao aceitos.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>modelType</Label>
                                            <Select
                                                value={modelType}
                                                onValueChange={setModelType}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="client">
                                                        client
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="timeline-model-id">
                                                modelId
                                            </Label>
                                            <Input
                                                id="timeline-model-id"
                                                value={modelId}
                                                onChange={(event) =>
                                                    setModelId(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            {defaultSubject && (
                                                <p className="text-xs text-muted-foreground">
                                                    Inicial:{' '}
                                                    {defaultSubject.label}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>pageSize</Label>
                                            <Select
                                                value={pageSize}
                                                onValueChange={setPageSize}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="20">
                                                        20
                                                    </SelectItem>
                                                    <SelectItem value="50">
                                                        50
                                                    </SelectItem>
                                                    <SelectItem value="100">
                                                        100
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                            <div>
                                                <Label>showHeader</Label>
                                                <p className="text-xs text-muted-foreground">
                                                    Alterna entre header
                                                    completo ou apenas a
                                                    estrutura da timeline.
                                                </p>
                                            </div>
                                            <Switch
                                                checked={showHeader}
                                                onCheckedChange={setShowHeader}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                            <div>
                                                <Label>readOnly</Label>
                                                <p className="text-xs text-muted-foreground">
                                                    Mantem leitura e oculta
                                                    composer/acoes.
                                                </p>
                                            </div>
                                            <Switch
                                                checked={readOnly}
                                                onCheckedChange={setReadOnly}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                            <div>
                                                <Label>allowAttachments</Label>
                                                <p className="text-xs text-muted-foreground">
                                                    Renderiza FileAttachments
                                                    por comentario.
                                                </p>
                                            </div>
                                            <Switch
                                                checked={allowAttachments}
                                                onCheckedChange={
                                                    setAllowAttachments
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <ActivityTimeline
                                    key={`${modelType}-${modelIdValue}-${showHeader}-${readOnly}-${allowAttachments}-${pageSize}`}
                                    modelType={modelType}
                                    modelId={modelIdValue}
                                    showHeader={showHeader}
                                    readOnly={readOnly}
                                    allowAttachments={allowAttachments}
                                    pageSize={Number(pageSize)}
                                    title="Timeline do registro"
                                />
                            </div>
                        </TabsContent>

                        <TabsContent
                            value="architecture"
                            className="space-y-4 [contain-intrinsic-size:auto_260px] [content-visibility:auto]"
                        >
                            {activeTab === 'architecture' && (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <FeatureCard
                                        icon={Workflow}
                                        title="Backend modular"
                                        description="app/Modules/Timeline concentra resolver, comments, policy, service e resource."
                                    />
                                    <FeatureCard
                                        icon={Braces}
                                        title="Frontend modular"
                                        description="resources/js/modules/timeline concentra API, hooks e componentes."
                                    />
                                    <FeatureCard
                                        icon={BookOpen}
                                        title="Activity log"
                                        description="A fase seguinte une os logs Spatie com os comentarios."
                                    />
                                    <FeatureCard
                                        icon={Paperclip}
                                        title="Uploads"
                                        description="Comentarios recebem anexos via parent polimorfico."
                                    />
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="backend"
                            className="space-y-4 [contain-intrinsic-size:auto_320px] [content-visibility:auto]"
                        >
                            {activeTab === 'backend' && (
                                <div className="grid gap-4 xl:grid-cols-2">
                                    <Card className="rounded-lg">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Database className="size-4" />
                                                Rotas
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <CodeBlock code={CODE_ENDPOINTS} />
                                        </CardContent>
                                    </Card>
                                    <Card className="rounded-lg">
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                Habilitar model
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <CodeBlock code={CODE_MODEL} />
                                        </CardContent>
                                    </Card>
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="frontend"
                            className="space-y-4 [contain-intrinsic-size:auto_220px] [content-visibility:auto]"
                        >
                            {activeTab === 'frontend' && (
                                <Card className="rounded-lg">
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Uso minimo
                                        </CardTitle>
                                        <CardDescription>
                                            O componente real recebe alias e ID
                                            do model.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <CodeBlock code={CODE_FRONTEND} />
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="uploads"
                            className="space-y-4 [contain-intrinsic-size:auto_220px] [content-visibility:auto]"
                        >
                            {activeTab === 'uploads' && (
                                <Card className="rounded-lg">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Paperclip className="size-4" />
                                            Anexos por comentario
                                        </CardTitle>
                                        <CardDescription>
                                            O comentario precisa existir antes
                                            do upload para ter UUID.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <CodeBlock code={CODE_UPLOADS} />
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="security"
                            className="space-y-4 [contain-intrinsic-size:auto_180px] [content-visibility:auto]"
                        >
                            {activeTab === 'security' && (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <FeatureCard
                                        icon={LockKeyhole}
                                        title="Sem classe na URL"
                                        description="Aliases em config/timeline.php impedem instanciacao arbitraria."
                                    />
                                    <FeatureCard
                                        icon={Shield}
                                        title="Permissoes por item"
                                        description="can_update, can_delete e can_reply vem do backend."
                                    />
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="checklist"
                            className="space-y-4 [contain-intrinsic-size:auto_260px] [content-visibility:auto]"
                        >
                            {activeTab === 'checklist' && (
                                <Card className="rounded-lg">
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Novo model
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ol className="list-decimal space-y-2 pl-5 text-sm text-muted-foreground">
                                            <li>
                                                Adicionar HasTimeline e
                                                HasComments no model.
                                            </li>
                                            <li>
                                                Registrar alias em
                                                config/timeline.php.
                                            </li>
                                            <li>
                                                Garantir policy view no model
                                                pai.
                                            </li>
                                            <li>
                                                Opcionalmente criar regra
                                                comment na policy.
                                            </li>
                                            <li>
                                                Configurar activity fields
                                                quando a fase de logs entrar.
                                            </li>
                                            <li>
                                                Renderizar ActivityTimeline no
                                                frontend.
                                            </li>
                                            <li>
                                                Rodar testes focados do modulo.
                                            </li>
                                        </ol>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>
            </main>
        </>
    );
}

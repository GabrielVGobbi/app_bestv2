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
import { Switch } from '@/components/ui/switch';
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

            <Header
                title="Activity Timeline"
                subtitle="Componente operacional para comentários, replies, anexos e eventos por model."
            >
                <Badge variant="secondary" className="w-fit">
                    @/modules/timeline
                </Badge>
            </Header>

            <main className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <FeatureCard
                            icon={Route}
                            title="Alias seguro"
                            description="O frontend envia aliases como client; o backend resolve pela config."
                        />
                        <FeatureCard
                            icon={MessageSquare}
                            title="Comentários"
                            description="Raiz, replies de um nível, edição e soft delete por policy."
                        />
                        <FeatureCard
                            icon={Paperclip}
                            title="Anexos"
                            description="Reutiliza FileAttachments com o alias timeline-comment."
                        />
                        <FeatureCard
                            icon={Shield}
                            title="Policies"
                            description="Permissões calculadas no servidor e refletidas no JSON."
                        />
                    </div>

                    <Tabs
                        value={activeTab}
                        onValueChange={setActiveTab}
                        className="space-y-6"
                    >
                        <TabsList
                            aria-label="Seções da página"
                            className="max-w-full overflow-x-auto"
                        >
                            <TabItem value="playground" label="Playground" />
                            <TabItem value="architecture" label="Arquitetura" />
                            <TabItem value="backend" label="Backend" />
                            <TabItem value="frontend" label="Frontend" />
                            <TabItem value="uploads" label="Uploads" />
                            <TabItem value="security" label="Segurança" />
                            <TabItem value="checklist" label="Checklist" />
                        </TabsList>

                        <TabPanel value="playground" className="space-y-6">
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
                                                <SelectTrigger />
                                                <SelectContent>
                                                    <SelectItem
                                                        index={0}
                                                        value="client"
                                                    >
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
                                                <SelectTrigger />
                                                <SelectContent>
                                                    <SelectItem
                                                        index={0}
                                                        value="20"
                                                    >
                                                        20
                                                    </SelectItem>
                                                    <SelectItem
                                                        index={1}
                                                        value="50"
                                                    >
                                                        50
                                                    </SelectItem>
                                                    <SelectItem
                                                        index={2}
                                                        value="100"
                                                    >
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
                                                    por comentário.
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
                        </TabPanel>

                        <TabPanel
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
                                        description="A fase seguinte une os logs Spatie com os comentários."
                                    />
                                    <FeatureCard
                                        icon={Paperclip}
                                        title="Uploads"
                                        description="Comentários recebem anexos via parent polimórfico."
                                    />
                                </div>
                            )}
                        </TabPanel>

                        <TabPanel
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
                        </TabPanel>

                        <TabPanel
                            value="frontend"
                            className="space-y-4 [contain-intrinsic-size:auto_220px] [content-visibility:auto]"
                        >
                            {activeTab === 'frontend' && (
                                <Card className="rounded-lg">
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Uso mínimo
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
                        </TabPanel>

                        <TabPanel
                            value="uploads"
                            className="space-y-4 [contain-intrinsic-size:auto_220px] [content-visibility:auto]"
                        >
                            {activeTab === 'uploads' && (
                                <Card className="rounded-lg">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Paperclip className="size-4" />
                                            Anexos por comentário
                                        </CardTitle>
                                        <CardDescription>
                                            O comentário precisa existir antes
                                            do upload para ter UUID.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <CodeBlock code={CODE_UPLOADS} />
                                    </CardContent>
                                </Card>
                            )}
                        </TabPanel>

                        <TabPanel
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
                                        title="Permissões por item"
                                        description="can_update, can_delete e can_reply vem do backend."
                                    />
                                </div>
                            )}
                        </TabPanel>

                        <TabPanel
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
                        </TabPanel>
                    </Tabs>
                </div>
            </main>
        </>
    );
}

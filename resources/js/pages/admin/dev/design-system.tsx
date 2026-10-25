import { Head, setLayoutProps } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    Check,
    CheckCircle,
    Copy,
    Info,
    Loader2,
    Mail,
    Plus,
    Search,
    Settings,
    Sun,
    Trash2,
    Upload,
    User,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import {
    components,
    designSystem,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Header from '@/components/dashboard/header';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Tooltip } from '@/components/ui/fluid/tooltip';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

// ─── Code Block ──────────────────────────────────────────────────────────────

function CodeBlock({
    code,
    language = 'tsx',
}: {
    code: string;
    language?: string;
}) {
    const [copied, setCopied] = useState(false);
    const [, copy] = useClipboard();
    const feedback = useRef({
        mounted: false,
        attempt: 0,
        timer: null as ReturnType<typeof setTimeout> | null,
    });

    useEffect(() => {
        const active = feedback.current;
        active.mounted = true;

        return () => {
            active.mounted = false;
            ++active.attempt;

            if (active.timer !== null) {
                clearTimeout(active.timer);
                active.timer = null;
            }
        };
    }, []);

    async function handleCopy(): Promise<void> {
        const active = feedback.current;
        const attempt = ++active.attempt;

        if (active.timer !== null) {
            clearTimeout(active.timer);
            active.timer = null;
        }

        setCopied(false);
        const success = await copy(code.trim());

        if (!success || !active.mounted || active.attempt !== attempt) {
            return;
        }

        setCopied(true);
        active.timer = setTimeout(() => {
            if (active.mounted && active.attempt === attempt) {
                active.timer = null;
                setCopied(false);
            }
        }, 2000);
    }

    return (
        <div className="group relative rounded-lg border bg-muted/40">
            <div className="flex items-center justify-between border-b px-4 py-2">
                <span className="font-mono text-xs text-muted-foreground">
                    {language}
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 gap-1.5 px-2 text-xs opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 focus-visible:opacity-100"
                    onClick={handleCopy}
                >
                    {copied ? (
                        <>
                            <Check className="size-3" />
                            Copiado
                        </>
                    ) : (
                        <>
                            <Copy className="size-3" />
                            Copiar
                        </>
                    )}
                </Button>
            </div>
            <pre className="overflow-x-auto p-4 text-sm leading-relaxed">
                <code className="font-mono text-foreground">{code.trim()}</code>
            </pre>
        </div>
    );
}

// ─── Section Wrapper ──────────────────────────────────────────────────────────

function Section({
    id,
    title,
    description,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section id={id} className="scroll-mt-6 space-y-4">
            <div>
                <h2 className="text-xl font-semibold tracking-tight">
                    {title}
                </h2>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
            <Separator />
        </section>
    );
}

// ─── Preview + Code tabs ──────────────────────────────────────────────────────

function PreviewBlock({
    preview,
    code,
    className,
}: {
    preview: React.ReactNode;
    code: string;
    className?: string;
}) {
    const [tab, setTab] = useState<'preview' | 'code'>('preview');

    return (
        <div className="rounded-lg border">
            <div className="flex border-b">
                {(['preview', 'code'] as const).map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={cn(
                            'px-4 py-2 text-sm font-medium transition-colors',
                            tab === t
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t === 'preview' ? 'Preview' : 'Código'}
                    </button>
                ))}
            </div>
            {tab === 'preview' ? (
                <div
                    className={cn(
                        'flex min-h-32 flex-wrap items-center justify-center gap-3 p-6',
                        className,
                    )}
                >
                    {preview}
                </div>
            ) : (
                <div className="p-2">
                    <CodeBlock code={code} />
                </div>
            )}
        </div>
    );
}

// ─── Nav items ───────────────────────────────────────────────────────────────

const navSections = [
    { id: 'typography', label: 'Tipografia' },
    { id: 'colors', label: 'Cores' },
    { id: 'buttons', label: 'Botões' },
    { id: 'badges', label: 'Badges' },
    { id: 'cards', label: 'Cards' },
    { id: 'alerts', label: 'Alerts' },
    { id: 'forms', label: 'Formulários' },
    { id: 'avatars', label: 'Avatares' },
    { id: 'skeletons', label: 'Skeletons' },
    { id: 'tooltips', label: 'Tooltips' },
    { id: 'layout', label: 'Layout' },
    { id: 'playground', label: 'Playground' },
];

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function DesignSystem() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Design System', href: designSystem.url() },
        ],
    });

    // Playground state
    const [btnVariant, setBtnVariant] = useState<
        'default' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'link'
    >('default');
    const [btnSize, setBtnSize] = useState<'sm' | 'default' | 'lg'>('default');
    const [btnLoading, setBtnLoading] = useState(false);
    const [btnDisabled, setBtnDisabled] = useState(false);
    const [btnIcon, setBtnIcon] = useState(false);

    return (
        <>
            <Head title="Design System" />

            <Header
                title="Design System"
                subtitle="Referência visual de todos os componentes, estilos e padrões de interface usados nesta aplicação."
            />

            <div className="page-wrapper-lg">
                <div className="flex gap-8 p-4">
                    {/* ── Sticky side nav ── */}
                    <aside className="hidden w-44 shrink-0 xl:block">
                        <nav className="sticky top-4 space-y-1">
                            <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                Seções
                            </p>
                            {navSections.map((s) => (
                                <a
                                    key={s.id}
                                    href={`#${s.id}`}
                                    className="block rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                >
                                    {s.label}
                                </a>
                            ))}
                        </nav>
                    </aside>

                    {/* ── Main content ── */}
                    <main className="min-w-0 flex-1 space-y-10">
                        {/* ─────────────── TIPOGRAFIA ─────────────── */}
                        <Section
                            id="typography"
                            title="Tipografia"
                            description="Geist em uma escala curta. Hierarquia por tamanho e peso; só dois níveis de cor de texto."
                        >
                            <PreviewBlock
                                preview={
                                    <div className="w-full divide-y divide-separator">
                                        {[
                                            {
                                                size: '11px',
                                                role: 'Legenda de tabela densa e metadados',
                                                className:
                                                    'text-2xs text-muted-foreground',
                                            },
                                            {
                                                size: '12px',
                                                role: 'Descrições',
                                                className:
                                                    'text-xs text-muted-foreground',
                                            },
                                            {
                                                size: '13px',
                                                role: 'Sidebar, módulos e corpo de tabela',
                                                className: 'text-[13px]',
                                            },
                                            {
                                                size: '14px',
                                                role: 'Corpo',
                                                className: 'text-sm',
                                            },
                                            {
                                                size: '16px',
                                                role: 'Lead',
                                                className: 'text-base',
                                            },
                                            {
                                                size: '20px',
                                                role: 'Seção (h2)',
                                                className:
                                                    'text-xl font-semibold tracking-tight',
                                            },
                                            {
                                                size: '24px',
                                                role: 'Título de página',
                                                className:
                                                    'text-2xl font-bold tracking-tight',
                                            },
                                            {
                                                size: '30px',
                                                role: 'KPI',
                                                className:
                                                    'text-3xl font-bold tracking-tight tabular-nums',
                                            },
                                        ].map((sample) => (
                                            <div
                                                key={sample.size}
                                                className="flex items-baseline gap-4 py-2"
                                            >
                                                <span className="w-12 shrink-0 font-mono text-2xs text-muted-foreground">
                                                    {sample.size}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'min-w-0 flex-1 truncate',
                                                        sample.className,
                                                    )}
                                                >
                                                    {sample.role}
                                                </span>
                                            </div>
                                        ))}
                                        <div className="flex items-baseline gap-4 py-2">
                                            <span className="w-12 shrink-0 font-mono text-2xs text-muted-foreground">
                                                tabular
                                            </span>
                                            <span className="grid text-right text-sm tabular-nums">
                                                <span>1.234,56</span>
                                                <span>98.071,00</span>
                                            </span>
                                        </div>
                                    </div>
                                }
                                code={`<h1 className="text-2xl font-bold tracking-tight">Título de página</h1>
<h2 className="text-xl font-semibold tracking-tight">Seção</h2>
<p className="text-sm">Corpo</p>
<p className="text-xs text-muted-foreground">Descrição</p>
<span className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">Rótulo</span>
<p className="text-3xl font-bold tabular-nums">1.234</p>`}
                                className="items-start"
                            />
                        </Section>

                        {/* ─────────────── CORES ─────────────── */}
                        <Section
                            id="colors"
                            title="Cores"
                            description="Tokens semânticos derivados de --brand-hue. Cada superfície tem o seu par de texto; status têm hue fixo."
                        >
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                {[
                                    {
                                        token: 'background',
                                        text: 'foreground',
                                        className:
                                            'bg-background text-foreground',
                                    },
                                    {
                                        token: 'card',
                                        text: 'card-foreground',
                                        className:
                                            'bg-card text-card-foreground',
                                    },
                                    {
                                        token: 'popover',
                                        text: 'popover-foreground',
                                        className:
                                            'bg-popover text-popover-foreground',
                                    },
                                    {
                                        token: 'muted',
                                        text: 'muted-foreground',
                                        className:
                                            'bg-muted text-muted-foreground',
                                    },
                                    {
                                        token: 'secondary',
                                        text: 'secondary-foreground',
                                        className:
                                            'bg-secondary text-secondary-foreground',
                                    },
                                    {
                                        token: 'accent',
                                        text: 'accent-foreground',
                                        className:
                                            'bg-accent text-accent-foreground',
                                    },
                                    {
                                        token: 'primary',
                                        text: 'primary-foreground',
                                        className:
                                            'bg-primary text-primary-foreground',
                                    },
                                    {
                                        token: 'primary-soft',
                                        text: 'primary-soft-foreground',
                                        className:
                                            'bg-primary-soft text-primary-soft-foreground',
                                    },
                                    {
                                        token: 'success',
                                        text: 'success-foreground',
                                        className:
                                            'bg-success text-success-foreground',
                                    },
                                    {
                                        token: 'success-soft',
                                        text: 'success-soft-foreground',
                                        className:
                                            'bg-success-soft text-success-soft-foreground',
                                    },
                                    {
                                        token: 'warning',
                                        text: 'warning-foreground',
                                        className:
                                            'bg-warning text-warning-foreground',
                                    },
                                    {
                                        token: 'warning-soft',
                                        text: 'warning-soft-foreground',
                                        className:
                                            'bg-warning-soft text-warning-soft-foreground',
                                    },
                                    {
                                        token: 'destructive',
                                        text: 'destructive-foreground',
                                        className:
                                            'bg-destructive text-destructive-foreground',
                                    },
                                    {
                                        token: 'destructive-soft',
                                        text: 'destructive-soft-foreground',
                                        className:
                                            'bg-destructive-soft text-destructive-soft-foreground',
                                    },
                                    {
                                        token: 'info',
                                        text: 'info-foreground',
                                        className:
                                            'bg-info text-info-foreground',
                                    },
                                    {
                                        token: 'info-soft',
                                        text: 'info-soft-foreground',
                                        className:
                                            'bg-info-soft text-info-soft-foreground',
                                    },
                                    {
                                        token: 'border',
                                        text: 'foreground',
                                        className:
                                            'border-2 border-border bg-card text-foreground',
                                    },
                                    {
                                        token: 'input',
                                        text: 'foreground',
                                        className:
                                            'border-2 border-input bg-input-surface text-foreground',
                                    },
                                    {
                                        token: 'sidebar',
                                        text: 'sidebar-foreground',
                                        className:
                                            'bg-sidebar text-sidebar-foreground',
                                    },
                                ].map((swatch) => (
                                    <div
                                        key={swatch.token}
                                        className={cn(
                                            'rounded-lg border border-border p-3',
                                            swatch.className,
                                        )}
                                    >
                                        <p className="text-xs font-semibold">
                                            --{swatch.token}
                                        </p>
                                        <p className="text-2xs">
                                            texto: --{swatch.text}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <CodeBlock
                                code={`// Superfície + par de texto
<div className="bg-card text-card-foreground">Card</div>
<div className="bg-primary text-primary-foreground">Ação principal</div>

// Status e destaques usam tintas suaves
<span className="bg-success-soft text-success-soft-foreground">Ativo</span>
<span className="bg-primary-soft text-primary-soft-foreground">AB</span>

// Bordas por papel
<div className="border border-border" />    // decorativa
<div className="border-b border-separator" /> // linha interna
<input className="border border-input" />   // campo`}
                            />
                        </Section>

                        {/* ─────────────── BOTÕES ─────────────── */}
                        <Section
                            id="buttons"
                            title="Botões"
                            description="Variantes, tamanhos, estados e combinações com ícones."
                        >
                            <div className="space-y-4">
                                <div>
                                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Variantes
                                    </p>
                                    <PreviewBlock
                                        preview={
                                            <>
                                                <Button variant="default">
                                                    Default
                                                </Button>
                                                <Button variant="secondary">
                                                    Secondary
                                                </Button>
                                                <Button variant="outline">
                                                    Outline
                                                </Button>
                                                <Button variant="ghost">
                                                    Ghost
                                                </Button>
                                                <Button variant="destructive">
                                                    Destructive
                                                </Button>
                                                <Button variant="link">
                                                    Link
                                                </Button>
                                            </>
                                        }
                                        code={`<Button variant="default">Default</Button>
<Button variant="secondary">Secondary</Button>
<Button variant="outline">Outline</Button>
<Button variant="ghost">Ghost</Button>
<Button variant="destructive">Destructive</Button>
<Button variant="link">Link</Button>`}
                                    />
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Tamanhos
                                    </p>
                                    <PreviewBlock
                                        preview={
                                            <>
                                                <Button size="sm">Small</Button>
                                                <Button size="default">
                                                    Default
                                                </Button>
                                                <Button size="lg">Large</Button>
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                >
                                                    <Plus />
                                                </Button>
                                            </>
                                        }
                                        code={`<Button size="sm">Small</Button>
<Button size="default">Default</Button>
<Button size="lg">Large</Button>
<Button size="icon" variant="outline"><Plus /></Button>`}
                                    />
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Com ícones & estados
                                    </p>
                                    <PreviewBlock
                                        preview={
                                            <>
                                                <Button>
                                                    <Mail />
                                                    Enviar email
                                                </Button>
                                                <Button variant="outline">
                                                    <Upload />
                                                    Upload
                                                </Button>
                                                <Button disabled>
                                                    <Loader2 className="animate-spin" />
                                                    Carregando
                                                </Button>
                                                <Button variant="destructive">
                                                    <Trash2 />
                                                    Excluir
                                                </Button>
                                            </>
                                        }
                                        code={`<Button>
    <Mail /> Enviar email
</Button>
<Button variant="outline">
    <Upload /> Upload
</Button>
<Button disabled>
    <Loader2 className="animate-spin" /> Carregando
</Button>
<Button variant="destructive">
    <Trash2 /> Excluir
</Button>`}
                                    />
                                </div>
                            </div>
                        </Section>

                        {/* ─────────────── BADGES ─────────────── */}
                        <Section
                            id="badges"
                            title="Badges"
                            description="Só tintas suaves: cada status usa o seu par soft e soft-foreground."
                        >
                            <PreviewBlock
                                preview={
                                    <>
                                        <Badge variant="default">Padrão</Badge>
                                        <Badge variant="secondary">
                                            Secundário
                                        </Badge>
                                        <Badge variant="success">
                                            <CheckCircle />
                                            Ativo
                                        </Badge>
                                        <Badge variant="warning">
                                            <AlertCircle />
                                            Pendente
                                        </Badge>
                                        <Badge variant="destructive">
                                            Falhou
                                        </Badge>
                                        <Badge variant="info">Na fila</Badge>
                                        <Badge variant="outline">
                                            Contorno
                                        </Badge>
                                    </>
                                }
                                code={`<Badge variant="default">Padrão</Badge>
<Badge variant="secondary">Secundário</Badge>
<Badge variant="success"><CheckCircle /> Ativo</Badge>
<Badge variant="warning"><AlertCircle /> Pendente</Badge>
<Badge variant="destructive">Falhou</Badge>
<Badge variant="info">Na fila</Badge>
<Badge variant="outline">Contorno</Badge>`}
                            />
                        </Section>

                        {/* ─────────────── CARDS ─────────────── */}
                        <Section
                            id="cards"
                            title="Cards"
                            description="Contêineres de conteúdo com header, body e footer."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <PreviewBlock
                                    preview={
                                        <Card className="w-full">
                                            <CardHeader>
                                                <CardTitle>
                                                    Card Simples
                                                </CardTitle>
                                                <CardDescription>
                                                    Descrição complementar do
                                                    card.
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <p className="text-sm text-muted-foreground">
                                                    Conteúdo principal do card
                                                    com informações relevantes.
                                                </p>
                                            </CardContent>
                                        </Card>
                                    }
                                    code={`<Card>
    <CardHeader>
        <CardTitle>Card Simples</CardTitle>
        <CardDescription>Descrição do card.</CardDescription>
    </CardHeader>
    <CardContent>
        <p>Conteúdo do card.</p>
    </CardContent>
</Card>`}
                                    className="items-start"
                                />
                                <PreviewBlock
                                    preview={
                                        <Card className="w-full">
                                            <CardHeader>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-9 items-center justify-center rounded-md border bg-background">
                                                        <Settings className="size-4 text-primary" />
                                                    </div>
                                                    <div>
                                                        <CardTitle className="text-base">
                                                            Card com Ícone
                                                        </CardTitle>
                                                        <CardDescription className="text-xs">
                                                            Com badge de status
                                                        </CardDescription>
                                                    </div>
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-auto"
                                                    >
                                                        Pronto
                                                    </Badge>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="text-sm text-muted-foreground">
                                                Ideal para catálogos de
                                                funcionalidades.
                                            </CardContent>
                                            <CardFooter className="flex gap-2">
                                                <Button size="sm">Abrir</Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Detalhes
                                                </Button>
                                            </CardFooter>
                                        </Card>
                                    }
                                    code={`<Card>
    <CardHeader>
        <div className="flex items-center gap-3">
            <div className="flex size-9 items-center justify-center rounded-md border bg-background">
                <Settings className="size-4 text-primary" />
            </div>
            <div>
                <CardTitle>Card com Ícone</CardTitle>
                <CardDescription>Com badge de status</CardDescription>
            </div>
            <Badge variant="secondary" className="ml-auto">Pronto</Badge>
        </div>
    </CardHeader>
    <CardContent>Conteúdo aqui.</CardContent>
    <CardFooter className="flex gap-2">
        <Button size="sm">Abrir</Button>
        <Button size="sm" variant="outline">Detalhes</Button>
    </CardFooter>
</Card>`}
                                    className="items-start"
                                />
                            </div>
                        </Section>

                        {/* ─────────────── ALERTS ─────────────── */}
                        <Section
                            id="alerts"
                            title="Alerts"
                            description="Mensagens de feedback: neutra, informação, sucesso, aviso e erro."
                        >
                            <PreviewBlock
                                preview={
                                    <div className="w-full space-y-3">
                                        <Alert>
                                            <Info className="size-4" />
                                            <AlertTitle>Neutro</AlertTitle>
                                            <AlertDescription>
                                                Contexto sem urgência.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert variant="info">
                                            <Info className="size-4" />
                                            <AlertTitle>Informação</AlertTitle>
                                            <AlertDescription>
                                                Mensagem informativa para o
                                                usuário.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert variant="success">
                                            <CheckCircle className="size-4" />
                                            <AlertTitle>Sucesso</AlertTitle>
                                            <AlertDescription>
                                                Operação concluída com êxito.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert variant="warning">
                                            <AlertTriangle className="size-4" />
                                            <AlertTitle>Aviso</AlertTitle>
                                            <AlertDescription>
                                                Atenção: esta ação pode ter
                                                consequências.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert variant="destructive">
                                            <AlertCircle className="size-4" />
                                            <AlertTitle>Erro</AlertTitle>
                                            <AlertDescription>
                                                Algo deu errado. Tente
                                                novamente.
                                            </AlertDescription>
                                        </Alert>
                                    </div>
                                }
                                code={`<Alert variant="info">
    <Info className="size-4" />
    <AlertTitle>Informação</AlertTitle>
    <AlertDescription>Mensagem informativa.</AlertDescription>
</Alert>

<Alert variant="success">...</Alert>
<Alert variant="warning">...</Alert>
<Alert variant="destructive">...</Alert>`}
                                className="items-start"
                            />
                        </Section>

                        {/* ─────────────── FORMULÁRIOS ─────────────── */}
                        <Section
                            id="forms"
                            title="Formulários"
                            description="Inputs, labels, checkboxes e estados de validação."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <PreviewBlock
                                    preview={
                                        <div className="w-full space-y-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="demo-name">
                                                    Nome
                                                </Label>
                                                <Input
                                                    id="demo-name"
                                                    placeholder="Digite seu nome"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="demo-email">
                                                    Email
                                                </Label>
                                                <div className="relative">
                                                    <Mail className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="demo-email"
                                                        type="email"
                                                        placeholder="seu@email.com"
                                                        className="pl-9"
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="demo-search">
                                                    Busca
                                                </Label>
                                                <div className="relative">
                                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="demo-search"
                                                        placeholder="Buscar..."
                                                        className="pr-9 pl-9"
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                                                    >
                                                        <X className="size-3" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor="demo-invalid"
                                                    className="text-destructive"
                                                >
                                                    Campo com erro
                                                </Label>
                                                <Input
                                                    id="demo-invalid"
                                                    aria-invalid
                                                    defaultValue="valor inválido"
                                                />
                                                <p className="text-xs text-destructive">
                                                    Este campo é obrigatório.
                                                </p>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Desabilitado</Label>
                                                <Input
                                                    disabled
                                                    placeholder="Campo desabilitado"
                                                />
                                            </div>
                                        </div>
                                    }
                                    code={`// Input simples
<div className="space-y-2">
    <Label htmlFor="name">Nome</Label>
    <Input id="name" placeholder="Digite seu nome" />
</div>

// Input com ícone
<div className="relative">
    <Mail className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
    <Input type="email" placeholder="seu@email.com" className="pl-9" />
</div>

// Input com erro
<Input aria-invalid defaultValue="valor inválido" />
<p className="text-xs text-destructive">Este campo é obrigatório.</p>

// Desabilitado
<Input disabled placeholder="Campo desabilitado" />`}
                                    className="items-start"
                                />
                                <PreviewBlock
                                    preview={
                                        <div className="w-full space-y-4">
                                            <div className="flex items-center gap-2">
                                                <Checkbox id="chk1" />
                                                <Label htmlFor="chk1">
                                                    Checkbox padrão
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="chk2"
                                                    defaultChecked
                                                />
                                                <Label htmlFor="chk2">
                                                    Checkbox marcado
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox id="chk3" disabled />
                                                <Label
                                                    htmlFor="chk3"
                                                    className="text-muted-foreground"
                                                >
                                                    Desabilitado
                                                </Label>
                                            </div>
                                            <div className="flex items-start gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="chk4"
                                                    className="mt-0.5"
                                                />
                                                <div>
                                                    <Label
                                                        htmlFor="chk4"
                                                        className="font-medium"
                                                    >
                                                        Aceitar termos de uso
                                                    </Label>
                                                    <p className="text-xs text-muted-foreground">
                                                        Li e concordo com os
                                                        termos e condições.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    }
                                    code={`<div className="flex items-center gap-2">
    <Checkbox id="chk" />
    <Label htmlFor="chk">Checkbox padrão</Label>
</div>

// Com descrição
<div className="flex items-start gap-2 rounded-md border p-3">
    <Checkbox id="terms" className="mt-0.5" />
    <div>
        <Label htmlFor="terms" className="font-medium">
            Aceitar termos de uso
        </Label>
        <p className="text-xs text-muted-foreground">
            Li e concordo com os termos e condições.
        </p>
    </div>
</div>`}
                                    className="items-start"
                                />
                            </div>
                        </Section>

                        {/* ─────────────── AVATARES ─────────────── */}
                        <Section
                            id="avatars"
                            title="Avatares"
                            description="Representação visual de usuários com fallback automático."
                        >
                            <PreviewBlock
                                preview={
                                    <>
                                        <Avatar>
                                            <AvatarFallback>GG</AvatarFallback>
                                        </Avatar>
                                        <Avatar className="size-8">
                                            <AvatarFallback className="text-xs">
                                                AB
                                            </AvatarFallback>
                                        </Avatar>
                                        <Avatar className="size-12">
                                            <AvatarFallback>CD</AvatarFallback>
                                        </Avatar>
                                        <Avatar className="size-16">
                                            <AvatarFallback className="text-xl">
                                                EF
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex -space-x-2">
                                            {['GH', 'IJ', 'KL', 'ML'].map(
                                                (i) => (
                                                    <Avatar
                                                        key={i}
                                                        className="ring-2 ring-background"
                                                    >
                                                        <AvatarFallback className="text-xs">
                                                            {i}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                ),
                                            )}
                                        </div>
                                    </>
                                }
                                code={`// Tamanhos
<Avatar className="size-8"><AvatarFallback>AB</AvatarFallback></Avatar>
<Avatar><AvatarFallback>CD</AvatarFallback></Avatar>
<Avatar className="size-12"><AvatarFallback>EF</AvatarFallback></Avatar>

// Stack de avatares
<div className="flex -space-x-2">
    {users.map(u => (
        <Avatar key={u.id} className="ring-2 ring-background">
            <AvatarFallback>{u.initials}</AvatarFallback>
        </Avatar>
    ))}
</div>`}
                            />
                        </Section>

                        {/* ─────────────── SKELETONS ─────────────── */}
                        <Section
                            id="skeletons"
                            title="Skeletons"
                            description="Placeholders animados para estados de carregamento."
                        >
                            <PreviewBlock
                                preview={
                                    <div className="w-full space-y-4">
                                        <div className="flex items-center gap-3">
                                            <Skeleton className="size-10 rounded-full" />
                                            <div className="flex-1 space-y-2">
                                                <Skeleton className="h-4 w-3/4" />
                                                <Skeleton className="h-3 w-1/2" />
                                            </div>
                                        </div>
                                        <Skeleton className="h-32 w-full rounded-lg" />
                                        <div className="grid grid-cols-3 gap-2">
                                            <Skeleton className="h-20 rounded-md" />
                                            <Skeleton className="h-20 rounded-md" />
                                            <Skeleton className="h-20 rounded-md" />
                                        </div>
                                    </div>
                                }
                                code={`// Avatar + texto
<div className="flex items-center gap-3">
    <Skeleton className="size-10 rounded-full" />
    <div className="flex-1 space-y-2">
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-3 w-1/2" />
    </div>
</div>

// Card skeleton
<Skeleton className="h-32 w-full rounded-lg" />

// Grid de cards
<div className="grid grid-cols-3 gap-2">
    <Skeleton className="h-20 rounded-md" />
    <Skeleton className="h-20 rounded-md" />
    <Skeleton className="h-20 rounded-md" />
</div>`}
                                className="items-start"
                            />
                        </Section>

                        {/* ─────────────── TOOLTIPS ─────────────── */}
                        <Section
                            id="tooltips"
                            title="Tooltips"
                            description="Dicas de contexto ao passar o mouse sobre elementos."
                        >
                            <PreviewBlock
                                preview={
                                    <>
                                        <Tooltip content="Notificações">
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                aria-label="Notificações"
                                            >
                                                <Bell />
                                            </Button>
                                        </Tooltip>
                                        <Tooltip
                                            content="Perfil do usuário"
                                            side="right"
                                        >
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                aria-label="Perfil do usuário"
                                            >
                                                <User />
                                            </Button>
                                        </Tooltip>
                                        <Tooltip
                                            content="Configurações"
                                            side="bottom"
                                        >
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                aria-label="Configurações"
                                            >
                                                <Settings />
                                            </Button>
                                        </Tooltip>
                                    </>
                                }
                                code={`import { Tooltip } from '@/components/ui/fluid/tooltip';

<Tooltip content="Notificações">
    <Button variant="outline" size="icon" aria-label="Notificações">
        <Bell />
    </Button>
</Tooltip>

// Posições: top (padrão), right, bottom, left
<Tooltip content="Perfil do usuário" side="right">…</Tooltip>`}
                            />
                        </Section>

                        {/* ─────────────── LAYOUT ─────────────── */}
                        <Section
                            id="layout"
                            title="Layout"
                            description="Estruturas de cabeçalho de página, heading e separadores."
                        >
                            <div className="space-y-4">
                                <PreviewBlock
                                    preview={
                                        <div className="w-full">
                                            <Heading
                                                title="Título da Página"
                                                description="Descrição complementar usada abaixo do título principal."
                                            />
                                        </div>
                                    }
                                    code={`import Heading from '@/components/heading'

<Heading
    title="Título da Página"
    description="Descrição complementar."
/>`}
                                    className="items-start"
                                />
                                <PreviewBlock
                                    preview={
                                        <div className="w-full space-y-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h3 className="text-lg font-semibold">
                                                        Seção com Ação
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Padrão de header interno
                                                        com botão.
                                                    </p>
                                                </div>
                                                <Button size="sm">
                                                    <Plus />
                                                    Novo item
                                                </Button>
                                            </div>
                                            <Separator />
                                            <p className="text-sm text-muted-foreground">
                                                Conteúdo abaixo do separador.
                                            </p>
                                        </div>
                                    }
                                    code={`// Header de seção com ação
<div className="flex items-center justify-between">
    <div>
        <h3 className="text-lg font-semibold">Seção com Ação</h3>
        <p className="text-sm text-muted-foreground">Descrição.</p>
    </div>
    <Button size="sm">
        <Plus /> Novo item
    </Button>
</div>
<Separator />
<p>Conteúdo abaixo do separador.</p>`}
                                    className="items-start"
                                />
                            </div>
                        </Section>

                        {/* ─────────────── PLAYGROUND ─────────────── */}
                        <Section
                            id="playground"
                            title="Playground"
                            description="Monte e visualize um botão interativamente."
                        >
                            <div className="grid gap-6 lg:grid-cols-2">
                                {/* Controls */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Configurações
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Variante
                                            </Label>
                                            <div className="flex flex-wrap gap-2">
                                                {(
                                                    [
                                                        'default',
                                                        'secondary',
                                                        'outline',
                                                        'ghost',
                                                        'destructive',
                                                        'link',
                                                    ] as const
                                                ).map((v) => (
                                                    <button
                                                        key={v}
                                                        onClick={() =>
                                                            setBtnVariant(v)
                                                        }
                                                        className={cn(
                                                            'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                                            btnVariant === v
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'hover:bg-muted',
                                                        )}
                                                    >
                                                        {v}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Tamanho
                                            </Label>
                                            <div className="flex gap-2">
                                                {(
                                                    [
                                                        'sm',
                                                        'default',
                                                        'lg',
                                                    ] as const
                                                ).map((s) => (
                                                    <button
                                                        key={s}
                                                        onClick={() =>
                                                            setBtnSize(s)
                                                        }
                                                        className={cn(
                                                            'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                                            btnSize === s
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'hover:bg-muted',
                                                        )}
                                                    >
                                                        {s}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Opções
                                            </Label>
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="pg-loading"
                                                        checked={btnLoading}
                                                        onCheckedChange={(v) =>
                                                            setBtnLoading(!!v)
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="pg-loading"
                                                        className="text-sm font-normal"
                                                    >
                                                        Loading
                                                    </Label>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="pg-disabled"
                                                        checked={btnDisabled}
                                                        onCheckedChange={(v) =>
                                                            setBtnDisabled(!!v)
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="pg-disabled"
                                                        className="text-sm font-normal"
                                                    >
                                                        Disabled
                                                    </Label>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="pg-icon"
                                                        checked={btnIcon}
                                                        onCheckedChange={(v) =>
                                                            setBtnIcon(!!v)
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="pg-icon"
                                                        className="text-sm font-normal"
                                                    >
                                                        Com ícone
                                                    </Label>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Preview + generated code */}
                                <div className="space-y-4">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                Preview
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="flex min-h-24 items-center justify-center">
                                            <Button
                                                variant={btnVariant}
                                                size={btnSize}
                                                disabled={
                                                    btnDisabled || btnLoading
                                                }
                                            >
                                                {btnLoading && (
                                                    <Loader2 className="animate-spin" />
                                                )}
                                                {btnIcon && !btnLoading && (
                                                    <Sun />
                                                )}
                                                {btnLoading
                                                    ? 'Carregando...'
                                                    : 'Botão exemplo'}
                                            </Button>
                                        </CardContent>
                                    </Card>

                                    <CodeBlock
                                        code={`<Button${btnVariant !== 'default' ? `\n    variant="${btnVariant}"` : ''}${btnSize !== 'default' ? `\n    size="${btnSize}"` : ''}${btnDisabled ? '\n    disabled' : ''}
>${btnLoading ? '\n    <Loader2 className="animate-spin" />\n    Carregando...' : btnIcon ? '\n    <Sun />\n    Botão exemplo' : '\n    Botão exemplo'}\n</Button>`}
                                    />
                                </div>
                            </div>
                        </Section>

                        {/* ─── Theme toggle preview ─── */}
                        <Section
                            id="theme"
                            title="Dicas de Dark Mode"
                            description="As classes Tailwind reagem automaticamente ao tema via CSS variables."
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <CodeBlock
                                    code={`// Use sempre variáveis semânticas:
bg-background      // fundo da página
bg-card            // fundo de card
bg-muted           // fundo secundário
text-foreground    // texto principal
text-muted-foreground // texto secundário
border-border      // bordas padrão

// Evite cores fixas como bg-white ou text-black
// pois quebram no dark mode`}
                                    language="css"
                                />
                                <CodeBlock
                                    code={`// Ícones de tema
import { Sun, Moon } from 'lucide-react'

// Renderização condicional de ícone
{theme === 'dark' ? (
    <Sun className="size-4" />
) : (
    <Moon className="size-4" />
)}

// Não use variantes dark: para cor; o token já cobre os dois temas
<div className="bg-card text-card-foreground">
    Conteúdo
</div>`}
                                />
                            </div>
                        </Section>

                        <div className="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            <Info className="size-4 shrink-0" />
                            <span>
                                Componentes adicionais como{' '}
                                <strong>DataTable</strong>,{' '}
                                <strong>AsyncSelect</strong> e{' '}
                                <strong>InlineCellEdit</strong> possuem páginas
                                dedicadas na seção de Components.
                            </span>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}

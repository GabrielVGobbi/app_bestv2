import { Head, setLayoutProps } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    Check,
    CheckCircle,
    ChevronDown,
    Copy,
    Info,
    Loader2,
    Mail,
    Moon,
    Plus,
    Search,
    Settings,
    Sun,
    Trash2,
    Upload,
    User,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { components, designSystem } from '@/actions/App/Http/Controllers/Admin/DevToolsController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

// ─── Code Block ──────────────────────────────────────────────────────────────

function CodeBlock({ code, language = 'tsx' }: { code: string; language?: string }) {
    const [copied, setCopied] = useState(false);

    function handleCopy() {
        navigator.clipboard.writeText(code.trim());
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <div className="group relative rounded-lg border bg-muted/40">
            <div className="flex items-center justify-between border-b px-4 py-2">
                <span className="font-mono text-xs text-muted-foreground">{language}</span>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 gap-1.5 px-2 text-xs opacity-0 transition-opacity group-hover:opacity-100"
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
                <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">{description}</p>
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

            <div className="page-wrapper-lg">
                <div className="flex gap-8 p-4">
                    {/* ── Sticky side nav ── */}
                    <aside className="hidden w-44 shrink-0 xl:block">
                        <nav className="sticky top-4 space-y-1">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
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
                        <Heading
                            title="Design System"
                            description="Referência visual de todos os componentes, estilos e padrões de interface usados nesta aplicação."
                        />

                        {/* ─────────────── TIPOGRAFIA ─────────────── */}
                        <Section
                            id="typography"
                            title="Tipografia"
                            description="Hierarquia de texto — headings, corpo, muted e variações."
                        >
                            <PreviewBlock
                                preview={
                                    <div className="w-full space-y-3">
                                        <h1 className="scroll-m-20 text-4xl font-extrabold tracking-tight lg:text-5xl">
                                            Heading 1
                                        </h1>
                                        <h2 className="scroll-m-20 text-3xl font-semibold tracking-tight">
                                            Heading 2
                                        </h2>
                                        <h3 className="scroll-m-20 text-2xl font-semibold tracking-tight">
                                            Heading 3
                                        </h3>
                                        <h4 className="scroll-m-20 text-xl font-semibold tracking-tight">
                                            Heading 4
                                        </h4>
                                        <p className="leading-7">
                                            Parágrafo padrão com <strong>negrito</strong> e{' '}
                                            <em>itálico</em> para destaque inline.
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Texto muted — usado para descrições e informações secundárias.
                                        </p>
                                        <p className="font-mono text-sm">
                                            Texto mono — para código, IDs e valores técnicos.
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Extra small — labels, timestamps, metadados.
                                        </p>
                                    </div>
                                }
                                code={`<h1 className="text-4xl font-extrabold tracking-tight">Heading 1</h1>
<h2 className="text-3xl font-semibold tracking-tight">Heading 2</h2>
<h3 className="text-2xl font-semibold tracking-tight">Heading 3</h3>
<h4 className="text-xl font-semibold tracking-tight">Heading 4</h4>
<p className="leading-7">Parágrafo padrão</p>
<p className="text-sm text-muted-foreground">Texto muted</p>
<p className="font-mono text-sm">Texto mono</p>`}
                                className="items-start"
                            />
                        </Section>

                        {/* ─────────────── CORES ─────────────── */}
                        <Section
                            id="colors"
                            title="Cores"
                            description="Paleta semântica baseada em CSS variables — funciona automaticamente em dark/light mode."
                        >
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                {[
                                    { name: 'Background', bg: 'bg-background', text: 'text-foreground', label: '--background' },
                                    { name: 'Foreground', bg: 'bg-foreground', text: 'text-background', label: '--foreground' },
                                    { name: 'Primary', bg: 'bg-primary', text: 'text-primary-foreground', label: '--primary' },
                                    { name: 'Secondary', bg: 'bg-secondary', text: 'text-secondary-foreground', label: '--secondary' },
                                    { name: 'Muted', bg: 'bg-muted', text: 'text-muted-foreground', label: '--muted' },
                                    { name: 'Accent', bg: 'bg-accent', text: 'text-accent-foreground', label: '--accent' },
                                    { name: 'Destructive', bg: 'bg-destructive', text: 'text-white', label: '--destructive' },
                                    { name: 'Border', bg: 'bg-border', text: 'text-foreground', label: '--border' },
                                ].map((c) => (
                                    <div key={c.name} className="overflow-hidden rounded-lg border">
                                        <div className={cn('flex h-16 items-center justify-center', c.bg, c.text)}>
                                            <span className="text-sm font-medium">{c.name}</span>
                                        </div>
                                        <div className="bg-background px-3 py-2">
                                            <p className="text-xs font-medium">{c.name}</p>
                                            <p className="font-mono text-[10px] text-muted-foreground">{c.label}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <CodeBlock
                                code={`// Usando CSS variables via Tailwind
<div className="bg-primary text-primary-foreground">Primary</div>
<div className="bg-secondary text-secondary-foreground">Secondary</div>
<div className="bg-destructive text-white">Destructive</div>
<div className="bg-muted text-muted-foreground">Muted</div>
<div className="text-foreground">Foreground</div>`}
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
                                    <p className="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Variantes</p>
                                    <PreviewBlock
                                        preview={
                                            <>
                                                <Button variant="default">Default</Button>
                                                <Button variant="secondary">Secondary</Button>
                                                <Button variant="outline">Outline</Button>
                                                <Button variant="ghost">Ghost</Button>
                                                <Button variant="destructive">Destructive</Button>
                                                <Button variant="link">Link</Button>
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
                                    <p className="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Tamanhos</p>
                                    <PreviewBlock
                                        preview={
                                            <>
                                                <Button size="sm">Small</Button>
                                                <Button size="default">Default</Button>
                                                <Button size="lg">Large</Button>
                                                <Button size="icon" variant="outline">
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
                                    <p className="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Com ícones & estados</p>
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
                            description="Indicadores de status, categorias e contadores."
                        >
                            <PreviewBlock
                                preview={
                                    <>
                                        <Badge variant="default">Default</Badge>
                                        <Badge variant="secondary">Secondary</Badge>
                                        <Badge variant="outline">Outline</Badge>
                                        <Badge variant="destructive">Destructive</Badge>
                                        <Badge variant="secondary">
                                            <CheckCircle />
                                            Ativo
                                        </Badge>
                                        <Badge variant="outline">
                                            <AlertCircle />
                                            Pendente
                                        </Badge>
                                    </>
                                }
                                code={`<Badge variant="default">Default</Badge>
<Badge variant="secondary">Secondary</Badge>
<Badge variant="outline">Outline</Badge>
<Badge variant="destructive">Destructive</Badge>
<Badge variant="secondary"><CheckCircle /> Ativo</Badge>
<Badge variant="outline"><AlertCircle /> Pendente</Badge>`}
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
                                                <CardTitle>Card Simples</CardTitle>
                                                <CardDescription>
                                                    Descrição complementar do card.
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <p className="text-sm text-muted-foreground">
                                                    Conteúdo principal do card com informações relevantes.
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
                                                        <CardTitle className="text-base">Card com Ícone</CardTitle>
                                                        <CardDescription className="text-xs">
                                                            Com badge de status
                                                        </CardDescription>
                                                    </div>
                                                    <Badge variant="secondary" className="ml-auto">
                                                        Pronto
                                                    </Badge>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="text-sm text-muted-foreground">
                                                Ideal para catálogos de funcionalidades.
                                            </CardContent>
                                            <CardFooter className="flex gap-2">
                                                <Button size="sm">Abrir</Button>
                                                <Button size="sm" variant="outline">
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
                            description="Mensagens de feedback — informação, sucesso, aviso e erro."
                        >
                            <PreviewBlock
                                preview={
                                    <div className="w-full space-y-3">
                                        <Alert>
                                            <Info className="size-4" />
                                            <AlertTitle>Informação</AlertTitle>
                                            <AlertDescription>
                                                Mensagem informativa para o usuário.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert className="border-green-500/50 text-green-700 dark:text-green-400 [&>svg]:text-green-600">
                                            <CheckCircle className="size-4" />
                                            <AlertTitle>Sucesso</AlertTitle>
                                            <AlertDescription>
                                                Operação concluída com êxito.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert className="border-yellow-500/50 text-yellow-700 dark:text-yellow-400 [&>svg]:text-yellow-600">
                                            <AlertTriangle className="size-4" />
                                            <AlertTitle>Aviso</AlertTitle>
                                            <AlertDescription>
                                                Atenção — esta ação pode ter consequências.
                                            </AlertDescription>
                                        </Alert>
                                        <Alert variant="destructive">
                                            <AlertCircle className="size-4" />
                                            <AlertTitle>Erro</AlertTitle>
                                            <AlertDescription>
                                                Algo deu errado. Por favor, tente novamente.
                                            </AlertDescription>
                                        </Alert>
                                    </div>
                                }
                                code={`// Informação (padrão)
<Alert>
    <Info className="size-4" />
    <AlertTitle>Informação</AlertTitle>
    <AlertDescription>Mensagem informativa.</AlertDescription>
</Alert>

// Sucesso (com classes customizadas)
<Alert className="border-green-500/50 text-green-700 [&>svg]:text-green-600">
    <CheckCircle className="size-4" />
    <AlertTitle>Sucesso</AlertTitle>
    <AlertDescription>Operação concluída.</AlertDescription>
</Alert>

// Erro (variante destructive)
<Alert variant="destructive">
    <AlertCircle className="size-4" />
    <AlertTitle>Erro</AlertTitle>
    <AlertDescription>Algo deu errado.</AlertDescription>
</Alert>`}
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
                                            <div className="space-y-1.5">
                                                <Label htmlFor="demo-name">Nome</Label>
                                                <Input id="demo-name" placeholder="Digite seu nome" />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="demo-email">Email</Label>
                                                <div className="relative">
                                                    <Mail className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="demo-email"
                                                        type="email"
                                                        placeholder="seu@email.com"
                                                        className="pl-9"
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="demo-search">Busca</Label>
                                                <div className="relative">
                                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="demo-search"
                                                        placeholder="Buscar..."
                                                        className="pl-9 pr-9"
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="absolute right-1 top-1/2 size-7 -translate-y-1/2"
                                                    >
                                                        <X className="size-3" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="demo-invalid" className="text-destructive">
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
                                            <div className="space-y-1.5">
                                                <Label>Desabilitado</Label>
                                                <Input disabled placeholder="Campo desabilitado" />
                                            </div>
                                        </div>
                                    }
                                    code={`// Input simples
<div className="space-y-1.5">
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
                                                <Label htmlFor="chk1">Checkbox padrão</Label>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox id="chk2" defaultChecked />
                                                <Label htmlFor="chk2">Checkbox marcado</Label>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox id="chk3" disabled />
                                                <Label htmlFor="chk3" className="text-muted-foreground">
                                                    Desabilitado
                                                </Label>
                                            </div>
                                            <div className="flex items-start gap-2 rounded-md border p-3">
                                                <Checkbox id="chk4" className="mt-0.5" />
                                                <div>
                                                    <Label htmlFor="chk4" className="font-medium">
                                                        Aceitar termos de uso
                                                    </Label>
                                                    <p className="text-xs text-muted-foreground">
                                                        Li e concordo com os termos e condições.
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
                                            <AvatarFallback className="text-xs">AB</AvatarFallback>
                                        </Avatar>
                                        <Avatar className="size-12">
                                            <AvatarFallback>CD</AvatarFallback>
                                        </Avatar>
                                        <Avatar className="size-16">
                                            <AvatarFallback className="text-xl">EF</AvatarFallback>
                                        </Avatar>
                                        <div className="flex -space-x-2">
                                            {['GH', 'IJ', 'KL', 'ML'].map((i) => (
                                                <Avatar key={i} className="ring-2 ring-background">
                                                    <AvatarFallback className="text-xs">{i}</AvatarFallback>
                                                </Avatar>
                                            ))}
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
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="outline" size="icon">
                                                    <Bell />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Notificações</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="outline" size="icon">
                                                    <User />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">Perfil do usuário</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="outline" size="icon">
                                                    <Settings />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent side="bottom">Configurações</TooltipContent>
                                        </Tooltip>
                                    </>
                                }
                                code={`<Tooltip>
    <TooltipTrigger asChild>
        <Button variant="outline" size="icon">
            <Bell />
        </Button>
    </TooltipTrigger>
    <TooltipContent>Notificações</TooltipContent>
</Tooltip>

// Posições: top (padrão), right, bottom, left
<TooltipContent side="right">Perfil do usuário</TooltipContent>`}
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
                                                    <h3 className="text-lg font-semibold">Seção com Ação</h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Padrão de header interno com botão.
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
                                        <CardTitle className="text-base">Configurações</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Variante
                                            </Label>
                                            <div className="flex flex-wrap gap-2">
                                                {(['default', 'secondary', 'outline', 'ghost', 'destructive', 'link'] as const).map((v) => (
                                                    <button
                                                        key={v}
                                                        onClick={() => setBtnVariant(v)}
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
                                            <Label className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Tamanho
                                            </Label>
                                            <div className="flex gap-2">
                                                {(['sm', 'default', 'lg'] as const).map((s) => (
                                                    <button
                                                        key={s}
                                                        onClick={() => setBtnSize(s)}
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
                                            <Label className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Opções
                                            </Label>
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="pg-loading"
                                                        checked={btnLoading}
                                                        onCheckedChange={(v) => setBtnLoading(!!v)}
                                                    />
                                                    <Label htmlFor="pg-loading" className="text-sm font-normal">
                                                        Loading
                                                    </Label>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="pg-disabled"
                                                        checked={btnDisabled}
                                                        onCheckedChange={(v) => setBtnDisabled(!!v)}
                                                    />
                                                    <Label htmlFor="pg-disabled" className="text-sm font-normal">
                                                        Disabled
                                                    </Label>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="pg-icon"
                                                        checked={btnIcon}
                                                        onCheckedChange={(v) => setBtnIcon(!!v)}
                                                    />
                                                    <Label htmlFor="pg-icon" className="text-sm font-normal">
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
                                            <CardTitle className="text-base">Preview</CardTitle>
                                        </CardHeader>
                                        <CardContent className="flex min-h-24 items-center justify-center">
                                            <Button
                                                variant={btnVariant}
                                                size={btnSize}
                                                disabled={btnDisabled || btnLoading}
                                            >
                                                {btnLoading && (
                                                    <Loader2 className="animate-spin" />
                                                )}
                                                {btnIcon && !btnLoading && (
                                                    <Sun />
                                                )}
                                                {btnLoading ? 'Carregando...' : 'Botão exemplo'}
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

// Classes condicionais para dark
<div className="bg-white dark:bg-zinc-900">
    Conteúdo
</div>`}
                                />
                            </div>
                        </Section>

                        <div className="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            <Info className="size-4 shrink-0" />
                            <span>
                                Componentes adicionais como <strong>DataTable</strong>, <strong>AsyncSelect</strong> e{' '}
                                <strong>InlineCellEdit</strong> possuem páginas dedicadas na seção de Components.
                            </span>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}

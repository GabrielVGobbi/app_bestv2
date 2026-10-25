import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    Bell,
    BellRing,
    History,
    FileText,
    Layers,
    Mail,
    MessageSquare,
    PencilLine,
    Puzzle,
    Search,
    Type,
} from 'lucide-react';

import {
    asyncSelect,
    activityActions,
    activityTimeline,
    components,
    designSystem,
    email,
    formPlayground,
    inlineCellEdit,
    notifications,
    richTextEditor,
    toasts,
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

const componentItems = [
    {
        title: 'Design System',
        description:
            'Referência visual de todos os componentes, tipografia, cores, botões e playground interativo.',
        href: designSystem.url(),
        status: 'Pronto',
        icon: Layers,
    },
    {
        title: 'Inline cell edit',
        description:
            'Editor de celula para usar dentro das colunas do DataTable.',
        href: inlineCellEdit.url(),
        status: 'Pronto',
        icon: PencilLine,
    },
    {
        title: 'Async select',
        description:
            'Select com busca remota, modo estatico, seleção única e múltipla.',
        href: asyncSelect.url(),
        status: 'Pronto',
        icon: Search,
    },
    {
        title: 'Form playground',
        description:
            'Galeria de FormField, formularios em card, inline, filtros e estados de validação.',
        href: formPlayground.url(),
        status: 'Pronto',
        icon: FileText,
    },
    {
        title: 'Toasts',
        description:
            'Bancada para disparar success, error, warning, info, loading, promise e action.',
        href: toasts.url(),
        status: 'Pronto',
        icon: Bell,
    },
    {
        title: 'Notifications',
        description:
            'Módulo completo de notificações: tipos, categorias, ações executáveis, menções e bell no header.',
        href: notifications.url(),
        status: 'Pronto',
        icon: BellRing,
    },
    {
        title: 'Activity Actions',
        description:
            'Catálogo configurável para auditar login, logout e ações de domínio com playground de logs reais.',
        href: activityActions.url(),
        status: 'Pronto',
        icon: History,
    },
    {
        title: 'Activity Timeline',
        description:
            'Timeline reutilizável para comentários, replies, anexos e eventos de auditoria por model.',
        href: activityTimeline.url(),
        status: 'Novo',
        icon: MessageSquare,
    },
    {
        title: 'Rich Text Editor',
        description:
            'Editor WYSIWYG headless (TipTap). Toolbar completa, upload de imagem, link dialog e tabela polimórfica para conteúdo rico.',
        href: richTextEditor.url(),
        status: 'Pronto',
        icon: Type,
    },
    {
        title: 'Email',
        description:
            'Módulo de envio assíncrono: kill switch, idempotência, retry automático, webhooks de entrega e playground de testes.',
        href: email.url(),
        status: 'Pronto',
        icon: Mail,
    },
];

export default function ComponentsIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Components', href: components.url() }],
    });

    return (
        <>
            <Head title="Components" />

            <Header
                title="Components"
                subtitle="Catálogo interno para organizar componentes, exemplos e decisões de uso."
            />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {componentItems.map((item) => (
                            <Link key={item.title} href={item.href} prefetch>
                                <Card className="h-full rounded-lg transition-colors hover:border-primary/50 hover:bg-muted/30">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex size-10 items-center justify-center rounded-md border bg-background">
                                                <item.icon className="size-5 text-primary" />
                                            </div>
                                            <Badge variant="secondary">
                                                {item.status}
                                            </Badge>
                                        </div>
                                        <CardTitle>{item.title}</CardTitle>
                                        <CardDescription>
                                            {item.description}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="text-sm text-muted-foreground">
                                        Abrir documentacao
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}

                        <Card className="h-full rounded-lg border-dashed bg-muted/20">
                            <CardHeader>
                                <div className="flex size-10 items-center justify-center rounded-md border bg-background">
                                    <Puzzle className="size-5 text-muted-foreground" />
                                </div>
                                <CardTitle>Proximos componentes</CardTitle>
                                <CardDescription>
                                    Novas entradas entram aqui com demo, API e
                                    exemplos de integração.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

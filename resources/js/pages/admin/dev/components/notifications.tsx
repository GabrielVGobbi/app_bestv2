import { Head, setLayoutProps } from '@inertiajs/react';
import { Bell, BellRing, BookOpen, Code2, Layers, Puzzle, Zap } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import {
    components,
    notifications,
    seedNotification,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Heading from '@/components/heading';
import { NotificationBell } from '@/components/notification-bell';
import { NotificationItem } from '@/components/notification-item';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useNotifications } from '@/hooks/use-notifications';
import { httpPost } from '@/lib/http';

const SEED_TYPES = [
    { type: 'system', label: 'Sistema', description: 'Exportação concluída, aviso de sistema', color: 'text-muted-foreground' },
    { type: 'user', label: 'Usuário', description: 'Comentário de outro usuário', color: 'text-blue-500' },
    { type: 'mention', label: 'Menção', description: 'Você foi mencionado @usuario', color: 'text-purple-500' },
    { type: 'social', label: 'Social', description: 'Novo seguidor, curtida', color: 'text-pink-500' },
    { type: 'action', label: 'Ação', description: 'Solicitação com Aprovar / Recusar', color: 'text-orange-500' },
    { type: 'warning', label: 'Aviso', description: 'Alerta de segurança', color: 'text-yellow-500' },
];

const createBackendCode = `use App\\Modules\\Notifications\\Actions\\CreateNotificationAction;
use App\\Modules\\Notifications\\DTOs\\CreateNotificationDTO;
use App\\Modules\\Notifications\\Enums\\NotificationCategory;

// Sistema
app(CreateNotificationAction::class)->handle(
    CreateNotificationDTO::system(
        recipientId: $user->id,
        title: 'Exportação concluída',
        body: 'Seu arquivo está pronto para download.',
        category: NotificationCategory::EXPORT,
        data: ['file_name' => 'relatorio.xlsx'],
    )
);

// Entre usuários
app(CreateNotificationAction::class)->handle(
    CreateNotificationDTO::fromUser(
        recipientId: $targetUser->id,
        senderId: auth()->id(),
        title: 'Novo comentário',
        body: auth()->user()->name . ' comentou em sua requisição.',
        category: NotificationCategory::COMMENT,
    )
);`;

const mentionCode = `use App\\Modules\\Notifications\\Services\\NotificationService;

// Dentro de um controller ou service que salva um comentário:
$notificationService = app(NotificationService::class);

$notificationService->notifyMentions(
    text: $comment->body,           // "@joao aprove isso por favor"
    author: auth()->user(),         // não notifica o próprio autor
    bodyTemplate: ':author mencionou você em um comentário.',
    link: route('requests.show', $request),
    notifiableType: Request::class,
    notifiableId: $request->id,
);`;

const actionCode = `// Notificação com botões executáveis
CreateNotificationDTO::withActions(
    recipientId: $assignee->id,
    title: 'Nova solicitação pendente',
    body: 'Você tem uma solicitação aguardando sua aprovação.',
    actions: [
        [
            'label' => 'Aprovar',
            'type' => 'approve_request',       // mapeado em NotificationActionResolver
            'variant' => 'primary',
            'requires_confirmation' => true,
            'confirmation_message' => 'Confirmar aprovação?',
        ],
        [
            'label' => 'Recusar',
            'type' => 'reject_request',
            'variant' => 'destructive',
            'requires_confirmation' => true,
            'confirmation_message' => 'Tem certeza que deseja recusar?',
        ],
    ],
    category: NotificationCategory::TASK,
    notifiableType: Request::class,
    notifiableId: $request->id,
)`;

const resolverCode = `// app/Modules/Notifications/Services/NotificationActionResolver.php
// Adicionar novo tipo de ação:

protected array $map = [
    'approve_request'  => ApproveRequestNotificationAction::class,
    'reject_request'   => RejectRequestNotificationAction::class,
    'download_export'  => DownloadExportNotificationAction::class,
    'confirm_payment'  => ConfirmPaymentNotificationAction::class,  // novo
];`;

const realtimeCode = `// TODO: quando Laravel Reverb/Echo for configurado:

// 1. Implementar ShouldBroadcast em app/Events/NotificationCreated.php
// 2. Descomentar em CreateNotificationAction::handle():
//    broadcast(new NotificationCreated($notification))->toOthers();

// 3. Frontend — assinar no canal privado do usuário:
// window.Echo.private(\`user.\${userId}.notifications\`)
//     .listen('NotificationCreated', (e) => {
//         setUnreadCount(prev => prev + 1)
//         setRecentNotifications(prev => [e.notification, ...prev])
//     })

// Canal esperado: private-user.{recipientId}.notifications`;

function CodeBlock({ code }: { code: string }) {
    return (
        <pre className="overflow-x-auto rounded-lg border bg-muted/50 p-4 text-xs leading-relaxed">
            <code>{code}</code>
        </pre>
    );
}

export default function NotificationsPlayground() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Notifications', href: notifications.url() },
        ],
    });

    const [seeding, setSeeding] = useState<string | null>(null);
    const {
        notifications: notificationList,
        unreadCount,
        loading,
        refresh,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        executeAction,
    } = useNotifications();

    const seed = async (type: string) => {
        setSeeding(type);

        try {
            await httpPost(seedNotification.url(), { type });
            await refresh();
            toast.success(`Notificação "${type}" criada com sucesso.`);
        } catch {
            toast.error('Erro ao criar notificação de teste.');
        } finally {
            setSeeding(null);
        }
    };

    return (
        <>
            <Head title="Notifications playground" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <Heading
                            title="Notifications"
                            description="Módulo completo: tipos, categorias, menções, ações executáveis e bell no header."
                        />
                        <Badge variant="secondary" className="w-fit">
                            app/Modules/Notifications
                        </Badge>
                    </div>

                    {/* Feature cards */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Layers className="size-4" />
                                    9 tipos + 14 categorias
                                </CardTitle>
                                <CardDescription>Enums tipados para evitar strings soltas no código.</CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Zap className="size-4" />
                                    Ações executáveis
                                </CardTitle>
                                <CardDescription>
                                    Botões nas notificações que disparam lógica real no backend via NotificationActionResolver.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Puzzle className="size-4" />
                                    Preparado para realtime
                                </CardTitle>
                                <CardDescription>
                                    Estrutura pronta para Laravel Reverb/Echo — só descomentar o broadcast.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>

                    {/* Seed section */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BellRing className="size-5" />
                                Criar notificações de teste
                            </CardTitle>
                            <CardDescription>
                                Clique para criar notificações reais para o seu usuário. A lista ao lado atualiza automaticamente.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {SEED_TYPES.map((item) => (
                                    <Button
                                        key={item.type}
                                        variant="outline"
                                        size="sm"
                                        disabled={seeding !== null}
                                        onClick={() => void seed(item.type)}
                                        className="flex flex-col h-auto px-4 py-2 gap-0.5"
                                    >
                                        <span className={`text-xs font-semibold ${item.color}`}>{item.label}</span>
                                        <span className="text-[10px] text-muted-foreground font-normal">{item.description}</span>
                                    </Button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
                        {/* Live list */}
                        <Card className="rounded-lg">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <Bell className="size-5" />
                                        Lista ao vivo
                                        {unreadCount > 0 && (
                                            <Badge variant="secondary">{unreadCount} não lidas</Badge>
                                        )}
                                    </CardTitle>
                                    <div className="flex gap-2">
                                        {unreadCount > 0 && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => void markAllAsRead()}
                                            >
                                                Marcar todas como lidas
                                            </Button>
                                        )}
                                        <Button size="sm" variant="ghost" onClick={refresh} disabled={loading}>
                                            Atualizar
                                        </Button>
                                    </div>
                                </div>
                                <CardDescription>
                                    Suas notificações. Teste marcar como lida, excluir e executar ações.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {loading ? (
                                    <div className="space-y-2">
                                        {[1, 2, 3].map((i) => (
                                            <div key={i} className="flex gap-3 rounded-lg border p-3">
                                                <Skeleton className="size-8 shrink-0 rounded-full" />
                                                <div className="flex-1 space-y-2">
                                                    <Skeleton className="h-3.5 w-1/3" />
                                                    <Skeleton className="h-3 w-2/3" />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : notificationList.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-10 text-center">
                                        <Bell className="size-8 text-muted-foreground/30" />
                                        <p className="text-sm text-muted-foreground">
                                            Nenhuma notificação. Use os botões acima para criar.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col gap-1">
                                        {notificationList.map((n) => (
                                            <NotificationItem
                                                key={n.uuid}
                                                notification={n}
                                                onMarkAsRead={(uuid) => void markAsRead(uuid)}
                                                onDelete={(uuid) => void deleteNotification(uuid)}
                                                onExecuteAction={executeAction}
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Bell preview */}
                        <div className="flex flex-col gap-4">
                            <Card className="rounded-lg">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <BellRing className="size-4" />
                                        Bell preview
                                    </CardTitle>
                                    <CardDescription>
                                        O componente exato que aparece no header. Clique para abrir.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center justify-center rounded-lg border bg-muted/20 p-6">
                                        <NotificationBell />
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        O badge atualiza conforme você cria e lê notificações acima.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Code examples */}
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Code2 className="size-5" />
                                    Criar notificações no backend
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={createBackendCode} />
                                <CodeBlock code={mentionCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Zap className="size-5" />
                                    Ações executáveis
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={actionCode} />
                                <CodeBlock code={resolverCode} />
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BookOpen className="size-5" />
                                Realtime — próximos passos
                            </CardTitle>
                            <CardDescription>
                                A estrutura está pronta. Para habilitar push em tempo real basta seguir os passos abaixo.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <CodeBlock code={realtimeCode} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

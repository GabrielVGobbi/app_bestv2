import { Head, setLayoutProps } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertCircle,
    BookOpen,
    CheckCircle2,
    Code2,
    Eye,
    FileText,
    Loader2,
    Mail,
    Play,
    Power,
    Send,
    Settings,
    Zap,
    X,
} from 'lucide-react';
import { useState } from 'react';

import {
    components,
    email,
    previewEmail,
    sendTestEmail,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface EmailLog {
    uuid: string;
    to_email: string;
    subject: string;
    status: string;
    category: string;
    created_at: string;
}

interface Props {
    recentLogs: EmailLog[];
    settings: Record<string, unknown>;
}

// ── Code snippets ─────────────────────────────────────────────────────────────

const usageBasicCode = `use App\\Modules\\Email\\Contracts\\EmailServiceInterface;
use App\\Modules\\Email\\DTOs\\SendEmailDTO;
use App\\Modules\\Email\\Enums\\EmailCategory;

// Enfileira para processamento assíncrono (recomendado)
$log = app(EmailServiceInterface::class)->queue(new SendEmailDTO(
    to: 'user@example.com',
    subject: 'Assunto do email',
    template: 'emails.exports.ready',
    category: EmailCategory::Export,
    variables: [
        'userName'    => 'Gabriel',
        'downloadUrl' => 'https://app.test/download/xyz',
        'expiresAt'   => '01/01/2027 23:59',
    ],
));

// Envio síncrono (usar só quando necessário)
$log = app(EmailServiceInterface::class)->sendNow($dto);`;

const dtoPropCode = `readonly class SendEmailDTO {
    public function __construct(
        public string        $to,            // destinatário
        public string        $subject,       // assunto
        public string        $template,      // view Blade: 'emails.exports.ready'
        public EmailCategory $category,      // kill switch por módulo
        public array         $variables = [],// variáveis injetadas na view
        public ?string       $toName = null,
        public ?string       $replyTo = null,
        public ?string       $notifiableType = null,
        public ?int          $notifiableId = null,
        public ?string       $idempotencyKey = null, // evita duplicatas em retry
    ) {}

    public function withTo(string $email, ?string $name = null): self { ... }
}`;

const killSwitchCode = `// Runtime, sem deploy: persiste em DB + cache (60s TTL)
use App\\Support\\AppSettings;

// Ler
AppSettings::get('email', 'enabled', true);
AppSettings::get('email', 'export_enabled', true);

// Escrever
AppSettings::set('email', 'enabled', false);        // bloqueia TUDO
AppSettings::set('email', 'export_enabled', false); // bloqueia só Export

// Grupos disponíveis: enabled, auth_enabled, export_enabled,
//                     notification_enabled, system_enabled`;

const idempotencyCode = `// Chave explícita: evita reenvio em retries de job ou reprocessamento
$dto = new SendEmailDTO(
    to: 'user@example.com',
    subject: 'Exportação pronta',
    template: 'emails.exports.ready',
    category: EmailCategory::Export,
    idempotencyKey: "export-{$export->id}-ready",
);

// Se omitido, a chave é gerada automaticamente como:
// sha256(to + template + notifiableId + date)`;

const webhookCode = `// Registrar provider customizado
use App\\Modules\\Email\\Services\\EmailWebhookService;

EmailWebhookService::register('myProvider', MyWebhookHandler::class);

// Handler deve implementar WebhookHandlerInterface
class MyWebhookHandler implements WebhookHandlerInterface
{
    public function verifySignature(Request $request): bool
    {
        return hash_equals(
            config('services.my_provider.webhook_secret'),
            $request->header('X-Webhook-Token', ''),
        );
    }

    public function handle(Request $request): void
    {
        $event = $request->input('event');
        $messageId = $request->input('message_id');

        $log = EmailLog::where('message_id', $messageId)->first();
        if (! $log) { return; }

        match ($event) {
            'delivered' => $log->markAsDelivered(),
            'bounced'   => $log->markAsBounced($request->input('reason')),
            default     => null,
        };
    }
}`;

const templateCode = `{{-- resources/views/emails/exports/ready.blade.php --}}
@extends('emails.layouts.base', [
    'preheaderText' => 'Sua exportação está pronta para download.',
])

@section('content')
    <p>Olá, {{ $userName }}!</p>
    <p>Sua exportação foi concluída e está pronta para download.</p>

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 32px 0;">
                <a href="{{ $downloadUrl }}" class="button">
                    Baixar arquivo
                </a>
            </td>
        </tr>
    </table>

    <p style="color: #6b7280; font-size: 14px;">
        O link expira em: <strong>{{ $expiresAt }}</strong>
    </p>
@endsection`;

const routeCode = `// web.php: UI de logs
Route::middleware('auth')->group(function () {
    Route::get('/email-logs', [EmailLogController::class, 'index'])
        ->name('email-logs.index');
    Route::post('/email-logs/{log:uuid}/resend', [EmailLogController::class, 'resend'])
        ->name('email-logs.resend');
    Route::patch('/email-logs/settings', [EmailLogController::class, 'updateSettings'])
        ->name('email-logs.settings.update');
});

// api.php: webhooks (público, sem auth)
Route::post('/email/webhook/{provider}', [EmailWebhookController::class, 'handle']);`;

// ── Helpers ───────────────────────────────────────────────────────────────────

function statusColor(status: string): string {
    const map: Record<string, string> = {
        queued: 'bg-info-soft text-info-soft-foreground',
        processing: 'bg-warning-soft text-warning-soft-foreground',
        sent: 'bg-success-soft text-success-soft-foreground',
        delivered: 'bg-success-soft text-success-soft-foreground',
        failed: 'bg-destructive-soft text-destructive-soft-foreground',
        bounced: 'bg-warning-soft text-warning-soft-foreground',
        delivery_delayed: 'bg-warning-soft text-warning-soft-foreground',
        complained: 'bg-destructive-soft text-destructive-soft-foreground',
        opened: 'bg-info-soft text-info-soft-foreground',
        clicked: 'bg-primary-soft text-primary-soft-foreground',
        received: 'bg-info-soft text-info-soft-foreground',
        scheduled: 'bg-muted text-muted-foreground',
        suppressed: 'bg-muted text-muted-foreground',
    };

    return map[status] ?? 'bg-muted text-muted-foreground';
}

// ── Sub-components ────────────────────────────────────────────────────────────

function CodeBlock({ code }: { code: string }) {
    return (
        <pre className="overflow-x-auto rounded-lg border bg-muted/50 p-4 text-xs leading-relaxed">
            <code>{code}</code>
        </pre>
    );
}

function FeatureCard({
    icon: Icon,
    title,
    description,
}: {
    icon: React.ElementType;
    title: string;
    description: string;
}) {
    return (
        <Card className="rounded-lg">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Icon className="size-4" />
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
        </Card>
    );
}

function PropRow({
    name,
    type,
    description,
    required = false,
}: {
    name: string;
    type: string;
    description: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-1 border-b py-3 last:border-b-0">
            <div className="flex flex-wrap items-center gap-2">
                <code className="text-sm font-semibold text-primary">
                    {name}
                </code>
                {required && <Badge variant="outline">obrigatório</Badge>}
            </div>
            <code className="w-fit rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                {type}
            </code>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function EmailPage({ recentLogs, settings }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Email', href: email.url() },
        ],
    });

    const [to, setTo] = useState('');
    const [subject, setSubject] = useState('Teste de envio');
    const [template, setTemplate] = useState('emails.exports.ready');
    const [category, setCategory] = useState('export');
    const [variables, setVariables] = useState(
        JSON.stringify(
            {
                userName: 'Gabriel',
                downloadUrl: 'https://example.com/download/xyz',
                expiresAt: '01/01/2027 23:59',
            },
            null,
            2,
        ),
    );
    const [sending, setSending] = useState(false);
    const [result, setResult] = useState<{
        success: boolean;
        message: string;
        uuid?: string;
    } | null>(null);

    const [previewing, setPreviewing] = useState(false);
    const [previewHtml, setPreviewHtml] = useState<string | null>(null);

    const globalEnabled = settings['enabled'] !== false;

    async function handlePreview() {
        setPreviewing(true);

        try {
            const response = await axios.post<string>(previewEmail.url(), {
                template,
                variables,
            });
            setPreviewHtml(response.data);
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } } };
            const msg = error.response?.data?.message ?? 'Erro ao renderizar.';
            setPreviewHtml(
                `<html><body style="font-family:monospace;padding:24px;color:#dc2626"><strong>Erro:</strong> ${msg}</body></html>`,
            );
        } finally {
            setPreviewing(false);
        }
    }

    async function handleSend() {
        setSending(true);
        setResult(null);

        try {
            const response = await axios.post(sendTestEmail.url(), {
                to,
                subject,
                template,
                category,
                variables,
            });
            setResult(response.data);
        } catch (err: unknown) {
            const error = err as {
                response?: { data?: { message?: string } };
            };
            setResult({
                success: false,
                message:
                    error.response?.data?.message ?? 'Erro ao enviar email.',
            });
        } finally {
            setSending(false);
        }
    }

    return (
        <>
            <Head title="E-mail" />

            <Header
                title="E-mail"
                subtitle="Módulo de envio assíncrono com kill switch, idempotência, retry automático e webhooks de entrega."
            >
                <Badge variant="secondary" className="w-fit">
                    App/Modules/Email
                </Badge>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    {/* Header */}

                    {/* Feature cards */}
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <FeatureCard
                            icon={Zap}
                            title="Async + retry"
                            description="Job com 3 tentativas e backoff exponencial (30s → 2min → 5min)."
                        />
                        <FeatureCard
                            icon={Power}
                            title="Kill switch"
                            description="Bloqueia tudo ou por módulo em runtime via AppSettings (DB + cache)."
                        />
                        <FeatureCard
                            icon={CheckCircle2}
                            title="Idempotência"
                            description="Chave SHA-256 evita duplicatas em retries. Fallback automático para falhos."
                        />
                        <FeatureCard
                            icon={FileText}
                            title="Webhooks"
                            description="Strategy por provider (Resend, SES, Postmark). Atualiza status no EmailLog."
                        />
                    </div>

                    {/* Kill switch status */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Power className="size-5" />
                                Status do kill switch
                            </CardTitle>
                            <CardDescription>
                                Lido de AppSettings em tempo real. Altere em{' '}
                                <a
                                    href="/email-logs"
                                    className="underline underline-offset-2"
                                >
                                    /email-logs
                                </a>{' '}
                                → painel de configurações.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-3">
                                {[
                                    { key: 'enabled', label: 'Global' },
                                    { key: 'auth_enabled', label: 'Auth' },
                                    {
                                        key: 'export_enabled',
                                        label: 'Export',
                                    },
                                    {
                                        key: 'notification_enabled',
                                        label: 'Notification',
                                    },
                                    {
                                        key: 'system_enabled',
                                        label: 'System',
                                    },
                                ].map(({ key, label }) => {
                                    const isOn = settings[key] !== false;

                                    return (
                                        <div
                                            key={key}
                                            className="flex items-center gap-2 rounded-lg border px-3 py-2"
                                        >
                                            <span
                                                className={`size-2 rounded-full ${isOn ? 'bg-success' : 'bg-destructive'}`}
                                            />
                                            <span className="text-sm font-medium">
                                                {label}
                                            </span>
                                            <Badge
                                                variant={
                                                    isOn
                                                        ? 'secondary'
                                                        : 'destructive'
                                                }
                                                className="text-xs"
                                            >
                                                {isOn ? 'ativo' : 'bloqueado'}
                                            </Badge>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Playground */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="size-5" />
                                Playground: envio de teste
                            </CardTitle>
                            <CardDescription>
                                Enfileira um email real via{' '}
                                <code className="text-xs">
                                    EmailServiceInterface::queue()
                                </code>
                                . Verifique o mailer configurado no{' '}
                                <code className="text-xs">.env</code> (
                                <code className="text-xs">MAIL_MAILER=log</code>{' '}
                                escreve em{' '}
                                <code className="text-xs">
                                    storage/logs/laravel.log
                                </code>
                                ).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {!globalEnabled && (
                                <div className="flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                                    <AlertCircle className="size-4 shrink-0" />
                                    Kill switch global está DESATIVADO. Nenhum
                                    email será enviado.
                                </div>
                            )}

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="pt-to">
                                        Destinatário (to)
                                    </Label>
                                    <Input
                                        id="pt-to"
                                        type="email"
                                        placeholder="dev@example.com"
                                        value={to}
                                        onChange={(e) => setTo(e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pt-subject">Assunto</Label>
                                    <Input
                                        id="pt-subject"
                                        placeholder="Assunto do email"
                                        value={subject}
                                        onChange={(e) =>
                                            setSubject(e.target.value)
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pt-template">
                                        Template (view Blade)
                                    </Label>
                                    <Select
                                        value={template}
                                        onValueChange={setTemplate}
                                    >
                                        <SelectTrigger id="pt-template" />
                                        <SelectContent>
                                            <SelectItem
                                                index={0}
                                                value="emails.exports.ready"
                                            >
                                                emails.exports.ready
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pt-category">
                                        Categoria
                                    </Label>
                                    <Select
                                        value={category}
                                        onValueChange={setCategory}
                                    >
                                        <SelectTrigger id="pt-category" />
                                        <SelectContent>
                                            <SelectItem
                                                index={0}
                                                value="export"
                                            >
                                                export
                                            </SelectItem>
                                            <SelectItem index={1} value="auth">
                                                auth
                                            </SelectItem>
                                            <SelectItem
                                                index={2}
                                                value="notification"
                                            >
                                                notification
                                            </SelectItem>
                                            <SelectItem
                                                index={3}
                                                value="system"
                                            >
                                                system
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="pt-variables">
                                    Variáveis (JSON)
                                </Label>
                                <Textarea
                                    id="pt-variables"
                                    className="min-h-20 font-mono text-xs"
                                    rows={6}
                                    value={variables}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLTextAreaElement>,
                                    ) => setVariables(e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Injetadas na view Blade como variáveis PHP.
                                    JSON inválido é ignorado.
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    variant="outline"
                                    onClick={handlePreview}
                                    disabled={previewing || !template}
                                >
                                    {previewing ? (
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <Eye className="mr-2 size-4" />
                                    )}
                                    {previewing ? 'Gerando…' : 'Pré-visualizar'}
                                </Button>

                                <Button
                                    onClick={handleSend}
                                    disabled={
                                        sending || !to || !subject || !template
                                    }
                                >
                                    <Send className="mr-2 size-4" />
                                    {sending
                                        ? 'Enviando…'
                                        : 'Enviar email de teste'}
                                </Button>

                                {result && (
                                    <div
                                        className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm ${
                                            result.success
                                                ? 'border-success-soft-foreground/20 bg-success-soft text-success-soft-foreground'
                                                : 'border-destructive/30 bg-destructive/10 text-destructive'
                                        }`}
                                    >
                                        {result.success ? (
                                            <CheckCircle2 className="size-4 shrink-0" />
                                        ) : (
                                            <AlertCircle className="size-4 shrink-0" />
                                        )}
                                        <span>{result.message}</span>
                                        {result.uuid && (
                                            <code className="text-xs opacity-70">
                                                {result.uuid}
                                            </code>
                                        )}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Preview */}
                    {previewHtml !== null && (
                        <Card className="rounded-lg">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <Eye className="size-5" />
                                        Pré-visualização do template
                                    </CardTitle>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant="secondary"
                                            className="text-xs"
                                        >
                                            {template}
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setPreviewHtml(null)}
                                        >
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Fechar
                                        </Button>
                                    </div>
                                </div>
                                <CardDescription>
                                    Renderizado no servidor com as variáveis
                                    atuais. Atualiza ao clicar em Pré-visualizar
                                    novamente.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-hidden rounded-b-lg border-t">
                                    <iframe
                                        title="Pré-visualização do e-mail"
                                        className="h-[600px] w-full bg-white"
                                        srcDoc={previewHtml ?? ''}
                                        sandbox="allow-scripts allow-same-origin"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Recent logs */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="size-5" />
                                Logs recentes
                            </CardTitle>
                            <CardDescription>
                                Últimos 5 registros em email_logs.{' '}
                                <a
                                    href="/email-logs"
                                    className="underline underline-offset-2"
                                >
                                    Ver todos →
                                </a>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentLogs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nenhum email registrado ainda.
                                </p>
                            ) : (
                                <div className="divide-y">
                                    {recentLogs.map((log) => (
                                        <div
                                            key={log.uuid}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2.5"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {log.subject}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {log.to_email} ·{' '}
                                                    {log.category}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs font-medium ${statusColor(log.status)}`}
                                                >
                                                    {log.status}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {new Date(
                                                        log.created_at,
                                                    ).toLocaleString('pt-BR', {
                                                        day: '2-digit',
                                                        month: '2-digit',
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Code examples */}
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Play className="size-5" />
                                    Uso básico
                                </CardTitle>
                                <CardDescription>
                                    Injetar{' '}
                                    <code className="text-xs">
                                        EmailServiceInterface
                                    </code>{' '}
                                    ou usar via{' '}
                                    <code className="text-xs">app()</code>.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={usageBasicCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Power className="size-5" />
                                    Kill switch runtime
                                </CardTitle>
                                <CardDescription>
                                    Sem deploy: persiste em DB e invalida cache
                                    em 60s.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={killSwitchCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle2 className="size-5" />
                                    Idempotência
                                </CardTitle>
                                <CardDescription>
                                    Evita reenvios em retries de job. Emails
                                    falhos com a mesma chave são resetados e
                                    reenviados.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={idempotencyCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-5" />
                                    Template Blade
                                </CardTitle>
                                <CardDescription>
                                    Estende{' '}
                                    <code className="text-xs">
                                        emails.layouts.base
                                    </code>
                                    . HTML inline responsivo, max-width 600px.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={templateCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Zap className="size-5" />
                                    Webhooks de entrega
                                </CardTitle>
                                <CardDescription>
                                    Strategy pattern por provider. Registrar
                                    handler customizado.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={webhookCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Code2 className="size-5" />
                                    Rotas registradas
                                </CardTitle>
                                <CardDescription>
                                    UI de logs + webhooks públicos.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={routeCode} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* API reference */}
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BookOpen className="size-5" />
                                    SendEmailDTO: props
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PropRow
                                    name="to"
                                    type="string"
                                    required
                                    description="Endereço de email do destinatário. Sobrescrito por EMAIL_TO_OVERRIDE em staging."
                                />
                                <PropRow
                                    name="subject"
                                    type="string"
                                    required
                                    description="Assunto do email."
                                />
                                <PropRow
                                    name="template"
                                    type="string"
                                    required
                                    description="Caminho da view Blade. Ex: 'emails.exports.ready'."
                                />
                                <PropRow
                                    name="category"
                                    type="EmailCategory"
                                    required
                                    description="Determina qual kill switch módulo é verificado. Valores: Export, Auth, Notification, System."
                                />
                                <PropRow
                                    name="variables"
                                    type="array"
                                    description="Variáveis injetadas na view Blade via compact()."
                                />
                                <PropRow
                                    name="idempotencyKey"
                                    type="string | null"
                                    description="Chave de idempotência explícita. Se null, gerada como sha256(to|template|notifiableId|date)."
                                />
                                <PropRow
                                    name="toName"
                                    type="string | null"
                                    description="Nome do destinatário para exibição no cliente de email."
                                />
                                <PropRow
                                    name="replyTo"
                                    type="string | null"
                                    description="Endereço de reply-to. Se null, usa o from padrão."
                                />
                                <PropRow
                                    name="notifiableType"
                                    type="string | null"
                                    description="Classe da entidade relacionada. Ex: Export::class. Para rastreamento polimórfico."
                                />
                                <PropRow
                                    name="notifiableId"
                                    type="int | null"
                                    description="ID da entidade relacionada."
                                />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BookOpen className="size-5" />
                                    EmailLog: colunas principais
                                </CardTitle>
                                <CardDescription>
                                    Tabela{' '}
                                    <code className="text-xs">email_logs</code>.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={dtoPropCode} />
                                <div className="mt-4 space-y-0">
                                    <PropRow
                                        name="uuid"
                                        type="string"
                                        description="Identificador único público do log."
                                    />
                                    <PropRow
                                        name="status"
                                        type="queued | processing | sent | failed | bounced | delivered | delivery_delayed | complained | opened | clicked | received | scheduled | suppressed"
                                        description="Ciclo de vida do email. Atualizado pelo job e pelos webhook handlers."
                                    />
                                    <PropRow
                                        name="attempts"
                                        type="int"
                                        description="Número de tentativas de envio (máx 3 via $tries no job)."
                                    />
                                    <PropRow
                                        name="message_id"
                                        type="string | null"
                                        description="ID retornado pelo provider de email. Usado para correlacionar webhooks."
                                    />
                                    <PropRow
                                        name="error_message"
                                        type="string | null"
                                        description="Mensagem da última exceção. Preenchida pelo job em cada falha."
                                    />
                                    <PropRow
                                        name="idempotency_key"
                                        type="string"
                                        description="Unique constraint. Evita inserção duplicada. Emails failed com mesma chave são resetados."
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

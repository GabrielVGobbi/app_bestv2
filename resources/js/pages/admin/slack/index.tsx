import { Head, router, setLayoutProps, useHttp } from '@inertiajs/react';
import {
    AlertCircle,
    Bell,
    BellOff,
    CheckCircle2,
    Eye,
    EyeOff,
    Loader2,
    Send,
    Settings2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import {
    show,
    test,
    update,
} from '@/actions/App/Http/Controllers/Admin/SlackSettingsController';
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

// ─── Types ───────────────────────────────────────────────────────────────────

interface SlackSettings {
    enabled: boolean;
    log_level: string;
}

interface PageProps {
    settings: SlackSettings;
    webhook_configured: boolean;
    isEnabled: boolean;
    levelOptions: Record<string, string>;
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

function StatusBadge({
    isEnabled,
    webhookConfigured,
}: {
    isEnabled: boolean;
    webhookConfigured: boolean;
}) {
    if (isEnabled) {
        return (
            <Badge variant="success" className="gap-1.5">
                <CheckCircle2 className="size-3" />
                Conectado
            </Badge>
        );
    }

    return (
        <Badge variant="secondary" className="gap-1.5">
            <AlertCircle className="size-3" />
            {webhookConfigured ? 'Desativado' : 'Não configurado'}
        </Badge>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function SlackIndex({
    settings,
    webhook_configured: webhookConfigured,
    isEnabled,
    levelOptions,
}: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Configurações Slack', href: show.url() }],
    });

    const [webhookUrl, setWebhookUrl] = useState('');
    const [resetWebhook, setResetWebhook] = useState(false);
    const [enabled, setEnabled] = useState(settings.enabled !== false);
    const [logLevel, setLogLevel] = useState(settings.log_level ?? 'warning');
    const [showWebhook, setShowWebhook] = useState(false);

    const {
        put,
        processing: saving,
        transform,
    } = useHttp<{
        enabled: boolean;
        log_level: string;
        webhook_url?: string;
        reset_webhook?: boolean;
    }>({
        enabled,
        log_level: logLevel,
    });

    const { post, processing: testing } = useHttp({});

    const handleSave = async () => {
        transform(() => ({
            enabled,
            log_level: logLevel,
            ...(webhookUrl.trim() ? { webhook_url: webhookUrl.trim() } : {}),
            ...(resetWebhook ? { reset_webhook: true } : {}),
        }));

        try {
            await put(update.url(), {
                onSuccess: () => {
                    setWebhookUrl('');
                    setResetWebhook(false);
                    router.reload({
                        only: ['settings', 'isEnabled', 'webhook_configured'],
                    });
                    toast.success('Configurações salvas com sucesso.');
                },
            });
        } catch {
            toast.error('Erro ao salvar configurações. Verifique os campos.');
        }
    };

    const hasUnsavedChanges =
        webhookUrl.trim() !== '' ||
        resetWebhook ||
        enabled !== settings.enabled ||
        logLevel !== settings.log_level;

    const handleTest = async () => {
        try {
            await post(test.url(), {
                onSuccess: (response) => {
                    const body = response as {
                        success: boolean;
                        message: string;
                    };

                    if (body.success) {
                        toast.success(body.message);
                    } else {
                        toast.error(body.message);
                    }
                },
            });
        } catch {
            toast.error(
                'Falha ao enviar mensagem de teste. Verifique a webhook URL.',
            );
        }
    };

    return (
        <>
            <Head title="Integração com o Slack" />

            <Header
                title="Integração com o Slack"
                subtitle="Receba alertas, erros e notificações do sistema diretamente no Slack."
            >
                <StatusBadge
                    isEnabled={isEnabled}
                    webhookConfigured={webhookConfigured}
                />
            </Header>

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 pt-6 pb-12 lg:px-6">
                {/* Configurações principais */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Settings2 className="size-4" />
                            Configuração do webhook
                        </CardTitle>
                        <CardDescription>
                            O webhook salvo não é exibido. Preencha uma nova URL
                            apenas para substituí-lo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {/* Enable toggle */}
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                            <Checkbox
                                id="enabled"
                                checked={enabled}
                                disabled={saving}
                                onCheckedChange={(v) => setEnabled(Boolean(v))}
                                aria-label="Ativar Slack"
                            />
                            <div className="space-y-0.5">
                                <Label
                                    htmlFor="enabled"
                                    className="cursor-pointer text-sm font-medium"
                                >
                                    Ativar integração
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Controla os alertas desta integração. O
                                    canal de log Slack possui configuração
                                    própria.
                                </p>
                            </div>
                        </div>

                        <Separator />

                        {/* Webhook URL */}
                        <div className="space-y-2">
                            <Label htmlFor="webhook_url">Webhook URL</Label>
                            <div className="relative">
                                <Input
                                    id="webhook_url"
                                    type={showWebhook ? 'text' : 'password'}
                                    placeholder="https://hooks.slack.com/services/T.../B.../..."
                                    value={webhookUrl}
                                    disabled={saving || resetWebhook}
                                    autoComplete="new-password"
                                    onChange={(e) =>
                                        setWebhookUrl(e.target.value)
                                    }
                                    className="pr-10 font-mono text-sm"
                                />
                                <button
                                    type="button"
                                    disabled={saving || resetWebhook}
                                    onClick={() => setShowWebhook((v) => !v)}
                                    className="absolute inset-y-0 right-3 flex items-center text-muted-foreground transition-colors hover:text-foreground"
                                    aria-label={
                                        showWebhook
                                            ? 'Ocultar URL'
                                            : 'Mostrar URL'
                                    }
                                >
                                    {showWebhook ? (
                                        <EyeOff className="size-4" />
                                    ) : (
                                        <Eye className="size-4" />
                                    )}
                                </button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {webhookConfigured
                                    ? 'Webhook configurado. Deixe vazio para manter a URL salva.'
                                    : 'Nenhum webhook configurado. Preencha uma URL para ativar.'}
                            </p>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="reset_webhook"
                                    checked={resetWebhook}
                                    disabled={saving}
                                    onCheckedChange={(value) => {
                                        setResetWebhook(value === true);

                                        if (value === true) {
                                            setWebhookUrl('');
                                        }
                                    }}
                                />
                                <Label htmlFor="reset_webhook">
                                    Restaurar webhook do ambiente
                                </Label>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Gere em:{' '}
                                <span className="font-mono">
                                    api.slack.com → Your Apps → Incoming
                                    Webhooks
                                </span>
                            </p>
                        </div>

                        {/* Log level */}
                        <div className="space-y-2">
                            <Label htmlFor="log_level">
                                Nível mínimo de alerta
                            </Label>
                            <Select
                                value={logLevel}
                                onValueChange={setLogLevel}
                                disabled={saving}
                            >
                                <SelectTrigger
                                    id="log_level"
                                    className="w-48"
                                    placeholder="Selecione o nível"
                                />
                                <SelectContent>
                                    {Object.entries(levelOptions).map(
                                        ([value, label], levelIndex) => (
                                            <SelectItem
                                                key={value}
                                                index={levelIndex}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Exceções com nível igual ou superior serão
                                enviadas ao Slack.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* O que é monitorado */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Bell className="size-4" />O que é enviado ao Slack
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm text-muted-foreground">
                            <li className="flex items-start gap-2">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success-soft-foreground" />
                                <span>
                                    <strong className="text-foreground">
                                        Exceções não tratadas
                                    </strong>
                                    : erros 500, falhas em jobs de fila e outras
                                    exceções críticas.
                                </span>
                            </li>
                            <li className="flex items-start gap-2">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success-soft-foreground" />
                                <span>
                                    <strong className="text-foreground">
                                        Alertas de log
                                    </strong>
                                    : via{' '}
                                    <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                        Log::warning()
                                    </code>
                                    ,{' '}
                                    <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                        Log::error()
                                    </code>{' '}
                                    etc., quando{' '}
                                    <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                        LOG_STACK=single,slack
                                    </code>{' '}
                                    está ativo.
                                </span>
                            </li>
                            <li className="flex items-start gap-2">
                                <BellOff className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span>
                                    <strong className="text-foreground">
                                        Ignorados automaticamente:
                                    </strong>{' '}
                                    erros 404, erros de validação, falhas de
                                    autenticação e erros HTTP abaixo de 500.
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex items-center justify-between gap-3 pt-2">
                    <Button
                        variant="outline"
                        onClick={() => void handleTest()}
                        disabled={
                            testing || saving || hasUnsavedChanges || !isEnabled
                        }
                        className="gap-2"
                    >
                        {testing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Send className="size-4" />
                        )}
                        Enviar mensagem de teste
                    </Button>

                    <Button
                        onClick={() => void handleSave()}
                        disabled={saving}
                        className="gap-2"
                    >
                        {saving ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <CheckCircle2 className="size-4" />
                        )}
                        Salvar configurações
                    </Button>
                </div>
            </div>
        </>
    );
}

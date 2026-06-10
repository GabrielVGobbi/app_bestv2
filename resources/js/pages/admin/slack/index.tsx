import { Head, setLayoutProps, useHttp } from '@inertiajs/react';
import { AlertCircle, Bell, BellOff, CheckCircle2, Eye, EyeOff, Loader2, Send, Settings2, Slack } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { show, test, update } from '@/actions/App/Http/Controllers/Admin/SlackSettingsController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

// ─── Types ───────────────────────────────────────────────────────────────────

interface SlackSettings {
    webhook_url?: string;
    enabled?: boolean;
    log_level?: string;
}

interface PageProps {
    settings: SlackSettings;
    isEnabled: boolean;
    levelOptions: Record<string, string>;
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

function StatusBadge({ isEnabled }: { isEnabled: boolean }) {
    if (isEnabled) {
        return (
            <Badge className="gap-1.5 bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20 dark:text-emerald-400">
                <CheckCircle2 className="size-3" />
                Conectado
            </Badge>
        );
    }
    return (
        <Badge variant="secondary" className="gap-1.5">
            <AlertCircle className="size-3" />
            Não configurado
        </Badge>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function SlackIndex({ settings, isEnabled, levelOptions }: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Configurações Slack', href: show.url() }],
    });

    const [webhookUrl, setWebhookUrl] = useState(settings.webhook_url ?? '');
    const [enabled, setEnabled] = useState(settings.enabled !== false);
    const [logLevel, setLogLevel] = useState(settings.log_level ?? 'warning');
    const [showWebhook, setShowWebhook] = useState(false);

    const { put, processing: saving, transform } = useHttp({
        webhook_url: webhookUrl,
        enabled,
        log_level: logLevel,
    });

    const { post, processing: testing } = useHttp({});

    const handleSave = () => {
        transform(() => ({ webhook_url: webhookUrl, enabled, log_level: logLevel }));

        put(update.url(), {
            onSuccess: () => toast.success('Configurações salvas com sucesso.'),
            onError: () => toast.error('Erro ao salvar configurações. Verifique os campos.'),
        });
    };

    const handleTest = () => {
        post(test.url(), {
            onSuccess: (response) => {
                const body = response as { success: boolean; message: string };
                if (body.success) {
                    toast.success(body.message);
                } else {
                    toast.error(body.message);
                }
            },
            onError: () => toast.error('Falha ao enviar mensagem de teste. Verifique a webhook URL.'),
        });
    };

    return (
        <>
            <Head title="Configurações Slack" />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 pb-12 pt-6 lg:px-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <div className="flex size-12 items-center justify-center rounded-xl bg-linear-to-br from-purple-500/20 to-purple-500/5 ring-1 ring-purple-500/20">
                        <Slack className="size-6 text-purple-500" />
                    </div>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold tracking-tight">Integração Slack</h1>
                        <p className="text-muted-foreground text-sm">
                            Receba alertas, erros e notificações do sistema diretamente no Slack.
                        </p>
                    </div>
                    <StatusBadge isEnabled={isEnabled} />
                </div>

                {/* Configurações principais */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Settings2 className="size-4" />
                            Configuração do Webhook
                        </CardTitle>
                        <CardDescription>
                            Crie um Incoming Webhook no Slack e cole a URL abaixo. A URL é mantida de forma segura no banco de
                            dados e nunca exposta publicamente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {/* Enable toggle */}
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                            <Checkbox
                                id="enabled"
                                checked={enabled}
                                onCheckedChange={(v) => setEnabled(Boolean(v))}
                                aria-label="Ativar Slack"
                            />
                            <div className="space-y-0.5">
                                <Label htmlFor="enabled" className="cursor-pointer text-sm font-medium">
                                    Ativar integração
                                </Label>
                                <p className="text-muted-foreground text-xs">
                                    Quando desativado, nenhum alerta será enviado ao Slack.
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
                                    onChange={(e) => setWebhookUrl(e.target.value)}
                                    className="pr-10 font-mono text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowWebhook((v) => !v)}
                                    className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-3 flex items-center transition-colors"
                                    aria-label={showWebhook ? 'Ocultar URL' : 'Mostrar URL'}
                                >
                                    {showWebhook ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Gere em: <span className="font-mono">api.slack.com → Your Apps → Incoming Webhooks</span>
                            </p>
                        </div>

                        {/* Log level */}
                        <div className="space-y-2">
                            <Label htmlFor="log_level">Nível mínimo de alerta</Label>
                            <Select value={logLevel} onValueChange={setLogLevel}>
                                <SelectTrigger id="log_level" className="w-48">
                                    <SelectValue placeholder="Selecione o nível" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(levelOptions).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                Exceções com nível igual ou superior serão enviadas ao Slack.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* O que é monitorado */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Bell className="size-4" />
                            O que é enviado ao Slack
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="text-muted-foreground space-y-2 text-sm">
                            <li className="flex items-start gap-2">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>
                                    <strong className="text-foreground">Exceções não tratadas</strong> — erros 500, falhas em
                                    jobs de fila e outras exceções críticas.
                                </span>
                            </li>
                            <li className="flex items-start gap-2">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>
                                    <strong className="text-foreground">Alertas de log</strong> — via{' '}
                                    <code className="bg-muted rounded px-1 py-0.5 text-xs">Log::warning()</code>,{' '}
                                    <code className="bg-muted rounded px-1 py-0.5 text-xs">Log::error()</code> etc., quando{' '}
                                    <code className="bg-muted rounded px-1 py-0.5 text-xs">LOG_STACK=single,slack</code> está
                                    ativo.
                                </span>
                            </li>
                            <li className="flex items-start gap-2">
                                <BellOff className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span>
                                    <strong className="text-foreground">Ignorados automaticamente:</strong> erros 404, erros de
                                    validação, falhas de autenticação e erros HTTP abaixo de 500.
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex items-center justify-between gap-3 pt-2">
                    <Button
                        variant="outline"
                        onClick={handleTest}
                        disabled={testing || !webhookUrl || !enabled}
                        className="gap-2"
                    >
                        {testing ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                        Enviar mensagem de teste
                    </Button>

                    <Button onClick={handleSave} disabled={saving} className="gap-2">
                        {saving ? <Loader2 className="size-4 animate-spin" /> : <CheckCircle2 className="size-4" />}
                        Salvar configurações
                    </Button>
                </div>
            </div>
        </>
    );
}

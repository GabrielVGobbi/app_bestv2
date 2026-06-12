import { Head, setLayoutProps } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    Clock3,
    Info,
    Loader2,
    MousePointerClick,
    RotateCcw,
    Sparkles,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import {
    components,
    toasts,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastPreset {
    type: ToastType;
    title: string;
    message: string;
    icon: React.ElementType;
    tone: string;
}

const presets: ToastPreset[] = [
    {
        type: 'success',
        title: 'Success',
        message: 'Operacao concluida com sucesso.',
        icon: CheckCircle2,
        tone: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        type: 'error',
        title: 'Error',
        message: 'Nao foi possivel concluir a operacao.',
        icon: XCircle,
        tone: 'text-red-600 dark:text-red-400',
    },
    {
        type: 'warning',
        title: 'Warning',
        message: 'Revise os dados antes de continuar.',
        icon: AlertTriangle,
        tone: 'text-amber-600 dark:text-amber-400',
    },
    {
        type: 'info',
        title: 'Info',
        message: 'Ha uma nova informacao disponivel.',
        icon: Info,
        tone: 'text-sky-600 dark:text-sky-400',
    },
];

export default function Toasts() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Toasts', href: toasts.url() },
        ],
    });

    const [message, setMessage] = useState(
        'Toast disparado pela bancada de componentes.',
    );
    const [description, setDescription] = useState(
        'Use esta tela para validar posicionamento, cores, estados e textos.',
    );
    const [duration, setDuration] = useState(4000);

    const toastOptions = useMemo(
        () => ({
            description,
            duration,
        }),
        [description, duration],
    );

    function showToast(type: ToastType, fallbackMessage: string): void {
        toast[type](message || fallbackMessage, toastOptions);
    }

    function showLoadingToast(): void {
        const id = toast.loading(message || 'Processando...', {
            description: 'Este toast sera resolvido automaticamente.',
        });

        window.setTimeout(() => {
            toast.success('Loading resolvido.', {
                id,
                description: 'O mesmo toast foi atualizado para success.',
                duration,
            });
        }, 1800);
    }

    function showPromiseToast(): void {
        toast.promise(
            new Promise<string>((resolve) => {
                window.setTimeout(
                    () => resolve('Resultado sincronizado.'),
                    1800,
                );
            }),
            {
                loading: 'Sincronizando dados...',
                success: (result) => result,
                error: 'Falha ao sincronizar dados.',
                description,
            },
        );
    }

    function showActionToast(): void {
        toast('Registro movido para arquivados.', {
            description: 'Teste de toast com uma acao contextual.',
            action: {
                label: 'Desfazer',
                onClick: () => toast.info('Acao desfeita.'),
            },
            duration,
        });
    }

    return (
        <>
            <Head title="Toasts" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Heading
                        title="Toasts"
                        description="Tela interna para validar notificacoes locais e estados especiais do Sonner."
                    />

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Disparos rapidos</CardTitle>
                                        <CardDescription>
                                            Teste variantes Padrão, loading,
                                            promise e acao.
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        className="gap-1.5"
                                    >
                                        <Bell className="size-3" />
                                        bottom-center
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {presets.map((preset) => (
                                        <Button
                                            key={preset.type}
                                            type="button"
                                            variant="outline"
                                            className="h-auto justify-start gap-3 px-4 py-3"
                                            onClick={() =>
                                                showToast(
                                                    preset.type,
                                                    preset.message,
                                                )
                                            }
                                        >
                                            <preset.icon
                                                className={`size-4 ${preset.tone}`}
                                            />
                                            <span className="flex flex-col items-start gap-0.5 text-left">
                                                <span>{preset.title}</span>
                                                <span className="text-xs font-normal text-muted-foreground">
                                                    {preset.message}
                                                </span>
                                            </span>
                                        </Button>
                                    ))}
                                </div>

                                <Separator />

                                <div className="grid gap-3 md:grid-cols-3">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={showLoadingToast}
                                    >
                                        <Loader2 className="size-4" />
                                        Loading
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={showPromiseToast}
                                    >
                                        <Clock3 className="size-4" />
                                        Promise
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={showActionToast}
                                    >
                                        <MousePointerClick className="size-4" />
                                        Action
                                    </Button>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => toast.dismiss()}
                                    >
                                        <RotateCcw className="size-4" />
                                        Fechar todos
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => {
                                            presets.forEach((preset, index) => {
                                                window.setTimeout(() => {
                                                    showToast(
                                                        preset.type,
                                                        preset.message,
                                                    );
                                                }, index * 250);
                                            });
                                        }}
                                    >
                                        <Sparkles className="size-4" />
                                        Sequencia
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle>Conteudo</CardTitle>
                                <CardDescription>
                                    Ajuste o texto antes de disparar os
                                    exemplos.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="toast-message">
                                        Mensagem
                                    </Label>
                                    <Input
                                        id="toast-message"
                                        value={message}
                                        onChange={(event) =>
                                            setMessage(event.target.value)
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="toast-description">
                                        Descrição
                                    </Label>
                                    <Input
                                        id="toast-description"
                                        value={description}
                                        onChange={(event) =>
                                            setDescription(event.target.value)
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="toast-duration">
                                        Duracao em ms
                                    </Label>
                                    <Input
                                        id="toast-duration"
                                        type="number"
                                        min={1000}
                                        step={500}
                                        value={duration}
                                        onChange={(event) => {
                                            setDuration(
                                                Number(event.target.value) ||
                                                    4000,
                                            );
                                        }}
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

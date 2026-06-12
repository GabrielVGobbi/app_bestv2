import { Head, setLayoutProps } from '@inertiajs/react';
import {
    BookOpen,
    Code2,
    Database,
    Layers,
    ListFilter,
    Search,
    Settings,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    asyncSelect,
    asyncSelectUsers,
    components,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import { AsyncSelect } from '@/components/async-select';
import type { AsyncSelectOption } from '@/components/async-select';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const STATUS_OPTIONS: AsyncSelectOption[] = [
    { value: 'pending', label: 'Pendente', description: 'Aguardando acao' },
    { value: 'confirmed', label: 'Confirmado', description: 'Validado' },
    { value: 'in_transit', label: 'Em transito', description: 'Em movimento' },
    { value: 'delivered', label: 'Entregue', description: 'Finalizado' },
    { value: 'cancelled', label: 'Cancelado', description: 'Encerrado' },
];

const PRIORITY_OPTIONS: AsyncSelectOption[] = [
    { value: 'low', label: 'Baixa', description: 'Sem urgencia' },
    { value: 'medium', label: 'Media', description: 'Fluxo normal' },
    { value: 'high', label: 'Alta', description: 'Precisa atencao' },
    { value: 'urgent', label: 'Urgente', description: 'Resolver agora' },
];

const basicCode = `import { AsyncSelect } from '@/components/async-select'
import { asyncSelectUsers } from '@/actions/App/Http/Controllers/Admin/DevToolsController'

const [userId, setUserId] = useState<string | null>(null)

<AsyncSelect
    endpoint={asyncSelectUsers.url()}
    value={userId}
    onChange={setUserId}
    placeholder="Selecione um usuario"
    clearable
/>`;

const multipleCode = `const [userIds, setUserIds] = useState<string[]>([])

<AsyncSelect
    multiple
    endpoint={asyncSelectUsers.url()}
    value={userIds}
    onChange={setUserIds}
    placeholder="Selecione usuarios"
    clearable
/>`;

const displayLimitCode = `<AsyncSelect
    multiple
    endpoint={asyncSelectUsers.url()}
    value={reviewerIds}
    onChange={setReviewerIds}
    maxVisibleSelected={5}
    selectedOverflowLabel={(count) => \`+\${count}\`}
    placeholder="Selecione revisores"
/>`;

const staticCode = `const STATUS_OPTIONS = [
    { value: 'pending', label: 'Pendente' },
    { value: 'confirmed', label: 'Confirmado' },
]

<AsyncSelect
    options={STATUS_OPTIONS}
    value={status}
    onChange={setStatus}
    placeholder="Selecione o status"
/>`;

const modalSingleCode = `const [open, setOpen] = useState(false)
const [assigneeId, setAssigneeId] = useState<string | null>(null)

<Dialog open={open} onOpenChange={setOpen}>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Delegar tarefa</DialogTitle>
        </DialogHeader>

        <AsyncSelect
            endpoint={asyncSelectUsers.url()}
            value={assigneeId}
            onChange={setAssigneeId}
            placeholder="Selecione um Responsável"
            clearable
        />

        <DialogFooter>
            <Button disabled={!assigneeId}>Salvar</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>`;

const modalMultipleCode = `const [reviewerIds, setReviewerIds] = useState<string[]>([])
const reviewerLabels = {
    'uuid-1': 'Ana Silva',
    'uuid-2': 'Joao Santos',
}

<DialogContent>
    <AsyncSelect
        multiple
        endpoint={asyncSelectUsers.url()}
        value={reviewerIds}
        initialLabel={reviewerLabels}
        onChange={setReviewerIds}
        maxVisibleSelected={5}
        placeholder="Selecione revisores"
        clearable
    />
</DialogContent>`;

const transformCode = `<AsyncSelect
    endpoint="/users"
    value={userId}
    onChange={setUserId}
    transformOption={(item) => ({
        value: String(item.uuid),
        label: String(item.name),
        description: String(item.email),
    })}
/>`;

const responseCode = `{
    "data": [
        { "value": "uuid-1", "label": "Ana Silva", "description": "ana@app.test" }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3
    }
}`;

const backendCode = `Route::get('/async-select/users', [DevToolsController::class, 'asyncSelectUsers']);

public function asyncSelectUsers(Request $request): JsonResponse
{
    $users = User::query()
        ->where('name', 'like', "%{$request->string('search')}%")
        ->paginate($request->integer('limit', 10));

    return response()->json([
        'data' => $users->map(fn (User $user) => [
            'value' => $user->uuid,
            'label' => $user->name,
            'description' => $user->email,
        ]),
        'meta' => [
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ],
    ]);
}`;

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
                {required && <Badge variant="outline">obrigatorio</Badge>}
            </div>
            <code className="w-fit rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                {type}
            </code>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

export default function AsyncSelectPage() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Async select', href: asyncSelect.url() },
        ],
    });

    const [mode, setMode] = useState<'async' | 'static'>('async');
    const [multiple, setMultiple] = useState(false);
    const [clearable, setClearable] = useState(true);
    const [disabled, setDisabled] = useState(false);
    const [singleValue, setSingleValue] = useState<string | null>(null);
    const [multipleValue, setMultipleValue] = useState<string[]>([]);
    const [statusValue, setStatusValue] = useState<string | null>(null);
    const [statusValues, setStatusValues] = useState<string[]>([]);
    const [priorityValue, setPriorityValue] = useState<string | null>(null);
    const [singleModalOpen, setSingleModalOpen] = useState(false);
    const [multipleModalOpen, setMultipleModalOpen] = useState(false);
    const [modalUserId, setModalUserId] = useState<string | null>(null);
    const [modalUserIds, setModalUserIds] = useState<string[]>([]);
    const [limitedUserIds, setLimitedUserIds] = useState<string[]>([]);

    const liveValue = useMemo(() => {
        if (mode === 'async') {
            return multiple ? multipleValue : singleValue;
        }

        return multiple ? statusValues : statusValue;
    }, [mode, multiple, multipleValue, singleValue, statusValue, statusValues]);

    return (
        <>
            <Head title="Async select" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <Heading
                            title="Async select"
                            description="Select reutilizavel com busca remota, busca local, selecao unica e multipla."
                        />
                        <Badge variant="secondary" className="w-fit">
                            resources/js/components/async-select
                        </Badge>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <FeatureCard
                            icon={Database}
                            title="Async real"
                            description="Busca no backend com search, page e limit."
                        />
                        <FeatureCard
                            icon={Layers}
                            title="Static"
                            description="Usa array local para listas pequenas."
                        />
                        <FeatureCard
                            icon={ListFilter}
                            title="Multiplo"
                            description="Retorna string[] e exibe selecoes como badges."
                        />
                    </div>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings className="size-5" />
                                        Playground
                                    </CardTitle>
                                    <CardDescription className="mt-1.5">
                                        Troque o modo e abra o select. No modo
                                        async, veja as requisicoes para o
                                        backend no Network.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            multiple ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => setMultiple(!multiple)}
                                    >
                                        Multiplo: {multiple ? 'sim' : 'nao'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            clearable ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => setClearable(!clearable)}
                                    >
                                        Clearable: {clearable ? 'sim' : 'nao'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            disabled ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => setDisabled(!disabled)}
                                    >
                                        Disabled: {disabled ? 'sim' : 'nao'}
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-5 lg:grid-cols-[260px_1fr]">
                            <div className="space-y-2">
                                <span className="text-sm font-medium">
                                    Modo
                                </span>
                                <Select
                                    value={mode}
                                    onValueChange={(value) =>
                                        setMode(value as 'async' | 'static')
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="async">
                                            Async (backend)
                                        </SelectItem>
                                        <SelectItem value="static">
                                            Static (array)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-3">
                                {mode === 'async' && multiple && (
                                    <AsyncSelect
                                        multiple
                                        endpoint={asyncSelectUsers.url()}
                                        value={multipleValue}
                                        onChange={setMultipleValue}
                                        placeholder="Selecione usuarios"
                                        clearable={clearable}
                                        disabled={disabled}
                                    />
                                )}
                                {mode === 'async' && !multiple && (
                                    <AsyncSelect
                                        endpoint={asyncSelectUsers.url()}
                                        value={singleValue}
                                        onChange={setSingleValue}
                                        placeholder="Selecione um usuario"
                                        clearable={clearable}
                                        disabled={disabled}
                                    />
                                )}
                                {mode === 'static' && multiple && (
                                    <AsyncSelect
                                        multiple
                                        options={STATUS_OPTIONS}
                                        value={statusValues}
                                        onChange={setStatusValues}
                                        placeholder="Selecione status"
                                        clearable={clearable}
                                        disabled={disabled}
                                    />
                                )}
                                {mode === 'static' && !multiple && (
                                    <AsyncSelect
                                        options={STATUS_OPTIONS}
                                        value={statusValue}
                                        onChange={setStatusValue}
                                        placeholder="Selecione um status"
                                        clearable={clearable}
                                        disabled={disabled}
                                    />
                                )}

                                <div className="rounded-lg border bg-muted/30 p-3">
                                    <div className="text-xs font-medium text-muted-foreground">
                                        Valor atual
                                    </div>
                                    <code className="text-xs">
                                        {JSON.stringify(liveValue)}
                                    </code>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Layers className="size-5" />
                                Uso em modal
                            </CardTitle>
                            <CardDescription>
                                Exemplos reais dentro de `Dialog`, com popover
                                abrindo acima da modal e estado controlado.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 xl:grid-cols-2">
                            <div className="space-y-4">
                                <div className="rounded-lg border bg-muted/20 p-4">
                                    <div className="mb-3">
                                        <div className="font-medium">
                                            Seleção única
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            Ideal para responsável, cliente,
                                            categoria ou owner.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={() => setSingleModalOpen(true)}
                                    >
                                        Abrir modal single
                                    </Button>
                                </div>

                                <CodeBlock code={modalSingleCode} />
                            </div>

                            <div className="space-y-4">
                                <div className="rounded-lg border bg-muted/20 p-4">
                                    <div className="mb-3">
                                        <div className="font-medium">
                                            Seleção múltipla
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            Use para participantes, revisores,
                                            tags ou permissões.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setMultipleModalOpen(true)
                                        }
                                    >
                                        Abrir modal multi
                                    </Button>
                                </div>

                                <CodeBlock code={modalMultipleCode} />
                            </div>
                        </CardContent>
                    </Card>

                    <Dialog
                        open={singleModalOpen}
                        onOpenChange={setSingleModalOpen}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Delegar tarefa</DialogTitle>
                                <DialogDescription>
                                    Selecione um usuário usando busca remota
                                    dentro da modal.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2">
                                <span className="text-sm font-medium">
                                    Responsável
                                </span>
                                <AsyncSelect
                                    endpoint={asyncSelectUsers.url()}
                                    value={modalUserId}
                                    onChange={setModalUserId}
                                    placeholder="Selecione um responsável"
                                    clearable
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setSingleModalOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!modalUserId}
                                    onClick={() => setSingleModalOpen(false)}
                                >
                                    Salvar
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Dialog
                        open={multipleModalOpen}
                        onOpenChange={setMultipleModalOpen}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Adicionar revisores</DialogTitle>
                                <DialogDescription>
                                    Selecione múltiplos usuários. O popover
                                    permanece aberto durante a seleção.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2">
                                <span className="text-sm font-medium">
                                    Revisores
                                </span>
                                <AsyncSelect
                                    multiple
                                    endpoint={asyncSelectUsers.url()}
                                    value={modalUserIds}
                                    onChange={setModalUserIds}
                                    maxVisibleSelected={5}
                                    placeholder="Selecione revisores"
                                    clearable
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setMultipleModalOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    disabled={modalUserIds.length === 0}
                                    onClick={() => setMultipleModalOpen(false)}
                                >
                                    Salvar {modalUserIds.length || ''}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Search className="size-5" />
                                    Exemplos
                                </CardTitle>
                                <CardDescription>
                                    Static, render customizado e uso com API.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="space-y-2">
                                    <span className="text-sm font-medium">
                                        Prioridade estatica
                                    </span>
                                    <AsyncSelect
                                        options={PRIORITY_OPTIONS}
                                        value={priorityValue}
                                        onChange={setPriorityValue}
                                        placeholder="Selecione prioridade"
                                        clearable
                                    />
                                </div>

                                <div className="space-y-2">
                                    <span className="text-sm font-medium">
                                        Usuario com renderOption
                                    </span>
                                    <AsyncSelect
                                        endpoint={asyncSelectUsers.url()}
                                        value={singleValue}
                                        onChange={setSingleValue}
                                        placeholder="Selecione um usuario"
                                        renderOption={(option) => (
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">
                                                        {option.label}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {option.description}
                                                    </div>
                                                </div>
                                                <Badge variant="outline">
                                                    user
                                                </Badge>
                                            </div>
                                        )}
                                        clearable
                                    />
                                </div>

                                <div className="space-y-2">
                                    <span className="text-sm font-medium">
                                        Multi com limite visual
                                    </span>
                                    <AsyncSelect
                                        multiple
                                        endpoint={asyncSelectUsers.url()}
                                        value={limitedUserIds}
                                        onChange={setLimitedUserIds}
                                        maxVisibleSelected={5}
                                        placeholder="Selecione usuarios"
                                        clearable
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Mantem todos os ids no value, mas mostra
                                        apenas 5 labels e compacta o restante em
                                        um badge.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Code2 className="size-5" />
                                    Codigo rapido
                                </CardTitle>
                                <CardDescription>
                                    Os tres usos mais comuns.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={basicCode} />
                                <CodeBlock code={multipleCode} />
                                <CodeBlock code={displayLimitCode} />
                                <CodeBlock code={staticCode} />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Zap className="size-5" />
                                    API do componente
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PropRow
                                    name="endpoint"
                                    type="string"
                                    description="URL usada para buscar opcoes. Se omitida, o componente usa options local."
                                />
                                <PropRow
                                    name="options"
                                    type="AsyncSelectOption[]"
                                    description="Opcoes locais para modo static."
                                />
                                <PropRow
                                    name="value"
                                    type="string | null | string[]"
                                    required
                                    description="Valor controlado. Use string[] quando multiple=true."
                                />
                                <PropRow
                                    name="onChange"
                                    type="(value) => void"
                                    required
                                    description="Callback disparado ao selecionar, remover ou limpar."
                                />
                                <PropRow
                                    name="initialLabel"
                                    type="string | Record<string, string>"
                                    description="Label inicial para valores ja salvos. Em multiple, passe um objeto { value: label } para nao exibir UUID."
                                />
                                <PropRow
                                    name="maxVisibleSelected"
                                    type="number"
                                    description="Limite visual de badges selecionados no modo multiple. Se omitido, mostra todos os labels."
                                />
                                <PropRow
                                    name="selectedOverflowLabel"
                                    type="(hiddenCount: number) => ReactNode"
                                    description="Customiza o badge de itens ocultos quando maxVisibleSelected e usado."
                                />
                                <PropRow
                                    name="transformOption"
                                    type="(item: unknown) => AsyncSelectOption"
                                    description="Mapeia respostas customizadas da API."
                                />
                                <PropRow
                                    name="renderOption"
                                    type="(option) => ReactNode"
                                    description="Renderizacao customizada de cada opcao."
                                />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BookOpen className="size-5" />
                                    Backend esperado
                                </CardTitle>
                                <CardDescription>
                                    O componente envia `search`, `page` e
                                    `limit`.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={responseCode} />
                                <CodeBlock code={backendCode} />
                                <CodeBlock code={transformCode} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

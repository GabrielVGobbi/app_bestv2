# DataTable

[Validação e limites das preferências locais](../../../../docs/datatable-preferences-validation.md).

Componente generico para listar recursos Laravel paginados em React, usando TanStack Table para estado da tabela e TanStack Query para buscar dados da API.

Ele assume que a API retorna o formato Laravel Resource paginado:

```ts
{
    data: T[]
    links: {
        first: string | null
        last: string | null
        prev: string | null
        next: string | null
    }
    meta: {
        current_page: number
        from: number | null
        last_page: number
        per_page: number
        to: number | null
        total: number
    }
}
```

O DataTable deve ficar sob `PrivateStateLayout`, aplicado pelos layouts de `app.tsx` abaixo do PageContext Inertia. Esse layout fornece um QueryClient por arvore privada, inclusive por render/request SSR; nao mantenha um client privado global no modulo. Na troca de identidade ou elegibilidade, somente a subarvore privada e remontada por geracao, sem remontar o App Inertia. Cache, selecao e callbacks do escopo anterior nao pertencem a nova conta.

## Uso Basico

```tsx
import type { ColumnDef } from '@tanstack/react-table'
import { DataTable } from '@/components/data-table'

const columns: ColumnDef<User>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        meta: { label: 'Nome' },
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'Email',
        meta: { label: 'Email' },
    },
]

<DataTable<User>
    queryKey="users"
    endpoint="/api/v1/users"
    mode="infinite-scroll"
    columns={columns}
    keyExtractor={(user) => user.uuid}
/>
```

## Props Principais

```ts
interface DataTableProps<TData> {
    queryKey: string;
    tableId?: string;
    endpoint: string;
    mode?: 'paginated' | 'infinite-scroll';
    columns: ColumnDef<TData>[];
    keyExtractor: (row: TData) => string | number;
    features?: {
        search?: boolean;
        filters?: boolean;
        columnVisibility?: boolean;
        refresh?: boolean;
        selection?: boolean;
    };
    filters?: DataTableFilterConfig[];
    quickFilters?: DataTableQuickFilter[];
    dateFilterColumns?: DataTableDateColumn[];
    actions?: DataTableAction<TData>[];
    actionsConfig?: DataTableActionsConfig;
    bulkActions?: DataTableBulkAction<TData>[];
    bulkAggregates?: DataTableBulkAggregate<TData>[];
    bulkActionsConfig?: DataTableBulkActionsConfig;
    infiniteScrollConfig?: {
        height?: string;
        fitViewport?: boolean;
        viewportBottomOffset?: number;
        minHeight?: number;
        loadMoreThreshold?: string;
    };
    appearance?: {
        size?: 'sm' | 'md';
        gridLines?: 'none' | 'horizontal' | 'vertical' | 'both';
        rowStyle?: 'plain' | 'striped' | 'soft-striped';
    };
    size?: 'sm' | 'md';
    gridLines?: 'none' | 'horizontal' | 'vertical' | 'both';
    rowStyle?: 'plain' | 'striped' | 'soft-striped';
    initialPerPage?: number;
    searchPlaceholder?: string;
    emptyMessage?: string;
}
```

## Estilos de Tabela

A aparencia pode ser configurada pelo objeto `appearance` ou pelas props diretas `size`, `gridLines` e `rowStyle`. As props diretas vencem o valor dentro de `appearance`.

Defaults:

```tsx
<DataTable size="md" gridLines="both" rowStyle="plain" />
```

### Sizes

- `sm`: linhas mais compactas para telas com muitos dados.
- `md`: tamanho padrao, com mais respiro para leitura.

### Grid Lines

- `both`: horizontal e vertical (default).
- `horizontal`: apenas linhas entre linhas.
- `vertical`: apenas separadores entre colunas.
- `none`: sem bordas internas.

### Row Style

- `plain`: sem alternancia (default).
- `striped`: alternancia com fundo mais forte.
- `soft-striped`: alternancia sutil, boa para tabelas densas.

### Combinacoes

```tsx
// Backoffice denso
<DataTable appearance={{ size: 'sm', gridLines: 'both', rowStyle: 'plain' }} ... />

// Lista leve com scroll
<DataTable appearance={{ size: 'md', gridLines: 'none', rowStyle: 'soft-striped' }} ... />
```

## Colunas

Sempre defina `id` explicitamente nas colunas. Isso evita ambiguidade entre `accessorKey` e o ID interno do TanStack Table, especialmente para visibilidade de colunas, ordenacao e resize.

A coluna de ações mantém a largura definida/calculada e não permite resize ou
ajuste ao conteúdo. Nasce fixada à direita para não acompanhar o redimensionamento
das colunas de dados; `actionsConfig.sticky=false` é uma exceção explícita.
Quando todas as colunas de dados já têm largura salva, a
última coluna redimensionável absorve o espaço disponível, respeitando `maxSize`,
para manter as ações na borda da tabela. Preferências de largura não alteram
colunas não redimensionáveis.
Não há alça na divisória imediatamente antes de Ações, nem arraste nativo nos
links das ações inline. Os ícones inline usam botões compactos de 32 px, sem
sombra e com exclusão discreta; permissões, callbacks e tooltips são preservados.

```tsx
const columns: ColumnDef<User>[] = [
    {
        id: 'created_at',
        accessorKey: 'created_at',
        header: 'Criado em',
        size: 180,
        cell: ({ row }) => formatDate(row.original.created_at),
        meta: {
            label: 'Criado em',
            type: 'date',
        },
    },
];
```

Use `meta.label` para o nome exibido no menu de colunas.

Use `meta.initialVisibility = false` para iniciar uma coluna oculta.

```tsx
{
    id: 'updated_at',
    accessorKey: 'updated_at',
    header: 'Atualizado em',
    meta: {
        label: 'Atualizado em',
        initialVisibility: false,
    },
}
```

## Features

```tsx
features={{
    search: true,
    filters: true,
    columnVisibility: true,
    refresh: true,
    selection: false,
}}
```

- `search`: habilita busca global compacta.
- `filters`: habilita popup de filtros avancados.
- `columnVisibility`: habilita controle de colunas no menu `Opcoes`.
- `refresh`: habilita refresh no menu `Opcoes`.
- `selection`: adiciona coluna de selecao com checkbox. Necessario para usar `bulkActions`.

## Busca Global

A busca global envia `search` para a API. No backend, `PaginationRequest` usa `searchTerm()` e o trait `Searchable` aplica busca nas colunas configuradas no model.

O input inicia compacto como icone. Ele expande no hover/focus e permanece aberto enquanto houver texto.

## Filtros Avancados

Filtros avancados aparecem no popup `Filtros`.

```tsx
const filters: DataTableFilterConfig[] = [
    {
        id: 'name',
        label: 'Nome',
        type: 'text',
        operator: 'contains',
        placeholder: 'Buscar por nome',
    },
    {
        id: 'email',
        label: 'Email',
        type: 'text',
        operator: 'contains',
        placeholder: 'Buscar por email',
    },
];
```

Tipos suportados:

- `text`
- `select`
- `multi-select`
- `boolean`
- `date`
- `date-range`
- `number`
- `number-range`
- `async-select` reservado para evolucao

Operadores suportados no backend:

- `eq`
- `contains`
- `starts_with`
- `in`
- `between`
- `gte`
- `lte`
- `null`
- `not_null`

## Quick Filters

Use quick filters para filtros frequentes que devem ficar direto na toolbar, ao lado da busca.

No Activity Log, Ação, Canal e Tipo de alvo usam `filters`, dentro do painel “Filtros”. A barra exibe somente os chips dos valores ativos, removíveis, na mesma linha dos controles. Não há segunda faixa de chips. Filtros rápidos inativos mostram somente seu nome; quando ativos, mostram nome e valor e não são repetidos como chips.

```tsx
const quickFilters: DataTableQuickFilter[] = [
    {
        id: 'status',
        label: 'Status',
        placeholder: 'Todos os status',
        icon: User,
        options: [
            { value: 'active', label: 'Ativo', color: '#22c55e' },
            { value: 'inactive', label: 'Inativo', color: '#ef4444' },
            { value: 'pending', label: 'Pendente', color: '#f59e0b' },
        ],
        multiple: true,
        clearable: true,
        operator: 'in',
    },
];
```

Para boolean/null filters:

```tsx
const quickFilters: DataTableQuickFilter[] = [
    {
        id: 'email_verified_at',
        label: 'Verificacao',
        placeholder: 'Verificacao',
        options: [
            { label: 'Verificados', value: true, color: '#22c55e' },
            { label: 'Nao verificados', value: false, color: '#ef4444' },
        ],
    },
];
```

No serializer atual, `boolean true` vira `not_null` e `boolean false` vira `null`.

## Filtro de Datas no Header

Use `dateFilterColumns` para exibir uma secao de datas no topo do popup de filtros.

```tsx
const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Data de Criacao' },
    { value: 'updated_at', label: 'Ultima Atualizacao' },
]

<DataTable
    dateFilterColumns={dateFilterColumns}
/>
```

A UI permite:

- escolher a coluna de data;
- usar presets como Hoje, Ontem, Esta Semana, Este Mes;
- preencher intervalo customizado;
- limpar o filtro de data.

O filtro enviado para API usa `between`:

```txt
filter[created_at][operator]=between
filter[created_at][value][from]=2026-05-01 00:00:00
filter[created_at][value][to]=2026-05-31 23:59:59
```

## Actions

Por padrao, as actions aparecem no menu de tres pontos.

```tsx
const actions: DataTableAction<User>[] = [
    {
        label: 'View',
        icon: <Eye className="size-4" />,
        href: (user) => show.url(user.uuid),
    },
];
```

Para botoes inline:

```tsx
<DataTable
    actions={actions}
    actionsConfig={{
        display: 'inline',
        label: 'Acoes',
        buttonSize: 'sm',
    }}
/>
```

### Actions So Com Icone

Controle por action, nao apenas global:

```tsx
const actions: DataTableAction<User>[] = [
    {
        label: 'View',
        icon: <Eye className="size-4" />,
        href: (user) => show.url(user.uuid),
        showLabel: false,
        tooltip: 'Visualizar',
    },
    {
        label: 'Edit',
        icon: <Edit className="size-4" />,
        href: (user) => edit.url(user.uuid),
        showLabel: true,
    },
];
```

Regras:

- `action.showLabel` decide por botao.
- `actionsConfig.showLabels` e apenas o default global.
- `action.tooltip` pode ser `true`, `false` ou uma string.
- se `showLabel: false`, tooltip aparece por padrao, exceto se `tooltip: false`.

## Selecao e Bulk Actions

Ative `features.selection` e defina `bulkActions` para habilitar selecao multipla com barra de acoes em massa.

A barra aparece automaticamente no rodape da tela (fixed, bottom-center) quando ha linhas selecionadas. Ela some com transicao CSS quando a selecao e limpa: sem re-mount, sem reflow.

### Bulk Actions

```tsx
import type { DataTableBulkAction } from '@/components/data-table'

const bulkActions: DataTableBulkAction<User>[] = [
    {
        id: 'bulk-delete',
        label: 'Excluir',
        icon: <Trash2 className="size-4" />,
        variant: 'destructive',
        onClick: (rows, clearSelection) => {
            // rows: TData[] da selecao completa
            // clearSelection(): limpa apenas as inclusoes deste snapshot
            handleBulkDelete(rows, clearSelection)
        },
    },
]

<DataTable
    features={{ selection: true }}
    bulkActions={bulkActions}
    ...
/>
```

`variant` segue os mesmos valores de `DataTableAction`: `default`, `outline`, `secondary`, `ghost`, `link`, `destructive`.

`disabled`, agregados e `onClick` recebem somente a selecao completa. Sem resolver, uma selecao restaurada parcialmente carregada bloqueia a acao ate que todas as linhas tenham sido carregadas.

O consumidor pode fornecer `selectionResolver(ids, { signal })`, retornando `{ rows, unavailableIds }`. Os dois arrays devem formar uma particao completa dos IDs solicitados, sem duplicatas ou IDs extras. A barra resolve novamente ao clicar na acao; respostas incompletas, erro HTTP e registros indisponiveis preservam os IDs, exibem o motivo e impedem o callback. Um 200 sem um ID significa indisponivel, removido ou sem acesso; 403/500 sao erros, nunca uma selecao vazia bem-sucedida. Mudancas de selecao, conjunto ou conta cancelam e descartam a resolucao anterior.

`bulkActionsConfig.maxActionCount` limita explicitamente a quantidade da acao. Acima desse limite, a acao fica desabilitada com o motivo e `Revisar selecao` permite resolver os IDs sem executar o callback. Erros oferecem `Tentar novamente`; essa revisao so atualiza os dados e agregados.

Na pagina Users, o resolver usa o GET autorizado existente com `filter[uuid][operator]=in`, valores em array e lotes de ate 100 (`page=1`, `per_page=100`). A confirmacao refaz esse preflight e envia um unico DELETE `api.users.bulk-destroy`, com `{ ids }` de 1 a 100 UUIDs. O servidor revalida a autoridade e executa o lote atomicamente; o recibo informa `data.deleted_ids` canonicos e `data.deleted_count`. A interface limpa o snapshot e invalida a lista somente apos recibo completo. Erros preservam dialogo, selecao e cache. O antigo botao de verificacao sem endpoint foi removido; nao ha verificacao em massa implicita.

### Bulk Aggregates

Use `bulkAggregates` para exibir valores calculados sobre as linhas selecionadas diretamente na barra. Util para somar valores, contar subconjuntos, etc.

```tsx
import type { DataTableBulkAggregate } from '@/components/data-table'

const bulkAggregates: DataTableBulkAggregate<Order>[] = [
    {
        id: 'total',
        label: 'Total',
        fn: (rows) => rows.reduce((sum, o) => sum + o.amount, 0),
        format: (value) => `R$ ${Number(value).toFixed(2)}`,
    },
    {
        id: 'verified-count',
        label: 'Verificados',
        fn: (rows) => rows.filter((u) => u.email_verified_at !== null).length,
        format: (value) => String(value),
    },
]

<DataTable
    bulkAggregates={bulkAggregates}
    ...
/>
```

`fn` recebe as linhas selecionadas e retorna `string | number`.
`format` e opcional: transforma o valor bruto para exibicao.

### Persistencia da Selecao

A selecao usa `private-state:{id}:datatable-selection:{queryKey}`, em que `{id}` vem de `auth.user.id`, e continua sendo purgada na saida da conta.

Preferencias usam `datatable-preferences:v1:{id}:{tableId}` e sobrevivem ao logout por autorizacao especifica: busca, filtros/datas, ordenacao, tamanho do lote, ordem/visibilidade/fixacao/larguras e densidade. Nunca incluem linhas, cache ou selecao. A identidade segue `tableId ?? persistState.key ?? queryKey`, mantendo chaves customizadas compativeis. O showcase usa `dev-inline-cell-edit-users` para nao dividir layout com usuarios. Apenas o escopo elegivel atual pode ler/gravar; callbacks antigos sao descartados. O purge de `private-state:` permanece intacto.

Quando a chave nova nao existe, migra somente `private-state:{id}:datatable-state:{queryKey}` da propria conta, ou a chave legada personalizada por `persistState.key`. Chaves sem identidade nunca sao adotadas. Validacao/versionamento descarta valores invalidos, reconcilia colunas novas/removidas e mantem pelo menos uma coluna de dados visivel. Colunas obrigatorias, selecao e acoes sao protegidas. Storage bloqueado ou invalido e informado em Configurar colunas. A restauracao antecede a primeira consulta, inclusive busca; alteracoes confirmadas sao gravadas sem debounce de persistencia.

`persistState={false}` desativa a persistencia. O objeto aceita `search`, `filters`, `sorting`, `perPage`, `columnVisibility`, `layout` (ordem/fixacao/larguras) e `density`, todos habilitados por padrao.

Configurar colunas permite mostrar/ocultar, arrastar ou usar setas, fixar dos dois lados e escolher `sm`/`md`. Resize tem guia, confirma ao soltar e cancela por Escape, pointercancel ou perda de foco; setas no separador alteram 10px (Shift: 50px). Ajustar largura mede apenas DOM carregado e cabecalhos, respeitando min/max, sem GET ou renderizacao duplicada. Mobile relaxa fixacao quando falta espaco, preservando a preferencia.

Limpar filtros remove filtros/datas preservando busca. Restaurar layout repoe ordem/visibilidade/fixacao/larguras/densidade; Restaurar tudo inclui busca/filtros/sort/tamanho do lote. Nenhum reset limpa selecao ou registros. Layout nao refaz consulta. Chips apresentam nome e valor, removem itens individualmente e aceitam `false`/`0`. Celulas truncadas abrem texto completo com quebra de linha via clique/toque/teclado. O modo padrao e `infinite-scroll`; `mode="paginated"` continua suportado com TanStack Query JSON.

Comportamento:

- **Filtros e busca**: selecionar 4 linhas, aplicar filtro e selecionar mais 1 resulta em 5 selecionados. As linhas fora da query atual permanecem selecionadas pelo ID; seus dados ficam no cache interno enquanto os checkboxes da query atual refletem o estado correto.
- **Infinite scroll**: ao carregar mais paginas, as selecoes das paginas anteriores sao mantidas. Os dados de todas as paginas ficam acumulados em `query.rows`.
- **Recarregamento de pagina**: na mesma conta elegivel, os IDs selecionados e o estado persistido sao restaurados apenas do seu namespace. Os checkboxes ficam marcados assim que as linhas correspondentes carregarem.
- **Limpar selecao**: o X remove a selecao atual. O `clearSelection()` entregue a uma acao limpa somente as inclusoes capturadas naquele momento; IDs adicionados ou desmarcados e selecionados novamente depois sao preservados. Uma funcao antiga nao limpa outra ativacao do conjunto nem outra conta.
- **Logout/troca de conta**: invalida o escopo, cancela consultas e timers, limpa cache/linhas selecionadas em memoria e purga o namespace privado da conta de saida. Visitantes e contas sem e-mail verificado ou com troca obrigatoria de senha nao restauram estado privado. Preferencias de aparencia/sidebar e de tabela por conta nao sao apagadas.
- **Legado e falhas de storage**: chaves antigas sem identidade nao sao migradas para a conta atual. JSON invalido ou storage indisponivel nao autoriza usar os dados de outra conta; a persistencia e opcional.
- **Historico**: props antigas de A durante uma sessao B ficam ocultas antes da renderizacao e sao revalidadas por HTTP. Isso nao confirma A nem apaga a persistencia propria de B apenas por revalidar o historico.

> **Nota**: os IDs persistidos podem existir antes dos dados. A contagem continua refletindo esses IDs, mas nenhum agregado ou callback usa um subconjunto silencioso. Com resolver, a consulta fresca completa a selecao; sem resolver, carregue as linhas restantes ou limpe a selecao.

As consultas usam o cliente `@/lib/http`, que combina o AbortSignal do chamador com o escopo privado e rejeita respostas obsoletas como `ApiError` de tipo `canceled`. Handlers assincronos de mutacao/bulk devem capturar o escopo e conferir sua validade antes de outra escrita, rollback, toast ou navegacao apos `await`; limpar cache ou desmontar a pagina nao interrompe essas funcoes JavaScript. Cancelamento nao desfaz uma operacao ja aceita no servidor e nao cobre automaticamente transportes `fetch`/Axios fora desse cliente. A API continua responsavel pelas policies e pelo escopo dos registros.

## Infinite Scroll

```tsx
<DataTable
    mode="infinite-scroll"
    infiniteScrollConfig={{
        height: '60vh',
        loadMoreThreshold: '200px',
    }}
/>
```

No infinite scroll, a tabela usa `useInfiniteQuery` e carrega a proxima pagina quando o sentinel entra na area visivel.

### Altura Responsiva

Use `fitViewport` para a tabela calcular automaticamente o espaco disponivel na tela. Assim o scroll fica dentro da tabela e a pagina evita criar um segundo scroll vertical.

```tsx
<DataTable
    mode="infinite-scroll"
    infiniteScrollConfig={{
        fitViewport: true,
        viewportBottomOffset: 96,
        minHeight: 320,
        loadMoreThreshold: '150px',
    }}
/>
```

- `fitViewport`: calcula a altura pelo viewport atual.
- `viewportBottomOffset`: reserva espaco para resumo, rodape e respiro inferior.
- `minHeight`: impede que a tabela fique pequena demais em telas baixas.
- `height`: continua disponivel para alturas fixas como `60vh`.

O rodape mostra:

```txt
30 carregados de 120 registros
```

## Paginacao

```tsx
<DataTable mode="paginated" />
```

No modo paginado, a tabela envia:

```txt
page
per_page
search
sort
direction
filter[...]
```

O rodape mostra:

```txt
Mostrando 1 a 15 de 120 registros
```

## Backend Esperado

O model precisa usar `Searchable` e definir colunas permitidas:

```php
protected array $searchable = ['name', 'email'];

protected array $filterable = [
    'uuid',
    'name',
    'email',
    'created_at',
    'updated_at',
    'email_verified_at',
];

protected array $sortable = [
    'id',
    'uuid',
    'name',
    'email',
    'created_at',
    'updated_at',
];
```

O controller de API deve retornar resource paginado:

```php
public function index(PaginationRequest $request): AnonymousResourceCollection
{
    return ApiDisplay::for(User::class)->paginate($request);
}
```

## Boas Praticas

- Defina `id` em todas as colunas.
- Use `keyExtractor` com identificador estavel, como UUID.
- Mantenha `queryKey` unico por recurso.
- Use quick filters para filtros usados com frequencia.
- Use filtros avancados para filtros menos frequentes ou com mais configuracao.
- Use `dateFilterColumns` para filtros de periodo comuns.
- Use `meta.label` nas colunas para nomes humanos no menu de colunas.
- Prefira Wayfinder para actions que navegam para rotas Laravel.
- Garanta que `filter.id` bate com uma coluna permitida em `$filterable`.
- Garanta que `accessorKey`/`id` de sort bate com uma coluna permitida em `$sortable`.

### Performance

Defina `columns`, `filters`, `quickFilters`, `dateFilterColumns`, `bulkAggregates` e qualquer array estatico **fora do componente**. Eles nao dependem de estado ou props e nao precisam ser recriados a cada render.

```tsx
// Fora do componente: criado uma unica vez
const columns: ColumnDef<User>[] = [ ... ]
const filters: DataTableFilterConfig[] = [ ... ]
const bulkAggregates: DataTableBulkAggregate<User>[] = [ ... ]

export default function Index() {
    // Dentro do componente: apenas o que depende de estado/props ou JSX
    const actions = useMemo<DataTableAction<User>[]>(() => [ ... ], [])
    const bulkActions = useMemo<DataTableBulkAction<User>[]>(() => [ ... ], [])
}
```

Use `useMemo` apenas quando o array contiver JSX inline (como `<Icon />`) ou referenciar callbacks locais. Para arrays de configuracao pura, `const` fora do componente e suficiente.

Use `useCallback` para handlers de delete/bulk que sao passados como `onClick` e referenciam `queryClient` ou outros valores estaveis.

Use `useRef` para armazenar referencias a funcoes (como `clearSelection`) em vez de `useState`, para evitar re-renders desnecessarios.

## O Que Nao Fazer

- Nao use colunas sem `id`.
- Nao envie filtros que o backend nao permite em `$filterable`.
- Nao coloque filtros frequentes escondidos apenas no popup.
- Nao coloque filtros raros como quick filters.
- Nao use hardcoded URLs em actions quando existe Wayfinder.
- Nao coloque logica pesada dentro de `cell` se puder formatar antes ou criar helper.
- Nao confie no frontend para seguranca de filtros; o backend deve continuar limitando colunas.
- Nao misture `quickFilters` e `filters` com o mesmo `id`, a menos que eles representem exatamente o mesmo estado.
- Nao use `showLabels: false` global se apenas uma action deve esconder label; use `action.showLabel`.
- Nao armazene funcoes de callback (como `clearSelection`) em `useState`; use `useRef`.
- Nao use `useMemo([], [])` para arrays de configuracao pura sem JSX; defina fora do componente.

## Exemplo Completo

```tsx
import type { ColumnDef } from '@tanstack/react-table'
import { Trash2, ShieldCheck, Eye, Edit } from 'lucide-react'
import { useCallback, useMemo, useRef, useState } from 'react'
import { DataTable } from '@/components/data-table'
import type {
    DataTableAction,
    DataTableBulkAction,
    DataTableBulkAggregate,
    DataTableFilterConfig,
    DataTableQuickFilter,
    DataTableDateColumn,
} from '@/components/data-table'

// Estaticos fora do componente
const columns: ColumnDef<User>[] = [ ... ]
const filters: DataTableFilterConfig[] = [ ... ]
const quickFilters: DataTableQuickFilter[] = [ ... ]
const dateFilterColumns: DataTableDateColumn[] = [ ... ]
const bulkAggregates: DataTableBulkAggregate<User>[] = [ ... ]

export default function UserIndex() {
    const [bulkDeleteUsers, setBulkDeleteUsers] = useState<User[]>([])
    const clearSelectionRef = useRef<(() => void) | null>(null)
    const queryClient = useQueryClient()

    const handleBulkDelete = useCallback((users: User[]) => {
        // Revalidar os IDs e aguardar o endpoint atomico autorizado antes de limpar.
        setBulkDeleteUsers([])
        clearSelectionRef.current?.()
        clearSelectionRef.current = null
    }, [queryClient])

    const actions = useMemo<DataTableAction<User>[]>(() => [
        { label: 'Edit', icon: <Edit className="size-4" />, href: (u) => edit.url(u.uuid), showLabel: false },
        { label: 'Delete', icon: <Trash2 className="size-4" />, variant: 'destructive', showLabel: false, onClick: setUserToDelete },
    ], [])

    const bulkActions = useMemo<DataTableBulkAction<User>[]>(() => [
        {
            id: 'bulk-delete',
            label: 'Excluir',
            icon: <Trash2 className="size-4" />,
            variant: 'destructive',
            onClick: (rows, clearSelection) => {
                clearSelectionRef.current = clearSelection
                setBulkDeleteUsers(rows)
            },
        },
    ], [])

    return (
        <DataTable<User>
            queryKey="users"
            endpoint={apiRoutes.users.index}
            mode="infinite-scroll"
            columns={columns}
            keyExtractor={(user) => user.uuid}
            features={{ search: true, filters: true, columnVisibility: true, refresh: true, selection: true }}
            filters={filters}
            quickFilters={quickFilters}
            dateFilterColumns={dateFilterColumns}
            actions={actions}
            actionsConfig={{ display: 'inline', label: 'Acoes', buttonSize: 'icon', showTooltips: true }}
            bulkActions={bulkActions}
            bulkAggregates={bulkAggregates}
            infiniteScrollConfig={{ fitViewport: true, viewportBottomOffset: 96, minHeight: 320 }}
            appearance={{ size: 'md', gridLines: 'none', rowStyle: 'soft-striped' }}
            searchPlaceholder="Buscar"
        />
    )
}
```

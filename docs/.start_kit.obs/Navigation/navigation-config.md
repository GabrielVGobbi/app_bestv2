---
title: Configuracao de Navegacao
tags:
  - navigation
  - laravel
  - inertia
aliases:
  - navigation.php
  - Navegacao
---

# Configuracao de Navegacao

## Contrato visual

A composição segue o [DESIGN.md canônico](../../../DESIGN.md): identidade própria
tematizável por `config/branding.php` sobre o shell validado. A navegação e sua
autorização continuam vindo deste projeto.

Quando `modulesMenuPosition = header`, a sidebar mostra diretamente os itens do
módulo, sem o antigo card “Módulo ativo”. O cabeçalho usa abas discretas e um
dropdown quando falta espaço. A sidebar é o Sidebar do Fluid Functionalism
(`resources/js/components/ui/fluid/`, seção “Sidebar Fluid” do DESIGN.md):
nas cores do shell, 240px expandida (largura arrastada salva no navegador),
faixa de ícones de 80px recolhida, seções recolhíveis e grupos com um nível de submenu. No mobile usa o drawer do Fluid com Escape,
contenção de foco e retorno ao botão de abertura.

O dashboard consome apenas os módulos já filtrados em `nav`: seus números são
contagens de recursos de navegação, não de registros do banco. Não importar
pedidos, cobranças ou políticas do Bionexis para preencher o layout.

As verificações visuais ficam em
`tests/Fixtures/Frontend/design-parity-browser.mjs`, executadas pelo PHPUnit em
`tests/Unit/Frontend/DesignParityTest.php`, com assets reais e props sintéticas.

Documentacao do arquivo [`config/navigation.php`](../../../config/navigation.php), usado como fonte unica da navegacao principal da aplicacao.

---

## Visao Geral

O arquivo `config/navigation.php` define os itens exibidos na sidebar do dashboard:

- estilo da sidebar (`flat` ou `modules`);
- itens globais acima da navegacao principal;
- modulos principais;
- itens e grupos dentro dos modulos;
- links de rodape.

Essa configuracao nao e consumida diretamente pelo React. Ela passa por `App\Services\NavigationService`, que filtra, ordena, resolve rotas nomeadas e entrega o resultado como prop global do Inertia.

O middleware obtém `resolved()` uma vez por resposta para compartilhar `global`,
`modules` e `footer`. Isso evita reconstruir a árvore três vezes no ambiente local
ou ler o cache persistente três vezes nos demais ambientes. Os getters individuais
continuam disponíveis; não há cache adicional entre requisições.

`nav`, `branding`, `name` e `layout` são *once props* (`shareOnce()`): o servidor
envia na primeira resposta e o cliente reaproveita nas visitas Inertia seguintes,
mandando `X-Inertia-Except-Once-Props`. Um deploy troca a versão dos assets e força
recarga completa. Em desenvolvimento, depois de editar `config/navigation.php`,
recarregue a aba (F5) para ver o menu novo.

Fluxo:

```text
config/navigation.php
    -> App\Services\NavigationService
    -> App\Http\Middleware\HandleInertiaRequests::shareOnce()
    -> page.props.nav
    -> resources/js/components/dashboard/dashboard-sidebar.tsx
```

---

## Arquivos Envolvidos

| Arquivo | Responsabilidade |
| --- | --- |
| `config/navigation.php` | Define a estrutura bruta da navegacao |
| `app/Services/NavigationService.php` | Filtra, ordena, normaliza e resolve URLs |
| `app/Http/Middleware/HandleInertiaRequests.php` | Compartilha `nav` em todas as paginas Inertia |
| `resources/js/types/navigation.ts` | Tipos do formato bruto e normalizado no frontend |
| `resources/js/lib/resolve-nav-items.ts` | Converte nomes de icones em componentes Lucide |
| `resources/js/lib/nav-icons.ts` | Mapa de icones permitidos na navegacao |
| `resources/js/components/dashboard/dashboard-sidebar.tsx` | Compõe a sidebar Fluid: modulos, secoes, grupos, rodape e conta |
| `resources/js/components/ui/fluid/` | Sidebar do Fluid Functionalism vendorizado (nao editar estilos ali) |
| `resources/js/contexts/nav-module-context.tsx` | Controla modulo ativo quando `style = modules` |

---

## Estrutura Principal

```php
return [
    'style' => 'flat',

    'global' => [
        // Itens sempre visiveis acima dos modulos.
    ],

    'modules' => [
        // Modulos principais.
    ],

    'footer' => [
        // Links exibidos no rodape da sidebar.
    ],
];
```

### `style`

Controla como a sidebar organiza os modulos.

| Valor | Comportamento |
| --- | --- |
| `flat` | Mostra todos os modulos como secoes em uma lista unica |
| `modules` | Mostra uma tela inicial de modulos; ao selecionar um modulo, exibe os itens dele |

No layout atual, o `NavModuleProvider` so e usado quando `style` e `modules`.

---

## Modulos

Cada entrada em `modules` representa uma area da aplicacao.

Exemplo:

```php
[
    'slug' => 'gestao',
    'label' => 'Gestao',
    'icon' => 'Users',
    'default' => false,
    'enabled' => true,
    'route_prefixes' => [
        '/users',
        '/roles',
        '/permissions',
    ],
    'items' => [
        // Itens do modulo.
    ],
],
```

Campos:

| Campo | Obrigatorio | Uso |
| --- | --- | --- |
| `slug` | Sim | Identificador unico do modulo |
| `label` | Sim | Texto exibido na sidebar |
| `icon` | Nao | Nome de icone Lucide registrado em `nav-icons.ts` |
| `default` | Nao | Define o modulo inicial quando nenhum prefixo bate |
| `enabled` | Nao | `false` remove o modulo antes de chegar ao frontend |
| `route_prefixes` | Nao | Prefixos de URL usados para detectar modulo ativo |
| `href` | Nao | URL do modulo quando ele funciona como link direto |
| `items` | Nao | Itens e grupos internos do modulo |

### Modulo Com Itens

Use quando o modulo agrupa varias telas.

```php
[
    'slug' => 'relatorios',
    'label' => 'Relatorios',
    'icon' => 'TableProperties',
    'default' => false,
    'enabled' => true,
    'route_prefixes' => [
        '/relatorios',
    ],
    'items' => [
        [
            'slug' => 'relatorios_vendas',
            'label' => 'Vendas',
            'icon' => 'TableProperties',
            'type' => 'link',
            'href' => ['route' => 'reports.sales.index'],
            'order' => 1,
            'enabled' => true,
            'visible' => true,
        ],
    ],
],
```

### Modulo Como Link Direto

Use quando o modulo nao tem submenu.

```php
[
    'slug' => 'dashboard',
    'label' => 'Dashboard',
    'icon' => 'LayoutGrid',
    'default' => true,
    'enabled' => true,
    'route_prefixes' => [],
    'href' => '/dashboard',
],
```

No frontend, um modulo sem `items` e com `href` vira um link direto.

---

## Itens

Itens aparecem em `global`, `modules.*.items`, grupos aninhados e `footer`.

Exemplo:

```php
[
    'slug' => 'usuarios',
    'label' => 'Usuarios',
    'icon' => 'Users',
    'type' => 'link',
    'href' => ['route' => 'users.index'],
    'order' => 1,
    'enabled' => true,
    'visible' => true,
],
```

Campos:

| Campo | Obrigatorio | Uso |
| --- | --- | --- |
| `slug` | Recomendado | Identificador unico no config |
| `label` | Sim | Texto exibido; vira `title` no frontend |
| `icon` | Nao | Nome de icone registrado em `nav-icons.ts` |
| `type` | Recomendado | `link` ou `group`; documenta a intencao do item |
| `href` | Para links | URL literal ou rota nomeada |
| `order` | Nao | Ordem dentro da lista; menor aparece primeiro |
| `enabled` | Nao | `false` remove o item no backend |
| `visible` | Nao | `false` chega ao frontend, mas e ocultado no render |
| `items` | Para grupos | Subitens aninhados |

Observacao: `slug`, `type` e `order` nao sao enviados ao frontend no formato final. Eles existem para organizacao e processamento do backend.

---

## Grupos

Um grupo e um item com `items`.

```php
[
    'slug' => 'componentes',
    'label' => 'Componentes',
    'icon' => 'Puzzle',
    'type' => 'group',
    'order' => 2,
    'enabled' => true,
    'visible' => true,
    'items' => [
        [
            'slug' => 'componentes_indice',
            'label' => 'Indice',
            'icon' => 'LayoutGrid',
            'type' => 'link',
            'href' => ['route' => 'admin.dev.components.index'],
            'order' => 1,
            'enabled' => true,
            'visible' => true,
        ],
    ],
],
```

No modo `flat`, grupos viram itens expansivos. O clique abre e fecha os filhos.

No modo `modules`, os itens do modulo ativo sao renderizados pela sidebar Fluid (`dashboard-sidebar.tsx`): secoes viram grupos recolhiveis e `group` vira linha-pai com submenu de um nivel; grupos mais profundos sao achatados nesse submenu.

---

## Formatos de `href`

### URL Literal

Use para caminhos simples ou links externos.

```php
'href' => '/dashboard',
```

```php
'href' => 'https://github.com/laravel/react-starter-kit',
```

### Rota Nomeada

Preferido para rotas internas.

```php
'href' => ['route' => 'users.index'],
```

O `NavigationService` resolve com:

```php
route($href['route'], $href['params'] ?? [], absolute: false)
```

Ou seja, a URL compartilhada com o frontend e relativa.

### Rota Nomeada Com Parametros

```php
'href' => [
    'route' => 'users.show',
    'params' => ['user' => 1],
],
```

Use somente quando o parametro for estatico ou seguro para estar no config. Para IDs dinamicos por usuario ou contexto, prefira montar o link no componente ou em uma prop propria da pagina.

---

## Como Adicionar Um Novo Item

1. Confirme que a rota existe e tem nome:

```bash
php artisan route:list --except-vendor --name=users
```

2. Escolha o modulo correto em `config/navigation.php`.

3. Adicione o item em `items`, usando `href` com rota nomeada:

```php
[
    'slug' => 'clientes',
    'label' => 'Clientes',
    'icon' => 'Users',
    'type' => 'link',
    'href' => ['route' => 'clients.index'],
    'order' => 6,
    'enabled' => true,
    'visible' => true,
],
```

4. Se a tela pertence a um novo prefixo de URL, adicione-o em `route_prefixes` do modulo:

```php
'route_prefixes' => [
    '/users',
    '/clients',
],
```

5. Se o icone ainda nao existir em `resources/js/lib/nav-icons.ts`, importe-o de `lucide-react` e adicione ao `NAV_ICONS`.

6. Em ambiente nao local, limpe o cache depois do deploy ou alteracao:

```bash
php artisan cache:clear
```

---

## Como Adicionar Um Novo Modulo

```php
[
    'slug' => 'financeiro',
    'label' => 'Financeiro',
    'icon' => 'TableProperties',
    'default' => false,
    'enabled' => true,
    'route_prefixes' => [
        '/financeiro',
    ],
    'items' => [
        [
            'slug' => 'financeiro_dashboard',
            'label' => 'Dashboard',
            'icon' => 'LayoutGrid',
            'type' => 'link',
            'href' => ['route' => 'financeiro.dashboard'],
            'order' => 1,
            'enabled' => true,
            'visible' => true,
        ],
    ],
],
```

Regras praticas:

- `slug` deve ser unico entre os modulos.
- `route_prefixes` deve usar caminhos da URL, nao nomes de rota.
- Prefixos mais especificos sao melhores quando ha sobreposicao.
- So um modulo deve ter `default => true` para evitar ambiguidade.
- A ordem dos modulos e a ordem do array `modules`; itens internos usam `order`.

---

## Como Adicionar Um Link Global

Itens globais ficam acima dos modulos e aparecem sempre.

```php
'global' => [
    [
        'slug' => 'dashboard_global',
        'label' => 'Dashboard',
        'icon' => 'LayoutGrid',
        'type' => 'link',
        'href' => ['route' => 'app.dashboard'],
        'order' => 1,
        'enabled' => true,
        'visible' => true,
    ],
],
```

Se `global` nao existir ou estiver vazio, a area global nao e renderizada.

---

## Como Adicionar Um Link de Rodape

Links de rodape sao tratados como links externos na sidebar atual.

```php
'footer' => [
    [
        'slug' => 'documentacao_interna',
        'label' => 'Docs',
        'icon' => 'BookOpen',
        'type' => 'link',
        'href' => 'https://example.com/docs',
        'order' => 1,
        'enabled' => true,
        'visible' => true,
    ],
],
```

O rodape fica fixo no `SidebarFooter`, acima da conta; na faixa recolhida, os links viram icones com tooltip.

---

## Como Funciona a Normalizacao

`NavigationService` monta tres listas:

```php
[
    'global' => ...,
    'modules' => ...,
    'footer' => ...,
]
```

### Para Modulos

Entrada:

```php
[
    'slug' => 'gestao',
    'label' => 'Gestao',
    'icon' => 'Users',
    'default' => false,
    'route_prefixes' => ['/users'],
    'items' => [...],
]
```

Saida enviada ao frontend:

```php
[
    'slug' => 'gestao',
    'label' => 'Gestao',
    'icon' => 'Users',
    'default' => false,
    'routePrefixes' => ['/users'],
    'href' => null,
    'items' => [...],
]
```

### Para Itens

Entrada:

```php
[
    'slug' => 'usuarios',
    'label' => 'Usuarios',
    'icon' => 'Users',
    'href' => ['route' => 'users.index'],
    'order' => 1,
    'enabled' => true,
    'visible' => true,
]
```

Saida enviada ao frontend:

```php
[
    'title' => 'Usuarios',
    'icon' => 'Users',
    'visible' => true,
    'href' => '/users',
]
```

Depois, o frontend converte `icon: 'Users'` em componente React usando `resolveIcon()`.

---

## Ativacao do Modulo Atual

Quando `style = modules`, o frontend detecta o modulo ativo pela URL atual.

Regras:

- apenas modulos com `items` entram na deteccao;
- `route_prefixes` sao comparados com `pathname.startsWith(prefix)`;
- prefixos maiores sao testados primeiro;
- se nenhum prefixo bater, a sidebar volta para a lista de modulos;
- o modulo padrao e o primeiro modulo com `items` e `default => true`; se nao houver, usa o primeiro modulo com `items`.

Exemplo:

```php
'route_prefixes' => [
    '/admin/dev',
],
```

Uma URL como `/admin/dev/components` ativa esse modulo.

---

## Cache

Em ambiente local, a navegacao e reconstruida a cada request.

Em ambientes nao locais, `NavigationService` usa:

```php
Cache::rememberForever('navigation.v4', ...)
```

Esse cache guarda a estrutura de navegacao derivada da configuracao, nao decisoes de autoridade do usuario. Exibir ou ocultar um item de menu nao autoriza endpoints: autenticacao, capacidades, policies, alvo e ambiente continuam sendo avaliados pelo servidor a cada operacao.

Para invalidar:

```bash
php artisan cache:clear
```

Ou, em codigo:

```php
app(\App\Services\NavigationService::class)->flush();
```

Sempre limpe o cache depois de alterar `config/navigation.php` em staging ou producao.

---

## Checklist

Antes de finalizar uma alteracao de navegacao:

- A rota usada em `href` existe e tem nome.
- O `slug` e unico dentro do contexto.
- O `order` nao conflita de forma confusa com itens vizinhos.
- O `route_prefixes` cobre as URLs da nova tela.
- O icone existe em `resources/js/lib/nav-icons.ts`.
- Links externos usam URL completa com `https://`.
- Em ambiente nao local, o cache foi limpo.

---

## Armadilhas Conhecidas

### Icone Nao Aparece

O nome do icone em `config/navigation.php` precisa existir no mapa `NAV_ICONS`.

Corrija em `resources/js/lib/nav-icons.ts`:

```ts
import { NewIcon } from 'lucide-react';

const NAV_ICONS: Record<string, LucideIcon> = {
    NewIcon,
};
```

### Item Nao Aparece

Verifique:

- `enabled` nao esta como `false`;
- `visible` nao esta como `false`;
- o item esta dentro do modulo correto;
- em producao/staging, o cache foi limpo.

### Modulo Nao Fica Ativo

Verifique se `route_prefixes` contem o caminho real da URL.

Use caminho, nao nome de rota:

```php
'route_prefixes' => ['/users'],
```

Nao use:

```php
'route_prefixes' => ['users.index'],
```

### Rota Nomeada Quebra a Pagina

Se uma rota nomeada nao existir ou exigir parametros ausentes, `NavigationService` falha ao resolver o `href`.

Confirme com:

```bash
php artisan route:list --except-vendor --name=nome.da.rota
```

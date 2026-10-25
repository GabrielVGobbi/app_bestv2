# Fluid Functionalism (vendorizado)

Cópia de componentes do [Fluid Functionalism](https://www.fluidfunctionalism.com),
flavor Radix, com o fechamento de dependências do registry. Licença MIT em
[LICENSE](LICENSE).

- Origem: `github.com/mickadesign/fluid-functionalism`, commit `7387f36` (22/10/2026).
- Componentes: Sidebar (lote 1); Tooltip, Dropdown, Select e Tabs (lote 2).
- Equivale a `npx shadcn add https://www.fluidfunctionalism.com/r/<item>.json`,
  mas isolado aqui: o CLI com `--overwrite` substituiria `components/ui/button.tsx`,
  `tooltip.tsx`, `dialog.tsx` e outros primitivos usados pela plataforma inteira.
  Após a migração tela por tela, `components/ui/select`, `tabs` e `dropdown-menu`
  foram removidos; Select, Tabs e Dropdown existem só aqui.
- Dependências npm: `motion`, `@radix-ui/react-scroll-area`, `@radix-ui/react-dialog`,
  `@radix-ui/react-tooltip`, `@radix-ui/react-dropdown-menu`, `@radix-ui/react-select`,
  `@radix-ui/react-tabs`, `@radix-ui/react-slot`, `class-variance-authority`, `lucide-react`.
- Tokens (`surface-*`, `hover`, `active`, `focus-ring`) e utilitários `scroll-fade` /
  `scroll-divider` ficam em `resources/css/app.css`, mapeados para a paleta do Start Kit.

## Mapa de arquivos

| Registry                                          | Aqui                                   |
| ------------------------------------------------- | -------------------------------------- |
| `registry/radix/sidebar.tsx`                      | `sidebar.tsx`                          |
| `registry/default/sidebar-*.tsx`                  | `sidebar-core.tsx`, `sidebar-menu.tsx` |
| `registry/radix/{button,tooltip,scroll-area}.tsx` | mesmos nomes                           |
| `registry/radix/{dropdown,select,tabs,tabs-subtle}.tsx` | mesmos nomes                     |
| `registry/default/{menu-item,dropdown-search,dropdown-sub}.tsx` | mesmos nomes             |
| `registry/default/fluid-hover-highlight.tsx`      | mesmo nome                             |
| `registry/default/hooks/*`                        | `hooks/`                               |
| `registry/default/lib/*`                          | `lib/`                                 |

Imports `@/lib/<x>`, `@/hooks/<x>` e `@/components/ui/<x>` foram reescritos para
`@/components/ui/fluid/...`; `@/lib/utils` continua sendo o `cn()` do projeto.

## Uso nas telas

- Tooltip: `<Tooltip content="..." side="bottom">{gatilho}</Tooltip>`.
- Dropdown: `DropdownMenu` + `DropdownTrigger render={<Button/>}` + `DropdownContent`
  com `MenuItem index label icon onSelect`. Índices começam em 0, seguem a ordem
  visível e não mudam com o menu aberto. `checked` boolean vira `menuitemradio`
  (com `checkedIndex`) ou checkbox (com `checkedIndices`, o menu fica aberto).
  Navegação e downloads vão em `onSelect` (`router.visit`, `window.open`): o item
  é uma linha, não um link.
- Select: `SelectTrigger` já mostra o valor; `SelectItem` exige `index` e `value`.
- Tabs: `Tabs value onValueChange` > `TabsList` > `TabItem value label`, e o
  conteúdo filtrado em `TabPanel`.
- Portais do Radix propagam eventos pela árvore React: dentro de uma linha clicável,
  pare a propagação num wrapper (ver `modules/uploads/components/upload-item.tsx`).

## Patches locais

Ao atualizar a partir do upstream, reaplicar:

1. Textos em pt-BR: gatilho (`Expandir menu` / `Encolher menu`, também no `aria-label`),
   rail (`Arraste para redimensionar`, `Clique para encolher`), faixa de peek
   (`Espiar menu`), o texto oculto `, não lido` e os placeholders padrão
   `Buscar…` (`dropdown-search.tsx`) e `Selecionar…` (`select.tsx`).
2. Drawer mobile (`sidebar.tsx`): título `Navegação principal` e uma
   `DialogPrimitive.Description`, no lugar de `aria-describedby={undefined}`.
3. Drawer mobile: devolve o foco a quem o abriu (`returnFocusRef` em
   `onOpenAutoFocus` / `onCloseAutoFocus`). O gatilho fica fora de
   `Dialog.Trigger`, então o Radix sozinho deixa o foco no `body`.
4. Raios em px explícitos (`lib/shape-context.tsx`, inset e skeleton): o
   `@theme` do projeto redefine `rounded-lg`/`xl`/`2xl`/`3xl`/`md`.
5. Comentários sem travessão (regra do repositório): cada travessão virou vírgula.
6. `menu-item.tsx`: prop `destructive` (ícone e rótulo em `text-destructive`) para
   ações como Excluir.
7. `select.tsx`: o wrapper do `SelectTrigger` ganha `has-[>.w-full]:w-full`; dentro
   de uma linha `flex` ele encolhia e um gatilho `w-full` ficava estreito.
8. `select.tsx`: variante `bordered` usa `border-input` (borda de campo, 3:1 no
   escuro pelo DESIGN.md) em vez de `border-border`.
9. Texto sobre `bg-foreground` usa `popover` em vez de `background` (tooltip, atalho
   `kbd` do `sidebar-core` e variante `primary` do botão): o `--background` claro do
   projeto é translúcido e deixava o texto do tooltip quase invisível.
10. `sidebar-core.tsx`: prop `collapsedWidth` no `SidebarProvider` recolhe para uma
    faixa de ícones daquela largura (em vez de offcanvas; peek desligado). O rail
    arrastado a partir da faixa reexpande e, ao recolher por arraste, preserva a
    largura de onde o arraste começou.

Não editar estilos aqui para ajustar a plataforma: compor por props e `className`
nos consumidores.

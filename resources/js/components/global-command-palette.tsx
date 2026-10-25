import { router, usePage } from '@inertiajs/react';
import { Contact, Music2, Plus, ReceiptText, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem as CommandResult,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { resolveNavModules } from '@/lib/resolve-nav-items';
import { create as createClient } from '@/routes/clients';
import { create as createFinancialEntry } from '@/routes/financial-entries';
import { create as createPlaylist } from '@/routes/playlists';
import { create as createSale } from '@/routes/sales';
import type { CommandItem, NavItem, User } from '@/types';

function flattenNavigation(items: NavItem[], group: string): CommandItem[] {
    return items.flatMap((item, index) => {
        const current =
            item.href && item.href !== '#' && item.type !== 'section'
                ? [
                      {
                          id: `nav-${group}-${index}-${item.title}`,
                          group: 'Navegação' as const,
                          label: item.title,
                          description: group,
                          keywords: [group, item.title],
                          href: String(item.href),
                          icon: item.icon,
                      },
                  ]
                : [];

        return [
            ...current,
            ...(item.items ? flattenNavigation(item.items, group) : []),
        ];
    });
}

function hasPermission(user: User, permission: string): boolean {
    if (user.roles?.some((role) => role.slug === 'developer')) {
        return true;
    }

    if (user.permissions?.some((item) => item.slug === permission)) {
        return true;
    }

    return (
        user.roles?.some((role) =>
            role.permissions?.some((item) => item.slug === permission),
        ) ?? false
    );
}

export function GlobalCommandPalette() {
    const { auth, nav } = usePage().props;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const items = useMemo<CommandItem[]>(() => {
        const modules = resolveNavModules(nav.modules);
        const navigation = modules.flatMap((module) =>
            flattenNavigation(module.items, module.label),
        );
        const actions: Array<CommandItem & { permission: string }> = [
            {
                id: 'action-create-client',
                group: 'Ações',
                label: 'Novo cliente',
                description: 'Abrir cadastro de cliente',
                keywords: ['criar', 'cadastrar', 'cliente'],
                href: createClient().url,
                icon: Contact,
                permission: 'clients.create',
            },
            {
                id: 'action-create-sale',
                group: 'Ações',
                label: 'Nova venda',
                description: 'Iniciar uma venda em rascunho',
                keywords: ['criar', 'venda', 'orçamento'],
                href: createSale().url,
                icon: ReceiptText,
                permission: 'sales.create',
            },
            {
                id: 'action-create-playlist',
                group: 'Ações',
                label: 'Nova playlist',
                description: 'Cadastrar playlist',
                keywords: ['criar', 'playlist', 'spotify'],
                href: createPlaylist().url,
                icon: Music2,
                permission: 'playlists.create',
            },
            {
                id: 'action-create-financial-entry',
                group: 'Ações',
                label: 'Novo lançamento',
                description: 'Registrar entrada ou saída financeira',
                keywords: ['criar', 'financeiro', 'lançamento'],
                href: createFinancialEntry().url,
                icon: Plus,
                permission: 'finance.entries.create',
            },
        ];

        return [
            ...navigation,
            ...actions.filter((item) =>
                hasPermission(auth.user, item.permission),
            ),
        ];
    }, [auth.user, nav.modules]);

    const groups = ['Navegação', 'Ações'] as const;
    const visit = (item: CommandItem) => {
        setOpen(false);
        router.visit(item.href);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group flex size-9 items-center justify-center gap-2 rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground xl:w-56 xl:justify-start xl:border xl:border-border xl:bg-muted/50 xl:px-3"
                aria-label="Buscar páginas e ações"
            >
                <Search className="size-4 shrink-0" />
                <span className="hidden min-w-0 flex-1 text-left text-sm xl:block">
                    Buscar
                </span>
                <kbd className="hidden rounded border bg-background px-1 py-0.5 text-[10px] text-muted-foreground xl:inline-flex">
                    Ctrl K
                </kbd>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="top-[14vh] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl">
                    <DialogTitle className="sr-only">Busca global</DialogTitle>
                    <DialogDescription className="sr-only">
                        Pesquise páginas e ações disponíveis para seu acesso.
                    </DialogDescription>
                    <Command className="rounded-xl">
                        <CommandInput placeholder="Busque por módulo, página ou ação..." />
                        <CommandList className="max-h-[min(28rem,65vh)] p-2">
                            <CommandEmpty>
                                Nenhum resultado encontrado.
                            </CommandEmpty>
                            {groups.map((group, index) => {
                                const groupItems = items.filter(
                                    (item) => item.group === group,
                                );

                                if (groupItems.length === 0) {
                                    return null;
                                }

                                return (
                                    <div key={group}>
                                        {index > 0 && (
                                            <CommandSeparator className="my-2" />
                                        )}
                                        <CommandGroup heading={group}>
                                            {groupItems.map((item) => {
                                                const Icon = item.icon;

                                                return (
                                                    <CommandResult
                                                        key={item.id}
                                                        value={`${item.label} ${item.keywords.join(' ')}`}
                                                        onSelect={() =>
                                                            visit(item)
                                                        }
                                                        className="min-h-12 rounded-lg px-3"
                                                    >
                                                        {Icon && (
                                                            <Icon className="size-4 text-muted-foreground" />
                                                        )}
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate font-medium">
                                                                {item.label}
                                                            </span>
                                                            {item.description && (
                                                                <span className="block truncate text-xs text-muted-foreground">
                                                                    {
                                                                        item.description
                                                                    }
                                                                </span>
                                                            )}
                                                        </span>
                                                    </CommandResult>
                                                );
                                            })}
                                        </CommandGroup>
                                    </div>
                                );
                            })}
                        </CommandList>
                    </Command>
                </DialogContent>
            </Dialog>
        </>
    );
}

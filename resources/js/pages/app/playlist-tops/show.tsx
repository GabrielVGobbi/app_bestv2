import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarDays, Hash, ListOrdered, Pencil } from 'lucide-react';

import {
    edit,
    index,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/TopController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import type { TopResource } from '@/types/app/top';

interface Props {
    topData: TopResource;
    permissions: {
        can_update: boolean;
    };
}

function cleanValue(value: unknown): string {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '-';
}

function InfoField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm break-words">{value}</p>
        </div>
    );
}

export default function ShowTop({ topData, permissions }: Props) {
    const top = topData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'TOPs', href: index.url() },
            { title: top.name, href: show.url(top.uuid) },
        ],
    });

    return (
        <>
            <Head title={top.name} />

            <Header title={top.name} backHref={index.url()}>
                {permissions.can_update ? (
                    <Button variant="outline" asChild>
                        <Link href={edit.url(top.uuid)}>
                            <Pencil className="size-4" aria-hidden="true" />
                            Editar
                        </Link>
                    </Button>
                ) : null}
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <Card className="gap-0 py-0">
                            <div className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground">
                                    <ListOrdered
                                        className="size-12"
                                        aria-hidden="true"
                                    />
                                </div>

                                <div className="mt-4 space-y-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <h2 className="truncate text-base font-semibold">
                                            {top.name}
                                        </h2>
                                        <Badge
                                            variant={
                                                top.is_active
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {top.is_active
                                                ? 'Ativo'
                                                : 'Inativo'}
                                        </Badge>
                                    </div>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {top.slug}
                                    </p>
                                </div>

                                <div className="mt-5 flex items-center gap-2 text-sm text-muted-foreground">
                                    <Hash
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span className="tabular-nums">
                                        {top.default_slots_quantity} vagas
                                        padrão
                                    </span>
                                </div>
                            </div>

                            <div className="border-t border-separator px-5 py-4 text-xs text-muted-foreground">
                                <div className="flex items-center gap-2">
                                    <CalendarDays
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span className="tabular-nums">
                                        Criado {formatDate(top.created_at)}
                                    </span>
                                </div>
                                <p className="mt-1 tabular-nums">
                                    Última edição {formatDate(top.updated_at)}
                                </p>
                            </div>
                        </Card>
                    </aside>

                    <main className="min-w-0 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Dados comerciais</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <InfoField label="Nome" value={top.name} />
                                <InfoField label="Slug" value={top.slug} />
                                <InfoField
                                    label="Vagas padrão"
                                    value={String(top.default_slots_quantity)}
                                />
                                <InfoField
                                    label="Ordem"
                                    value={String(top.sort_order)}
                                />
                                <InfoField
                                    label="Playlists vinculadas"
                                    value={cleanValue(
                                        top.playlist_tops_count ?? 0,
                                    )}
                                />
                                <InfoField
                                    label="Status"
                                    value={top.is_active ? 'Ativo' : 'Inativo'}
                                />
                            </CardContent>
                        </Card>

                        {top.description ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Descrição</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                        {top.description}
                                    </p>
                                </CardContent>
                            </Card>
                        ) : null}
                    </main>
                </div>
            </div>
        </>
    );
}

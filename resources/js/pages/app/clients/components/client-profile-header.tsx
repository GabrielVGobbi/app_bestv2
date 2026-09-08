import { Link } from '@inertiajs/react';
import { ArrowLeft, SquarePen } from 'lucide-react';

import {
    destroy,
    edit,
    index,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { CopyableText } from '@/components/ui/copyable-text';
import { Separator } from '@/components/ui/separator';
import type { Client } from '@/types/app/client';

import { isBlank } from '../partials/client-show-helpers';

interface ClientProfileHeaderProps {
    client: Client;
    backHref: string;
    backAriaLabel: string;
    showEditAction?: boolean;
}

export function ClientProfileHeader({
    client,
    backHref,
    backAriaLabel,
    showEditAction = false,
}: ClientProfileHeaderProps) {
    const clientName = String(client.name ?? client.uuid);
    const subtitle = !isBlank(client.company_name)
        ? client.company_name
        : !isBlank(client.email)
          ? client.email
          : null;
    const canUpdate = client.permissions.can_update;

    return (
        <Header>
            <div className="flex min-w-0 items-center gap-3">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8 shrink-0"
                    asChild
                >
                    <Link href={backHref} aria-label={backAriaLabel}>
                        <ArrowLeft className="size-4" />
                    </Link>
                </Button>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="truncate text-xl font-semibold">
                            {clientName}
                        </h1>
                    </div>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                        {subtitle ? (
                            <span className="truncate">{subtitle}</span>
                        ) : null}
                        {subtitle ? (
                            <span className="text-border" aria-hidden>
                                ·
                            </span>
                        ) : null}
                        <CopyableText
                            value={client.uuid}
                            label="ID"
                            className="text-xs text-muted-foreground"
                        >
                            <span className="underline-offset-2 group-hover/copyable:underline">
                                Copiar ID
                            </span>
                        </CopyableText>
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-2">
                {showEditAction && canUpdate ? (
                    <>
                        <Button variant="outline" size="action" asChild>
                            <Link href={edit.url(client.uuid)}>
                                <SquarePen className="size-4" />
                                Editar
                            </Link>
                        </Button>
                        <Separator
                            orientation="vertical"
                            className="mx-1 h-6"
                        />
                    </>
                ) : null}
                <DeleteButton
                    url={destroy.url(client.uuid)}
                    resourceName={clientName}
                    resourceType="cliente"
                    redirectTo={index.url()}
                    size="action"
                />
            </div>
        </Header>
    );
}

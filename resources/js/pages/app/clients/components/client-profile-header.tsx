import { Link } from '@inertiajs/react';
import { SquarePen } from 'lucide-react';

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
    showEditAction?: boolean;
}

export function ClientProfileHeader({
    client,
    backHref,
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
        <Header
            title={clientName}
            subtitle={subtitle ?? undefined}
            backHref={backHref}
        >
            <div className="flex flex-wrap items-center gap-2">
                <CopyableText
                    value={client.uuid}
                    label="ID"
                    className="text-xs text-muted-foreground"
                >
                    <span className="underline-offset-2 group-hover/copyable:underline">
                        Copiar ID
                    </span>
                </CopyableText>
                <Separator orientation="vertical" className="mx-1 h-6" />
                {showEditAction && canUpdate ? (
                    <>
                        <Button variant="outline" asChild>
                            <Link href={edit.url(client.uuid)}>
                                <SquarePen
                                    className="size-4"
                                    aria-hidden="true"
                                />
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
                />
            </div>
        </Header>
    );
}

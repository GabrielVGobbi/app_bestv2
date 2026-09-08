import { FileText, Mail, Phone, UserRound } from 'lucide-react';

import { edit } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { Separator } from '@/components/ui/separator';
import { formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import type { Client } from '@/types/app/client';

import {
    DefaultAddressSummary,
    SidebarContactRow,
    SidebarMetaRow,
} from '../partials/client-show-helpers';

interface ClientProfileSidebarProps {
    client: Client;
    showEmptyContactLinks?: boolean;
}

export function ClientProfileSidebar({
    client,
    showEmptyContactLinks = false,
}: ClientProfileSidebarProps) {
    const clientName = String(client.name ?? client.uuid);
    const responsibleUser = client.responsible_user;
    const addresses = client.addresses ?? [];
    const defaultAddress = addresses.find((address) => address.is_default);
    const canUpdate = client.permissions.can_update;
    const emptyHref =
        showEmptyContactLinks && canUpdate ? edit.url(client.uuid) : undefined;

    const initials = clientName
        .split(' ')
        .map((namePart) => namePart[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <aside className="lg:sticky lg:top-4 lg:self-start">
            <div className="rounded-lg border bg-card">
                <div className="p-5">
                    <div className="flex h-20 items-center justify-center rounded-lg bg-secondary text-2xl font-semibold text-primary">
                        {initials}
                    </div>

                    <div className="mt-5 space-y-0.5">
                        <SidebarContactRow
                            icon={FileText}
                            label="Documento"
                            value={formatCpfCnpj(client.document)}
                            copyable
                            emptyHref={emptyHref}
                            emptyLabel={
                                emptyHref ? 'Adicionar documento' : undefined
                            }
                        />
                        <SidebarContactRow
                            icon={Mail}
                            label="Email"
                            value={client.email}
                            copyable
                            emptyHref={emptyHref}
                            emptyLabel={
                                emptyHref ? 'Adicionar email' : undefined
                            }
                        />
                        <SidebarContactRow
                            icon={Phone}
                            label="Telefone"
                            value={formatPhone(client.phone)}
                            copyable
                            emptyHref={emptyHref}
                            emptyLabel={
                                emptyHref ? 'Adicionar telefone' : undefined
                            }
                        />
                    </div>

                    <Separator className="my-4" />

                    <div className="space-y-0.5">
                        <SidebarMetaRow
                            icon={UserRound}
                            label="Responsável"
                            value={
                                responsibleUser?.name ?? responsibleUser?.email
                            }
                        />
                        <DefaultAddressSummary address={defaultAddress} />
                    </div>
                </div>

                <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                    <p>Criado · {formatDate(client.created_at)}</p>
                    <p className="mt-1">
                        Atualizado · {formatDate(client.updated_at)}
                    </p>
                </div>
            </div>
        </aside>
    );
}

import { Form } from '@inertiajs/react';
import { Eye, RefreshCw } from 'lucide-react';
import { useState } from 'react';

import { syncItem } from '@/actions/App/Modules/Sales/Http/Controllers/SaleItemSpotifyPresenceController';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn, formatDate } from '@/lib/utils';
import type { SaleItem } from '@/types/app/sale';

type Presence = SaleItem['spotify_playlist_presence'];

function matchTypeLabel(matchType: Presence['match_type']): string | null {
    if (matchType === 'track_id') {
        return 'ID Spotify';
    }

    if (matchType === 'isrc') {
        return 'ISRC';
    }

    return null;
}

function presenceExplanation(presence: Presence): string {
    if (presence.status === 'present') {
        const position = presence.track_position
            ? ` na posição #${presence.track_position}`
            : '';
        const match = matchTypeLabel(presence.match_type);
        const matchText = match ? ` O encontro foi pelo ${match}.` : '';

        return `A faixa foi encontrada na playlist${position}.${matchText}`;
    }

    if (presence.status === 'missing') {
        return 'A faixa não está na playlist neste momento.';
    }

    if (presence.status === 'spotify_unavailable') {
        return 'A API do Spotify não liberou os dados desta playlist para esta credencial.';
    }

    if (presence.status === 'playlist_missing_id') {
        return 'A playlist não possui ID do Spotify configurado, então a verificação não pode ser feita.';
    }

    return 'Ainda não verificamos se esta faixa está na playlist do Spotify.';
}

interface SpotifyPresenceControlProps {
    saleUuid: string;
    item: SaleItem;
    canVerify: boolean;
}

export function SpotifyPresenceControl({
    saleUuid,
    item,
    canVerify,
}: SpotifyPresenceControlProps) {
    const [isExplainOpen, setIsExplainOpen] = useState(false);

    if (item.type !== 'playlist_slot') {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    const presence = item.spotify_playlist_presence;
    const hasDetails = Boolean(
        presence.checked_at ||
            presence.track_position ||
            presence.match_type ||
            presence.error,
    );

    return (
        <>
            <div className="group inline-flex items-center gap-0.5">
                <StatusBadge presenter={presence.status_presenter} />
                <div
                    className={cn(
                        'flex items-center overflow-hidden opacity-0 transition-opacity',
                        'w-0 group-hover:w-auto group-hover:opacity-100 group-focus-within:w-auto group-focus-within:opacity-100',
                        'max-md:w-auto max-md:opacity-100',
                        '[@media(hover:none)]:w-auto [@media(hover:none)]:opacity-100',
                    )}
                >
                    {canVerify ? (
                        <Form
                            action={syncItem({
                                sale: saleUuid,
                                saleItem: item.uuid,
                            })}
                            options={{
                                preserveScroll: true,
                                only: ['saleData', 'permissions', 'flash'],
                            }}
                            disableWhileProcessing
                            className="inline-flex"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground"
                                    aria-label="Revalidar presença no Spotify"
                                    disabled={processing}
                                >
                                    <RefreshCw
                                        className={cn(
                                            'size-3.5',
                                            processing && 'animate-spin',
                                        )}
                                    />
                                </Button>
                            )}
                        </Form>
                    ) : null}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 text-muted-foreground"
                        aria-label="Ver motivo do status"
                        onClick={() => setIsExplainOpen(true)}
                    >
                        <Eye className="size-3.5" />
                    </Button>
                </div>
            </div>

            <Dialog open={isExplainOpen} onOpenChange={setIsExplainOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {presence.status_presenter.label}
                        </DialogTitle>
                        <DialogDescription>
                            {presenceExplanation(presence)}
                        </DialogDescription>
                    </DialogHeader>

                    {hasDetails ? (
                        <dl className="divide-y divide-border/70 overflow-hidden rounded-lg border border-border/70">
                            {presence.checked_at ? (
                                <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-3 px-3 py-2">
                                    <dt className="text-[11px] text-muted-foreground">
                                        Verificado em
                                    </dt>
                                    <dd className="text-xs font-medium">
                                        {formatDate(presence.checked_at)}
                                    </dd>
                                </div>
                            ) : null}
                            {presence.track_position ? (
                                <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-3 px-3 py-2">
                                    <dt className="text-[11px] text-muted-foreground">
                                        Posição
                                    </dt>
                                    <dd className="text-xs font-medium">
                                        #{presence.track_position}
                                    </dd>
                                </div>
                            ) : null}
                            {matchTypeLabel(presence.match_type) ? (
                                <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-3 px-3 py-2">
                                    <dt className="text-[11px] text-muted-foreground">
                                        Match
                                    </dt>
                                    <dd className="text-xs font-medium">
                                        {matchTypeLabel(presence.match_type)}
                                    </dd>
                                </div>
                            ) : null}
                            {presence.error ? (
                                <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-3 px-3 py-2">
                                    <dt className="text-[11px] text-muted-foreground">
                                        Detalhe
                                    </dt>
                                    <dd className="text-xs leading-5 text-muted-foreground">
                                        {presence.error}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn, formatDate } from '@/lib/utils';

import type { TimelineActivityItem as TimelineActivityItemType } from '../api/types';
import { timelineIconFor } from '../lib/timeline-icons';

const severityClass: Record<string, string> = {
    muted: 'border-border/80 bg-muted text-muted-foreground',
    info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-300',
    success:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-300',
    warning:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-300',
    danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/70 dark:bg-red-950/30 dark:text-red-300',
};

interface TimelineActivityItemProps {
    item: TimelineActivityItemType;
}

export function TimelineActivityItem({ item }: TimelineActivityItemProps) {
    const hasChanges = item.changes.length > 0;

    return (
        <article className="relative flex gap-3">
            <div className="relative flex w-7 shrink-0 justify-center">
                <div className="absolute top-6 bottom-0 w-px bg-border/70" />
                <div
                    className={cn(
                        'relative z-10  flex size-6 shrink-0 items-center justify-center rounded-full border',
                        severityClass[item.severity],
                    )}
                >
                    {timelineIconFor(item.event, 'size-3.5')}
                </div>
            </div>

            <div className="min-w-0 flex-1">
                {hasChanges ? (
                    <ActivityDetailsDialog item={item} />
                ) : (
                    <>
                        <div className="flex flex-wrap items-baseline ">
                            {item.label && (
                                <span className="text-xs font-medium text-foreground">
                                    {item.label}
                                </span>
                            )}
                            <time className="text-xs text-muted-foreground">
                                {formatDate(item.created_at)}
                            </time>
                        </div>
                        <p className=" max-w-[72ch] text-sm leading-relaxed text-muted-foreground">
                            {item.description}
                        </p>
                    </>
                )}
            </div>
        </article>
    );
}

function ActivityDetailsDialog({ item }: { item: TimelineActivityItemType }) {
    const [detailsOpen, setDetailsOpen] = useState(false);

    return (
        <Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="group/details -mx-2 -my-1 h-auto min-h-8 justify-start rounded-md px-2 py-1 text-left hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring/40"
                >
                    <span className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        {item.label && (
                            <span className="text-xs font-medium text-foreground">
                                {item.label}
                            </span>
                        )}
                        <time className="text-xs font-normal text-muted-foreground">
                            {formatDate(item.created_at)}
                        </time>
                        <span className="text-xs font-medium text-primary opacity-0 transition-opacity group-hover/details:opacity-100 group-focus-visible/details:opacity-100">
                            Ver
                        </span>
                    </span>
                </Button>
            </DialogTrigger>
            {detailsOpen && (
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base">
                            {item.label ?? 'Detalhes da atividade'}
                        </DialogTitle>
                        <DialogDescription>
                            {item.causer?.name ?? 'Sistema'} em{' '}
                            {formatDate(item.created_at)}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid gap-2 rounded-lg border border-border/70 bg-muted/20 p-3 text-sm sm:grid-cols-3">
                            <ActivityMeta label="Evento" value={item.event} />
                            <ActivityMeta
                                label="Origem"
                                value={item.causer?.name ?? 'Sistema'}
                            />
                            <ActivityMeta
                                label="Data"
                                value={formatDate(item.created_at)}
                            />
                        </div>

                        <div className="space-y-2">
                            <h3 className="text-sm font-medium text-foreground">
                                Campos alterados
                            </h3>
                            <div className="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                                {item.changes.map((change) => (
                                    <div
                                        key={`${change.field}-${change.label}`}
                                        className="rounded-lg border border-border/70 bg-background p-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-medium text-foreground">
                                                    {change.label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {change.field}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            <ChangeValue
                                                label="Antes"
                                                value={change.old}
                                            />
                                            <ChangeValue
                                                label="Depois"
                                                value={change.new}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </DialogContent>
            )}
        </Dialog>
    );
}

function ActivityMeta({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="truncate text-sm text-foreground">{value}</p>
        </div>
    );
}

function ChangeValue({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div className="min-w-0 rounded-md bg-muted/40 px-3 py-2">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm break-words whitespace-pre-wrap text-foreground">
                {value ?? 'vazio'}
            </p>
        </div>
    );
}

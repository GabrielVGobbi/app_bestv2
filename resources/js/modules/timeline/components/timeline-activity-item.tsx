import { memo, useEffect, useMemo, useState } from 'react';

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

import type {
    TimelineActivityItem as TimelineActivityItemType,
    TimelineChange,
} from '../api/types';
import { timelineIconFor } from '../lib/timeline-icons';

const severityClass: Record<string, string> = {
    muted: 'border-border/80 bg-muted text-muted-foreground',
    info: 'border-info-soft-foreground/20 bg-info-soft text-info-soft-foreground',
    success:
        'border-success-soft-foreground/20 bg-success-soft text-success-soft-foreground',
    warning:
        'border-warning-soft-foreground/20 bg-warning-soft text-warning-soft-foreground',
    danger: 'border-destructive-soft-foreground/20 bg-destructive-soft text-destructive-soft-foreground',
};

interface TimelineActivityItemProps {
    item: TimelineActivityItemType;
}

export function TimelineActivityItem(props: TimelineActivityItemProps) {
    return <TimelineActivityItemContent {...props} />;
}

const TimelineActivityItemContent = memo(function TimelineActivityItemContent({
    item,
}: TimelineActivityItemProps) {
    const hasChanges = item.changes.length > 0;
    const createdAtLabel = useMemo(
        () => formatDate(item.created_at),
        [item.created_at],
    );

    return (
        <article className="relative flex gap-3">
            <div className="relative flex w-7 shrink-0 justify-center">
                <div className="absolute top-6 bottom-0 w-px bg-border/70" />
                <div
                    className={cn(
                        'relative z-10 mt-1 flex size-6 shrink-0 items-center justify-center rounded-full border',
                        severityClass[item.severity],
                    )}
                >
                    {timelineIconFor(item.event, 'size-3.5')}
                </div>
            </div>

            <div className="min-w-0 flex-1 py-1.5">
                {hasChanges ? (
                    <ActivityDetailsDialog
                        item={item}
                        createdAtLabel={createdAtLabel}
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            {item.label && (
                                <span className="text-xs font-medium text-foreground">
                                    {item.label}
                                </span>
                            )}
                            <time className="text-xs text-muted-foreground">
                                {createdAtLabel}
                            </time>
                        </div>
                        <p className="mt-1 max-w-[72ch] text-sm leading-relaxed text-muted-foreground">
                            {item.description}
                        </p>
                    </>
                )}
            </div>
        </article>
    );
});

function ActivityDetailsDialog({
    item,
    createdAtLabel,
}: {
    item: TimelineActivityItemType;
    createdAtLabel: string;
}) {
    const [open, setOpen] = useState(false);
    const [renderChanges, setRenderChanges] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            setRenderChanges(true);
        });

        return () => {
            window.cancelAnimationFrame(frame);
        };
    }, [open]);

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (!nextOpen) {
            setRenderChanges(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
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
                            {createdAtLabel}
                        </time>
                        <span className="text-xs font-medium text-primary opacity-0 transition-opacity group-hover/details:opacity-100 group-focus-visible/details:opacity-100">
                            Ver
                        </span>
                    </span>
                </Button>
            </DialogTrigger>
            {open && (
                <DialogContent className="duration-100 motion-reduce:duration-0 sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base">
                            {item.label ?? 'Detalhes da atividade'}
                        </DialogTitle>
                        <DialogDescription>
                            {item.causer?.name ?? 'Sistema'} em {createdAtLabel}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid gap-2 rounded-lg border border-border/70 bg-muted/20 p-3 text-sm sm:grid-cols-3">
                            <ActivityMeta label="Evento" value={item.event} />
                            <ActivityMeta
                                label="Origem"
                                value={item.causer?.name ?? 'Sistema'}
                            />
                            <ActivityMeta label="Data" value={createdAtLabel} />
                        </div>

                        <div className="space-y-2">
                            <h3 className="text-sm font-medium text-foreground">
                                Campos alterados
                            </h3>
                            {renderChanges ? (
                                <ActivityChangesList changes={item.changes} />
                            ) : (
                                <div
                                    className="h-24 rounded-lg border border-border/70 bg-muted/20"
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                    </div>
                </DialogContent>
            )}
        </Dialog>
    );
}

const ActivityChangesList = memo(function ActivityChangesList({
    changes,
}: {
    changes: TimelineChange[];
}) {
    return (
        <div className="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
            {changes.map((change, index) => (
                <div
                    key={`${change.field}-${index}`}
                    className="rounded-lg border border-border/70 bg-background p-3 [contain-intrinsic-size:auto_120px] [content-visibility:auto]"
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
                        <ChangeValue label="Antes" value={change.old} />
                        <ChangeValue label="Depois" value={change.new} />
                    </div>
                </div>
            ))}
        </div>
    );
});

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

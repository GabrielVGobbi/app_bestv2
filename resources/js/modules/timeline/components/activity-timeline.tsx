import { AlertCircle, RefreshCw } from 'lucide-react';
import { useMemo } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import type { ModelId } from '../api/types';
import { useCommentMutations } from '../hooks/use-comment-mutations';
import { useTimeline } from '../hooks/use-timeline';
import { groupTimelineItems } from '../lib/group-timeline-items';
import { CommentComposer } from './comment-composer';
import { TimelineDayGroup } from './timeline-day-group';
import { TimelineEmptyState } from './timeline-empty-state';
import { TimelineSkeleton } from './timeline-skeleton';

export interface ActivityTimelineProps {
    modelType: string;
    modelId: ModelId;
    title?: string | null;
    showHeader?: boolean;
    readOnly?: boolean;
    allowAttachments?: boolean;
    className?: string;
    pageSize?: number;
}

export function ActivityTimeline({
    modelType,
    modelId,
    title = 'Timeline',
    showHeader = true,
    readOnly = false,
    allowAttachments = true,
    className,
    pageSize = 50,
}: ActivityTimelineProps) {
    const timeline = useTimeline(modelType, modelId, pageSize);
    const mutations = useCommentMutations(modelType, modelId);

    const groups = useMemo(
        () => groupTimelineItems(timeline.data?.data ?? []),
        [timeline.data?.data],
    );

    const shouldShowHeader = showHeader && title !== null;

    const content = (() => {
        if (timeline.isLoading) {
            return <TimelineSkeleton />;
        }

        if (timeline.isError) {
            return (
                <Alert variant="destructive">
                    <AlertCircle className="size-4" />
                    <AlertTitle>Erro ao carregar timeline</AlertTitle>
                    <AlertDescription>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-2"
                            onClick={() => void timeline.refetch()}
                        >
                            <RefreshCw className="size-3.5" />
                            Tentar novamente
                        </Button>
                    </AlertDescription>
                </Alert>
            );
        }

        if (groups.length === 0) {
            return <TimelineEmptyState />;
        }

        return (
            <div className="space-y-6">
                {groups.map((group) => (
                    <TimelineDayGroup
                        key={group.key}
                        group={group}
                        readOnly={readOnly}
                        allowAttachments={allowAttachments}
                        onReply={async (commentId, payload) => {
                            await mutations.reply.mutateAsync({
                                commentId,
                                payload,
                            });
                        }}
                        onUpdate={async (commentId, payload) => {
                            await mutations.update.mutateAsync({
                                commentId,
                                payload,
                            });
                        }}
                        onDelete={async (commentId) => {
                            await mutations.remove.mutateAsync(commentId);
                        }}
                    />
                ))}
            </div>
        );
    })();

    return (
        <section
            className={cn(
                'rounded-lg border border-border/70 bg-background/70 shadow-xs',
                className,
            )}
        >
            {shouldShowHeader && (
                <div className="border-b border-border/60 px-4 py-3">
                    <h2 className="text-sm font-semibold tracking-normal text-foreground">
                        {title}
                    </h2>
                    {timeline.data?.subject && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {timeline.data.subject.label}
                        </p>
                    )}
                </div>
            )}
            <div className="space-y-6">
                {!readOnly && (
                    <CommentComposer
                        label="Novo comentario"
                        onSubmit={async (payload) => {
                            await mutations.create.mutateAsync(payload);
                        }}
                    />
                )}

                {content}
            </div>
        </section>
    );
}

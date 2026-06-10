import { Paperclip } from 'lucide-react';
import { useState } from 'react';

import { DeleteConfirmModal } from '@/components/delete-confirm-modal';
import { Button } from '@/components/ui/button';
import { cn, formatDate } from '@/lib/utils';

import type {
    CommentPayload,
    TimelineCommentItem as TimelineCommentItemType,
} from '../api/types';
import { CommentActionsMenu } from './comment-actions-menu';
import { CommentComposer } from './comment-composer';
import { TimelineCommentAttachments } from './timeline-comment-attachments';

interface TimelineCommentItemProps {
    item: TimelineCommentItemType;
    readOnly?: boolean;
    allowAttachments?: boolean;
    isReply?: boolean;
    onReply?: (
        commentId: number,
        payload: CommentPayload,
    ) => Promise<void> | void;
    onUpdate: (
        commentId: number,
        payload: CommentPayload,
    ) => Promise<void> | void;
    onDelete: (commentId: number) => Promise<void> | void;
}

export function TimelineCommentItem({
    item,
    readOnly = false,
    allowAttachments = true,
    isReply = false,
    onReply,
    onUpdate,
    onDelete,
}: TimelineCommentItemProps) {
    const [editing, setEditing] = useState(false);
    const [replying, setReplying] = useState(false);
    const [attachmentsOpen, setAttachmentsOpen] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const attachments = item.attachments ?? [];
    const replies = item.replies ?? [];
    const authorName = item.author?.name ?? 'Usuario';

    return (
        <article
            className={cn(
                'group/comment relative flex min-w-0 items-start gap-2.5',
                isReply && 'gap-2',
            )}
        >
            <div
                className={cn(
                    'flex shrink-0 justify-center',
                    isReply ? 'w-6' : 'w-8',
                )}
            >
                <div
                    className={cn(
                        'mt-1 flex shrink-0 items-center justify-center rounded-full border border-border/70 bg-muted text-[0.65rem] font-semibold text-muted-foreground shadow-sm',
                        isReply ? 'size-5' : 'size-7',
                    )}
                    aria-hidden="true"
                >
                    {getInitials(authorName)}
                </div>
            </div>

            <div className="min-w-0 flex-1">
                <div className="max-w-[44rem] space-y-1.5">
                    <div className="flex min-w-0 items-center gap-2">
                        <p className="truncate text-sm font-semibold text-foreground">
                            {authorName}
                        </p>
                        <time className="shrink-0 text-xs text-muted-foreground">
                            {formatDate(item.created_at)}
                        </time>
                        {item.edited_at && (
                            <span className="text-xs text-muted-foreground">
                                editado
                            </span>
                        )}
                    </div>

                    <div
                        className={cn(
                            'relative rounded-2xl rounded-tl-md border border-border/70 bg-background px-3.5 py-3 shadow-xs transition-colors group-hover/comment:border-border',
                            isReply && 'rounded-xl rounded-tl-sm px-3 py-2.5',
                        )}
                    >
                        <div className="absolute top-1.5 right-1.5 flex shrink-0 items-center gap-0.5 rounded-full bg-background/95 opacity-100 transition-opacity md:opacity-0 md:group-focus-within/comment:opacity-100 md:group-hover/comment:opacity-100">
                            {!readOnly && allowAttachments && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground hover:text-foreground"
                                    onClick={() => setAttachmentsOpen(true)}
                                    title="Anexar arquivo"
                                    aria-label="Anexar arquivo"
                                >
                                    <Paperclip className="size-3.5" />
                                </Button>
                            )}

                            {!readOnly && (
                                <CommentActionsMenu
                                    canUpdate={item.permissions.can_update}
                                    canDelete={item.permissions.can_delete}
                                    onEdit={() => setEditing(true)}
                                    onDelete={() => setConfirmingDelete(true)}
                                />
                            )}
                        </div>

                        <div className="min-w-0 pr-16">
                            {editing ? (
                                <CommentComposer
                                    compact
                                    label="Editar comentario"
                                    submitLabel="Salvar"
                                    initialValue={item.body}
                                    initialFormat={item.body_format}
                                    onCancel={() => setEditing(false)}
                                    onSubmit={async (payload) => {
                                        await onUpdate(item.id, payload);
                                        setEditing(false);
                                    }}
                                />
                            ) : (
                                <TimelineCommentBody item={item} />
                            )}
                        </div>

                        {allowAttachments && (
                            <div className="mt-2.5">
                                <TimelineCommentAttachments
                                    commentUuid={item.uuid}
                                    initialAttachments={attachments}
                                    readOnly={readOnly}
                                    open={attachmentsOpen}
                                    onOpenChange={setAttachmentsOpen}
                                    showTrigger={false}
                                />
                            </div>
                        )}
                    </div>

                    {!readOnly && !isReply && item.permissions.can_reply && (
                        <div className="flex pl-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 rounded-full px-2.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                                onClick={() =>
                                    setReplying((current) => !current)
                                }
                            >
                                Responder
                            </Button>
                        </div>
                    )}

                    {replying && onReply && (
                        <div className="pl-1">
                            <CommentComposer
                                compact
                                autoFocus
                                label="Resposta"
                                placeholder="Responder comentario..."
                                submitLabel="Responder"
                                onCancel={() => setReplying(false)}
                                onSubmit={async (payload) => {
                                    await onReply(item.id, payload);
                                    setReplying(false);
                                }}
                            />
                        </div>
                    )}

                    {replies.length > 0 && (
                        <div className="space-y-2 border-l border-border/70 pl-3">
                            {replies.map((reply) => (
                                <TimelineCommentItem
                                    key={reply.id}
                                    item={reply}
                                    readOnly={readOnly}
                                    allowAttachments={allowAttachments}
                                    isReply
                                    onUpdate={onUpdate}
                                    onDelete={onDelete}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <DeleteConfirmModal
                isOpen={confirmingDelete}
                onClose={() => setConfirmingDelete(false)}
                title="Excluir comentario"
                description="Esta acao remove o comentario da timeline."
                onConfirm={async () => {
                    await onDelete(item.id);
                    setConfirmingDelete(false);
                }}
            />
        </article>
    );
}

function getInitials(name: string) {
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    return initials || 'U';
}

function TimelineCommentBody({ item }: { item: TimelineCommentItemType }) {
    if (item.body_format === 'html') {
        return (
            <div
                className="timeline-comment-html max-w-[72ch] text-sm leading-relaxed break-words text-foreground [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_h1]:text-base [&_h1]:font-semibold [&_h2]:text-[0.95rem] [&_h2]:font-semibold [&_h3]:text-sm [&_h3]:font-semibold [&_li]:ml-4 [&_ol]:list-decimal [&_p]:my-1 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:bg-muted [&_pre]:p-2 [&_ul]:list-disc"
                dangerouslySetInnerHTML={{ __html: item.body }}
            />
        );
    }

    return (
        <p className="max-w-[72ch] text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground">
            {item.body}
        </p>
    );
}

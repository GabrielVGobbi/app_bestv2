import {
    Activity,
    MessageSquare,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';

export function timelineIconFor(event?: string | null, className?: string): ReactNode {
    if (event === 'created') {
return <Plus className={className} />;
}

    if (event === 'updated') {
return <Pencil className={className} />;
}

    if (event === 'deleted') {
return <Trash2 className={className} />;
}

    return <Activity className={className} />;
}

export const CommentIcon = MessageSquare;

import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface CommentActionsMenuProps {
    canUpdate: boolean;
    canDelete: boolean;
    disabled?: boolean;
    onEdit: () => void;
    onDelete: () => void;
}

export function CommentActionsMenu({
    canUpdate,
    canDelete,
    disabled = false,
    onEdit,
    onDelete,
}: CommentActionsMenuProps) {
    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={disabled}
                    aria-label="Açõesdo comentario"
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {canUpdate && (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil className="size-4" />
                        Editar
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                        <Trash2 className="size-4" />
                        Excluir
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

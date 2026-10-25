import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';

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
            <DropdownTrigger
                render={
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={disabled}
                        aria-label="Ações do comentário"
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                }
            />
            <DropdownContent align="end" className="w-44">
                {canUpdate && (
                    <MenuItem
                        index={0}
                        icon={Pencil}
                        label="Editar"
                        onSelect={onEdit}
                    />
                )}
                {canDelete && (
                    <MenuItem
                        index={canUpdate ? 1 : 0}
                        icon={Trash2}
                        label="Excluir"
                        destructive
                        onSelect={onDelete}
                    />
                )}
            </DropdownContent>
        </DropdownMenu>
    );
}

import type { Table } from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    Columns3,
    GripVertical,
    RotateCcw,
    Settings2,
    Rows3,
    MoveHorizontal,
} from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownContent,
    DropdownMenu,
    DropdownSeparator,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { SurfaceProvider } from '@/components/ui/fluid/lib/surface-context';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';

import { moveColumn } from '../core/table-layout';
import type { DataTableSize, DataTableState } from '../core/types';

export interface TableLayoutControls {
    state: DataTableState;
    density: DataTableSize;
    storageStatus: string;
    onDensityChange: (size: DataTableSize) => void;
    onResetLayout: () => void;
    onResetAll: () => void;
    onAutosize: (columnId?: string) => void;
}

export function TableLayoutSettings<TData>({
    table,
    state,
    density,
    storageStatus,
    onDensityChange,
    onResetLayout,
    onResetAll,
    onAutosize,
}: TableLayoutControls & { table: Table<TData> }) {
    const [dragged, setDragged] = useState<string>();
    const [dialogOpen, setDialogOpen] = useState(false);
    const columns = table
        .getAllLeafColumns()
        .filter((column) => !['select', 'actions'].includes(column.id));
    const order = state.columnOrder.length
        ? state.columnOrder
        : table.getAllLeafColumns().map((column) => column.id);
    columns.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
    const visibleCount = columns.filter(
        (column) => state.columnVisibility[column.id] !== false,
    ).length;
    function applyOrder(next: string[]) {
        table.setColumnOrder(next);
        table.setColumnPinning((pinning) => ({
            left: [...(pinning.left ?? [])].sort(
                (a, b) => next.indexOf(a) - next.indexOf(b),
            ),
            right: [...(pinning.right ?? [])].sort(
                (a, b) => next.indexOf(a) - next.indexOf(b),
            ),
        }));
    }

    return (
        <>
            <DropdownMenu>
                <DropdownTrigger
                    render={
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Configurações da tabela"
                            title="Configurações da tabela"
                        >
                            <Settings2 />
                        </Button>
                    }
                />
                <DropdownContent align="end" className="w-56">
                    <MenuItem
                        index={0}
                        icon={Columns3}
                        label="Configurar colunas"
                        onSelect={() => setDialogOpen(true)}
                    />
                    <DropdownSeparator />
                    <MenuItem
                        index={1}
                        icon={RotateCcw}
                        label="Restaurar layout"
                        onSelect={onResetLayout}
                    />
                    <MenuItem
                        index={2}
                        icon={RotateCcw}
                        label="Restaurar tudo"
                        onSelect={onResetAll}
                    />
                </DropdownContent>
            </DropdownMenu>
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto bg-popover text-popover-foreground sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Configurar colunas</DialogTitle>
                        <DialogDescription>
                            Arraste ou use as setas para ordenar. Ajustes são
                            exclusivos desta tabela e conta neste navegador.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm">Densidade</span>
                        <Button
                            type="button"
                            size="sm"
                            variant={density === 'sm' ? 'secondary' : 'outline'}
                            aria-pressed={density === 'sm'}
                            onClick={() => onDensityChange('sm')}
                        >
                            <Rows3 className="size-4" aria-hidden="true" />
                            Compacta
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={density === 'md' ? 'secondary' : 'outline'}
                            aria-pressed={density === 'md'}
                            onClick={() => onDensityChange('md')}
                        >
                            <MoveHorizontal
                                className="size-4"
                                aria-hidden="true"
                            />
                            Confortável
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onAutosize()}
                        >
                            <MoveHorizontal
                                className="size-4"
                                aria-hidden="true"
                            />
                            Ajustar todas ao conteúdo
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={onResetLayout}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Restaurar layout
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        O ajuste mede apenas cabeçalhos e linhas carregadas. No
                        celular, a fixação pode ser relaxada para manter espaço
                        de leitura.
                    </p>
                    <ul className="divide-y">
                        {columns.map((column, index) => {
                            const label =
                                column.columnDef.meta?.label ??
                                (typeof column.columnDef.header === 'string'
                                    ? column.columnDef.header
                                    : column.id);

                            return (
                                <li
                                    key={column.id}
                                    draggable
                                    onDragStart={(event) => {
                                        setDragged(column.id);
                                        event.dataTransfer.effectAllowed =
                                            'move';
                                    }}
                                    onDragOver={(event) =>
                                        event.preventDefault()
                                    }
                                    onDrop={(event) => {
                                        event.preventDefault();

                                        if (dragged) {
                                            applyOrder(
                                                moveColumn(
                                                    order,
                                                    dragged,
                                                    column.id,
                                                ),
                                            );
                                        }

                                        setDragged(undefined);
                                    }}
                                    onDragEnd={() => setDragged(undefined)}
                                    className="flex flex-wrap items-center gap-2 py-3"
                                >
                                    <GripVertical
                                        aria-hidden
                                        className="size-4 text-muted-foreground"
                                    />
                                    <label className="flex min-w-28 flex-1 items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={
                                                state.columnVisibility[
                                                    column.id
                                                ] !== false
                                            }
                                            disabled={
                                                !column.getCanHide() ||
                                                (state.columnVisibility[
                                                    column.id
                                                ] !== false &&
                                                    visibleCount <= 1)
                                            }
                                            onCheckedChange={(checked) =>
                                                column.toggleVisibility(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {label}
                                    </label>
                                    <div className="flex gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Mover ${label} para cima`}
                                            disabled={index === 0}
                                            onClick={() =>
                                                applyOrder(
                                                    moveColumn(
                                                        order,
                                                        column.id,
                                                        columns[index - 1].id,
                                                    ),
                                                )
                                            }
                                        >
                                            <ArrowUp className="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Mover ${label} para baixo`}
                                            disabled={
                                                index === columns.length - 1
                                            }
                                            onClick={() =>
                                                applyOrder(
                                                    moveColumn(
                                                        order,
                                                        columns[index + 1].id,
                                                        column.id,
                                                    ),
                                                )
                                            }
                                        >
                                            <ArrowDown className="size-4" />
                                        </Button>
                                    </div>
                                    {/* Diálogo bg-popover (surface-3): o select abre um nível acima. */}
                                    <SurfaceProvider value={3}>
                                        <Select
                                            value={
                                                state.columnPinning.left?.includes(
                                                    column.id,
                                                )
                                                    ? 'left'
                                                    : state.columnPinning.right?.includes(
                                                            column.id,
                                                        )
                                                      ? 'right'
                                                      : 'none'
                                            }
                                            onValueChange={(value) =>
                                                column.pin(
                                                    value === 'none'
                                                        ? false
                                                        : (value as
                                                              | 'left'
                                                              | 'right'),
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                aria-label={`Fixação de ${label}`}
                                                className="w-40"
                                            />
                                            <SelectContent>
                                                <SelectItem
                                                    index={0}
                                                    value="none"
                                                >
                                                    Sem fixação
                                                </SelectItem>
                                                <SelectItem
                                                    index={1}
                                                    value="left"
                                                >
                                                    Fixar à esquerda
                                                </SelectItem>
                                                <SelectItem
                                                    index={2}
                                                    value="right"
                                                >
                                                    Fixar à direita
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </SurfaceProvider>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={
                                            !column.getCanResize() ||
                                            state.columnVisibility[
                                                column.id
                                            ] === false
                                        }
                                        onClick={() => onAutosize(column.id)}
                                    >
                                        <MoveHorizontal
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Ajustar largura
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                    <p role="status" className="text-xs text-muted-foreground">
                        {storageStatus === 'unavailable'
                            ? 'O navegador não permitiu salvar. Ajustes disponíveis somente nesta sessão.'
                            : storageStatus === 'invalid'
                              ? 'Preferências inválidas foram ignoradas. Os padrões estão em uso.'
                              : storageStatus === 'disabled'
                                ? 'Persistência desativada. Ajustes disponíveis somente nesta sessão.'
                                : storageStatus === 'saved'
                                  ? 'Preferências salvas neste navegador.'
                                  : 'Preferências disponíveis neste navegador.'}
                    </p>
                </DialogContent>
            </Dialog>
        </>
    );
}

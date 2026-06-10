import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/button'
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog'

interface DeleteConfirmModalProps {
	isOpen: boolean
	onClose: () => void
	onConfirm: () => void
	isLoading?: boolean
	title?: string
	description?: string
	confirmLabel?: string
	confirmVariant?: 'destructive' | 'default' | 'outline' | 'secondary' | 'ghost' | 'link'
}

export function DeleteConfirmModal({
	isOpen,
	onClose,
	onConfirm,
	isLoading = false,
	title = 'Confirmar exclusão',
	description = 'Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.',
	confirmLabel,
	confirmVariant = 'destructive',
}: DeleteConfirmModalProps) {
	return (
		<Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
			<DialogContent className="sm:max-w-[400px]">
				<DialogHeader>
					<div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-destructive/10">
						<AlertTriangle className="size-6 text-destructive" />
					</div>
					<DialogTitle className="text-center">{title}</DialogTitle>
					<DialogDescription className="text-center">
						{description}
					</DialogDescription>
				</DialogHeader>

				<DialogFooter className="mt-4 sm:justify-center">
					<Button
						type="button"
						variant="outline"
						onClick={onClose}
						disabled={isLoading}
					>
						Cancelar
					</Button>
					<Button
						type="button"
						variant={confirmVariant}
						onClick={onConfirm}
						disabled={isLoading}
					>
						{isLoading ? 'Aguarde...' : (confirmLabel ?? 'Remover')}
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}

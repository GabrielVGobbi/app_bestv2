import {
	Check,
	Download,
	Eye,
	File,
	FileSpreadsheet,
	FileText,
	Image as ImageIcon,
	Loader2,
	MoreHorizontal,
	Music,
	Pencil,
	Star,
	Trash2,
	Video,
	X,
	type LucideIcon,
} from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

import type { TagSuggestion, Upload } from '../api/types'
import { TagCombobox } from './tag-combobox'

const ICON_MAP: Record<string, LucideIcon> = {
	image: ImageIcon,
	video: Video,
	music: Music,
	'file-text': FileText,
	'file-spreadsheet': FileSpreadsheet,
	file: File,
}

interface UploadItemProps {
	upload: Upload
	suggestions: TagSuggestion[]
	readOnly?: boolean
	showCoverOption?: boolean
	onRename: (id: number, name: string) => void
	onChangeTag: (id: number, tag: string | null) => void
	onDelete: (id: number) => void
	onSetCover?: (id: number) => void
	onToggleFavorite?: (id: number) => void
	isUpdating?: boolean
	isDeleting?: boolean
	isTogglingFavorite?: boolean
	onRequestDelete?: (id: number) => void
	selectable?: boolean
	selected?: boolean
	onSelect?: (id: number, checked: boolean) => void
	onPreview?: (id: number) => void
}

export function UploadItem({
	upload,
	suggestions,
	readOnly = false,
	showCoverOption = false,
	onRename,
	onChangeTag,
	onDelete: _onDelete,
	onSetCover,
	onToggleFavorite,
	isUpdating = false,
	isDeleting = false,
	isTogglingFavorite = false,
	onRequestDelete,
	selectable = false,
	selected = false,
	onSelect,
	onPreview,
}: UploadItemProps) {
	const [editing, setEditing] = useState(false)
	const [nameDraft, setNameDraft] = useState(upload.name)

	const Icon = ICON_MAP[upload.icon] ?? File
	const canEdit = !readOnly && !selectable && upload.permissions.can_update
	const canDelete = !readOnly && upload.permissions.can_delete
	const previewUrl = upload.preview_url || upload.url
	const downloadUrl = upload.download_url || upload.url

	function commitRename() {
		const trimmed = nameDraft.trim()
		if (!trimmed) {
			setNameDraft(upload.name)
			setEditing(false)
			return
		}
		if (trimmed !== upload.name) {
			onRename(upload.id, trimmed)
		}
		setEditing(false)
	}

	function cancelRename() {
		setNameDraft(upload.name)
		setEditing(false)
	}

	return (
		<div
			className={cn(
				'group flex items-start gap-3 rounded-lg border bg-card p-3 transition-colors hover:bg-muted/40',
				selectable && 'cursor-pointer',
				upload.is_cover && 'border-primary/50 bg-primary/5',
				upload.is_favorite && 'border-amber-400/50',
				selected && 'border-primary/60 bg-primary/5 ring-1 ring-primary/40',
			)}
			onClick={() => {
				if (selectable) onSelect?.(upload.id, !selected)
			}}
		>
			{selectable && (
				<div className="mt-1 shrink-0">
					<Checkbox
						checked={selected}
						onCheckedChange={(checked) =>
							onSelect?.(upload.id, Boolean(checked))
						}
						onClick={(event) => event.stopPropagation()}
						aria-label={`Selecionar ${upload.name}`}
					/>
				</div>
			)}

			<div className="relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
				{upload.is_image ? (
					<img
						src={previewUrl}
						alt={upload.name}
						className={cn(
							'size-full object-cover',
							!selectable && 'cursor-pointer',
						)}
						loading="lazy"
						onClick={(event) => {
							event.stopPropagation()
							if (!selectable) onPreview?.(upload.id)
						}}
					/>
				) : (
					<button
						type="button"
						className={cn(
							'inline-flex size-full items-center justify-center',
							!selectable && 'cursor-pointer',
						)}
						onClick={(event) => {
							event.stopPropagation()
							if (!selectable) onPreview?.(upload.id)
						}}
						disabled={selectable}
						aria-label={`Visualizar ${upload.name}`}
					>
						<Icon className="size-6 text-muted-foreground" />
					</button>
				)}
				{upload.is_cover && (
					<span className="absolute right-0 top-0 rounded-bl bg-primary p-0.5 text-primary-foreground">
						<Star className="size-2.5 fill-current" />
					</span>
				)}
			</div>

			<div className="flex min-w-0 flex-1 flex-col gap-1.5">
				<div className="flex min-w-0 items-center gap-2">
					{editing ? (
						<div className="flex min-w-0 flex-1 items-center gap-1">
							<Input
								autoFocus
								value={nameDraft}
								onChange={(e) => setNameDraft(e.target.value)}
								onBlur={commitRename}
								onKeyDown={(e) => {
									if (e.key === 'Enter') commitRename()
									if (e.key === 'Escape') cancelRename()
								}}
								onClick={(event) => event.stopPropagation()}
								className="h-7 text-sm"
							/>
							<Button
								type="button"
								size="icon"
								variant="ghost"
								className="size-7 shrink-0"
								onMouseDown={(e) => e.preventDefault()}
								onClick={commitRename}
							>
								<Check className="size-3.5" />
							</Button>
							<Button
								type="button"
								size="icon"
								variant="ghost"
								className="size-7 shrink-0"
								onMouseDown={(e) => e.preventDefault()}
								onClick={cancelRename}
							>
								<X className="size-3.5" />
							</Button>
						</div>
					) : (
						<>
							<p
								className={cn(
									'truncate text-sm font-medium',
									canEdit && 'cursor-text hover:underline',
								)}
								title={upload.name}
								onClick={(event) => {
									if (!canEdit) return
									event.stopPropagation()
									setEditing(true)
								}}
							>
								{upload.name}
							</p>
							{canEdit && (
								<Button
									type="button"
									size="icon"
									variant="ghost"
									className="size-6 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
									onClick={(event) => {
										event.stopPropagation()
										setEditing(true)
									}}
									aria-label="Renomear"
								>
									<Pencil className="size-3" />
								</Button>
							)}
							{isUpdating && (
								<Loader2 className="size-3 shrink-0 animate-spin text-muted-foreground" />
							)}
						</>
					)}
				</div>

				<div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
					<span>{upload.size_human}</span>
					{upload.extension && (
						<>
							<span aria-hidden>·</span>
							<span className="uppercase">{upload.extension}</span>
						</>
					)}
					{upload.uploader?.name && (
						<>
							<span aria-hidden>·</span>
							<span>por {upload.uploader.name}</span>
						</>
					)}
					<span aria-hidden>·</span>
					<TagCombobox
						value={upload.tag}
						suggestions={suggestions}
						onChange={(next) => onChangeTag(upload.id, next)}
						disabled={!canEdit || isUpdating}
						size="sm"
						placeholder="Sem tag"
						appearance="minimal"
					/>
				</div>
			</div>

			<div className={cn(
					'flex shrink-0 items-center transition-opacity duration-150',
					'opacity-0 group-hover:opacity-100 focus-within:opacity-100',
					(isDeleting || isUpdating || isTogglingFavorite) && 'opacity-100',
				)}>
				{(isDeleting || isTogglingFavorite) && (
					<Loader2 className="mr-1 size-3.5 animate-spin text-muted-foreground" />
				)}
				<DropdownMenu>
					<DropdownMenuTrigger asChild>
						<Button
							type="button"
							size="icon"
							variant="ghost"
							className="size-8"
							onClick={(event) => event.stopPropagation()}
							aria-label="Ações"
						>
							<MoreHorizontal className="size-4" />
						</Button>
					</DropdownMenuTrigger>
					<DropdownMenuContent align="end" onClick={(event) => event.stopPropagation()}>
						{!selectable && (
							<DropdownMenuItem onSelect={() => onPreview?.(upload.id)}>
								<Eye className="size-4" />
								Visualizar
							</DropdownMenuItem>
						)}
						<DropdownMenuItem asChild>
							<a href={downloadUrl} target="_blank" rel="noopener noreferrer">
								<Download className="size-4" />
								Baixar
							</a>
						</DropdownMenuItem>
						{canEdit && onToggleFavorite && (
							<DropdownMenuItem
								onSelect={() => onToggleFavorite(upload.id)}
								disabled={isTogglingFavorite}
								className={cn(upload.is_favorite && 'text-amber-500 focus:text-amber-600')}
							>
								<Star className={cn('size-4', upload.is_favorite && 'fill-current')} />
								{upload.is_favorite ? 'Remover dos favoritos' : 'Favoritar'}
							</DropdownMenuItem>
						)}
						{showCoverOption && upload.is_image && !upload.is_cover && canEdit && onSetCover && (
							<DropdownMenuItem onSelect={() => onSetCover(upload.id)}>
								<Star className="size-4" />
								Definir como capa
							</DropdownMenuItem>
						)}
						{canDelete && (
							<>
								<DropdownMenuSeparator />
								<DropdownMenuItem
									onSelect={() => onRequestDelete?.(upload.id)}
									disabled={isDeleting}
									className="text-destructive focus:text-destructive"
								>
									<Trash2 className="size-4" />
									Excluir
								</DropdownMenuItem>
							</>
						)}
					</DropdownMenuContent>
				</DropdownMenu>
			</div>
		</div>
	)
}

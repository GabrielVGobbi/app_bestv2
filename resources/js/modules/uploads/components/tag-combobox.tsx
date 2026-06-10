import { Check, ChevronsUpDown, Tag as TagIcon, X } from 'lucide-react'
import { useMemo, useState } from 'react'

import { Button } from '@/components/ui/button'
import {
	Command,
	CommandEmpty,
	CommandGroup,
	CommandInput,
	CommandItem,
	CommandList,
} from '@/components/ui/command'
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'

import type { TagSuggestion } from '../api/types'

interface TagComboboxProps {
	value: string | null
	suggestions: TagSuggestion[]
	onChange: (value: string | null) => void
	placeholder?: string
	disabled?: boolean
	className?: string
	size?: 'sm' | 'default'
	allowClear?: boolean
	appearance?: 'default' | 'minimal'
}

export function TagCombobox({
	value,
	suggestions,
	onChange,
	placeholder = 'Selecionar tag…',
	disabled = false,
	className,
	size = 'default',
	allowClear = true,
	appearance = 'default',
}: TagComboboxProps) {
	const [open, setOpen] = useState(false)
	const [search, setSearch] = useState('')

	const selectedLabel = useMemo(() => {
		if (!value) return null
		const match = suggestions.find((s) => s.value === value)
		return match?.label ?? value
	}, [value, suggestions])

	const filtered = useMemo(() => {
		const term = search.trim().toLowerCase()
		if (!term) return suggestions
		return suggestions.filter(
			(s) =>
				s.label.toLowerCase().includes(term)
				|| s.value.toLowerCase().includes(term),
		)
	}, [search, suggestions])

	const showCustomOption =
		search.trim().length > 0
		&& !suggestions.some(
			(s) =>
				s.value.toLowerCase() === search.trim().toLowerCase()
				|| s.label.toLowerCase() === search.trim().toLowerCase(),
		)

	function handleSelect(next: string) {
		onChange(next || null)
		setSearch('')
		setOpen(false)
	}

	function handleClear(e: React.MouseEvent) {
		e.stopPropagation()
		onChange(null)
	}

	return (
		<Popover open={open} onOpenChange={setOpen}>
			<PopoverTrigger asChild>
				<Button
					type="button"
					variant={appearance === 'minimal' ? 'ghost' : 'outline'}
					role="combobox"
					aria-expanded={open}
					disabled={disabled}
					className={cn(
						'justify-between gap-2 font-normal',
						size === 'sm' && 'h-7 px-2 text-xs',
						!value && 'text-muted-foreground',
						appearance === 'minimal'
							&& 'h-auto rounded-none px-0 py-0 text-xs text-muted-foreground hover:bg-transparent hover:text-foreground',
						className,
					)}
				>
					<span className="flex min-w-0 items-center gap-1.5 truncate">
						<TagIcon
							className={cn(
								'shrink-0',
								size === 'sm' ? 'size-3' : 'size-3.5',
								appearance === 'minimal' && 'opacity-70',
							)}
						/>
						<span className="truncate">
							{selectedLabel ?? placeholder}
						</span>
					</span>

					<span className="flex shrink-0 items-center gap-1">
						{allowClear && value && !disabled && (
							<span
								role="button"
								tabIndex={-1}
								onClick={handleClear}
								className="rounded p-0.5 hover:bg-muted"
								aria-label="Remover tag"
							>
								<X className="size-3" />
							</span>
						)}
						<ChevronsUpDown
							className={cn(
								'size-3.5 opacity-50',
								appearance === 'minimal' && 'size-3 opacity-40',
							)}
						/>
					</span>
				</Button>
			</PopoverTrigger>

			<PopoverContent
				className="w-[260px] p-0"
				align="start"
				onOpenAutoFocus={(e) => e.preventDefault()}
			>
				<Command shouldFilter={false}>
					<CommandInput
						value={search}
						onValueChange={setSearch}
						placeholder="Buscar ou digitar…"
					/>
					<CommandList>
						{filtered.length === 0 && !showCustomOption && (
							<CommandEmpty>Nenhuma tag encontrada.</CommandEmpty>
						)}

						{filtered.length > 0 && (
							<CommandGroup heading="Sugestões">
								{filtered.map((item) => (
									<CommandItem
										key={item.value}
										value={item.value}
										onSelect={() => handleSelect(item.value)}
									>
										<Check
											className={cn(
												'mr-2 size-4',
												value === item.value
													? 'opacity-100'
													: 'opacity-0',
											)}
										/>
										<span className="flex flex-col">
											<span>{item.label}</span>
											<span className="text-[10px] text-muted-foreground">
												{item.value}
											</span>
										</span>
									</CommandItem>
								))}
							</CommandGroup>
						)}

						{showCustomOption && (
							<CommandGroup heading="Customizada">
								<CommandItem
									value={`__custom__${search}`}
									onSelect={() => handleSelect(search.trim())}
								>
									<Check className="mr-2 size-4 opacity-0" />
									Usar "{search.trim()}"
								</CommandItem>
							</CommandGroup>
						)}
					</CommandList>
				</Command>
			</PopoverContent>
		</Popover>
	)
}

import {
    Check,
    Link2,
    Loader2,
    Music2,
    Plus,
    Search,
    Tags,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import {
    store as storeGenre,
    storeSubgenre,
} from '@/actions/App/Modules/Playlists/Http/Controllers/MusicGenreController';
import { AsyncSelect } from '@/components/async-select';
import type { AsyncSelectOption } from '@/components/async-select';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { getErrorMessage, httpPost, isApiError } from '@/lib/http';
import type { ApiResponse } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { MusicGenre, MusicSubgenre } from '@/types/app/playlist';

interface Props {
    genres: MusicGenre[];
    subgenres: MusicSubgenre[];
    selectedGenreId: string;
    selectedSubgenreIds: number[];
    errors: Record<string, string>;
    canCreate?: boolean;
    onGenresChange: (genres: MusicGenre[]) => void;
    onSubgenresChange: (subgenres: MusicSubgenre[]) => void;
    onGenreChange: (genreId: string) => void;
    onSubgenreIdsChange: (ids: number[]) => void;
}

interface MusicGenrePickerProps {
    genres: MusicGenre[];
    selectedGenreId: string;
    error?: string;
    canCreate?: boolean;
    showSubgenreCount?: boolean;
    onGenresChange: (genres: MusicGenre[]) => void;
    onGenreChange: (genreId: string) => void;
}

interface ModalShellProps {
    title: string;
    description: string;
    children: React.ReactNode;
    onClose: () => void;
}

function sortGenres(genres: MusicGenre[]): MusicGenre[] {
    return [...genres].sort((first, second) =>
        first.name.localeCompare(second.name, 'pt-BR'),
    );
}

function sortSubgenres(subgenres: MusicSubgenre[]): MusicSubgenre[] {
    return [...subgenres].sort((first, second) =>
        first.name.localeCompare(second.name, 'pt-BR'),
    );
}

function normalizeSearch(value: string): string {
    return value.trim().toLowerCase();
}

function catalogErrorMessage(error: unknown): string {
    return isApiError(error)
        ? getErrorMessage(error)
        : 'Erro inesperado. Tente novamente.';
}

/** Sempre montado aberto: quem chama decide a montagem; fechar pelo overlay, Escape ou X avisa onClose. */
function ModalShell({
    title,
    description,
    children,
    onClose,
}: ModalShellProps) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {children}
            </DialogContent>
        </Dialog>
    );
}

function CreateGenreButton({
    onCreated,
}: {
    onCreated: (genre: MusicGenre) => void;
}) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);

    function close(): void {
        setOpen(false);
        setName('');
    }

    async function createGenre(): Promise<void> {
        const nextName = name.trim();

        if (!nextName) {
            return;
        }

        setBusy(true);

        try {
            const response = await httpPost<ApiResponse<MusicGenre>>(
                storeGenre.url(),
                { name: nextName },
            );
            onCreated(response.data);
            close();
            toast.success('Gênero criado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="shrink-0"
                aria-label="Novo gênero"
                title="Novo gênero"
                onClick={() => setOpen(true)}
            >
                <Plus className="size-4" />
            </Button>

            {open ? (
                <ModalShell
                    title="Novo gênero"
                    description="Crie um gênero principal para organizar o catálogo musical."
                    onClose={close}
                >
                    <Input
                        value={name}
                        placeholder="Ex.: MPB"
                        aria-label="Nome do gênero"
                        disabled={busy}
                        onChange={(event) => setName(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                void createGenre();
                            }
                        }}
                    />

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            <X className="size-4" aria-hidden="true" />
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void createGenre()}
                            disabled={busy || !name.trim()}
                        >
                            {busy ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <Plus />
                            )}
                            Criar gênero
                        </Button>
                    </DialogFooter>
                </ModalShell>
            ) : null}
        </>
    );
}

export function MusicGenrePicker({
    genres,
    selectedGenreId,
    error,
    canCreate = false,
    showSubgenreCount = true,
    onGenresChange,
    onGenreChange,
}: MusicGenrePickerProps) {
    const genreOptions = useMemo<AsyncSelectOption[]>(
        () =>
            genres.map((genre) => ({
                value: String(genre.id),
                label: genre.name,
                description: showSubgenreCount
                    ? `${genre.subgenres?.length ?? 0} subgêneros`
                    : undefined,
                disabled: !genre.is_active,
            })),
        [genres, showSubgenreCount],
    );

    return (
        <FormField id="field-music-genre" label="Gênero musical" error={error}>
            <div className="flex gap-2">
                <AsyncSelect
                    className="min-w-0 flex-1"
                    value={selectedGenreId || null}
                    options={genreOptions}
                    placeholder="Selecione ou busque um gênero"
                    searchPlaceholder="Buscar gênero..."
                    emptyMessage="Nenhum gênero encontrado."
                    onChange={(value) => onGenreChange(value ?? '')}
                    clearable
                    renderOption={(option) => (
                        <div className="flex min-w-0 items-center gap-2">
                            <Music2 className="size-4 text-muted-foreground" />
                            <div className="min-w-0">
                                <span className="block truncate">
                                    {option.label}
                                </span>
                                {option.description ? (
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {option.description}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    )}
                />
                {canCreate ? (
                    <CreateGenreButton
                        onCreated={(genre) => {
                            onGenresChange(sortGenres([...genres, genre]));
                            onGenreChange(String(genre.id));
                        }}
                    />
                ) : null}
            </div>
        </FormField>
    );
}

function AttachSubgenreButton({
    selectedGenre,
    subgenres,
    linkedIds,
    canCreate,
    onLinked,
}: {
    selectedGenre: MusicGenre | undefined;
    subgenres: MusicSubgenre[];
    linkedIds: Set<number>;
    canCreate: boolean;
    onLinked: (subgenre: MusicSubgenre) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [visibleLimit, setVisibleLimit] = useState(12);
    const [busyKey, setBusyKey] = useState<string | null>(null);

    function updateSearch(value: string): void {
        setSearch(value);
        setVisibleLimit(12);
    }

    function close(): void {
        setOpen(false);
        updateSearch('');
        setBusyKey(null);
    }

    const availableSubgenres = useMemo(() => {
        const normalizedSearch = normalizeSearch(search);

        return sortSubgenres(
            subgenres.filter((subgenre) => {
                if (linkedIds.has(subgenre.id) || !subgenre.is_active) {
                    return false;
                }

                if (!normalizedSearch) {
                    return true;
                }

                return [subgenre.name, subgenre.slug].some((value) =>
                    value.toLowerCase().includes(normalizedSearch),
                );
            }),
        );
    }, [linkedIds, search, subgenres]);

    const visibleAvailableSubgenres = useMemo(
        () => availableSubgenres.slice(0, visibleLimit),
        [availableSubgenres, visibleLimit],
    );
    const hiddenAvailableCount =
        availableSubgenres.length - visibleAvailableSubgenres.length;
    const hasSearch = search.trim() !== '';

    const exactExistingSubgenre = useMemo(() => {
        const normalizedSearch = normalizeSearch(search);

        if (!normalizedSearch) {
            return null;
        }

        return (
            subgenres.find(
                (subgenre) =>
                    normalizeSearch(subgenre.name) === normalizedSearch ||
                    normalizeSearch(subgenre.slug) === normalizedSearch,
            ) ?? null
        );
    }, [search, subgenres]);

    const canCreateSubgenre =
        canCreate &&
        Boolean(selectedGenre) &&
        search.trim() !== '' &&
        !exactExistingSubgenre;

    async function linkSubgenre(subgenre: MusicSubgenre): Promise<void> {
        if (!selectedGenre || linkedIds.has(subgenre.id)) {
            return;
        }

        setBusyKey(`link:${subgenre.uuid}`);

        try {
            const response = await httpPost<ApiResponse<MusicSubgenre>>(
                storeSubgenre.url(selectedGenre.uuid),
                { name: '', music_subgenre_id: subgenre.id },
            );
            onLinked(response.data);
            updateSearch('');
            toast.success('Subgênero atrelado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    async function createSubgenre(): Promise<void> {
        const name = search.trim();

        if (!name || !selectedGenre) {
            return;
        }

        if (exactExistingSubgenre) {
            await linkSubgenre(exactExistingSubgenre);

            return;
        }

        setBusyKey('create');

        try {
            const response = await httpPost<ApiResponse<MusicSubgenre>>(
                storeSubgenre.url(selectedGenre.uuid),
                { name, music_subgenre_id: null },
            );
            onLinked(response.data);
            updateSearch('');
            toast.success('Subgênero criado e atrelado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={!selectedGenre}
                onClick={() => setOpen(true)}
            >
                <Link2 />
                Atrelar
            </Button>

            {open ? (
                <ModalShell
                    title="Atrelar subgênero"
                    description={
                        selectedGenre
                            ? `Busque no catálogo global ou crie uma nova tag para ${selectedGenre.name}.`
                            : 'Selecione um gênero antes de atrelar subgêneros.'
                    }
                    onClose={close}
                >
                    <div className="space-y-3">
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <div className="relative min-w-0 flex-1">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    value={search}
                                    placeholder="Buscar ou criar subgênero"
                                    aria-label="Buscar ou criar subgênero"
                                    className="pl-9"
                                    disabled={Boolean(busyKey)}
                                    onChange={(event) =>
                                        updateSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            void createSubgenre();
                                        }
                                    }}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                onClick={() => void createSubgenre()}
                                disabled={Boolean(busyKey) || !search.trim()}
                            >
                                {busyKey === 'create' ? (
                                    <Loader2 className="animate-spin" />
                                ) : canCreateSubgenre ? (
                                    <Plus />
                                ) : (
                                    <Link2 />
                                )}
                                {canCreateSubgenre ? 'Criar' : 'Atrelar'}
                            </Button>
                        </div>

                        {exactExistingSubgenre ? (
                            <p className="text-xs text-muted-foreground">
                                “{exactExistingSubgenre.name}” já existe no
                                catálogo. Use atrelar para reaproveitar o mesmo
                                subgênero.
                            </p>
                        ) : null}

                        {availableSubgenres.length > 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Exibindo {visibleAvailableSubgenres.length} de{' '}
                                {availableSubgenres.length} subgênero
                                {availableSubgenres.length === 1 ? '' : 's'}
                                {hasSearch
                                    ? ' encontrado.'
                                    : ' disponível. Use a busca para encontrar outros.'}
                                {hiddenAvailableCount > 0 && hasSearch
                                    ? ' Refine a busca para ver resultados mais específicos.'
                                    : null}
                            </p>
                        ) : null}

                        <div className="max-h-64 space-y-2 overflow-y-auto pr-1">
                            {visibleAvailableSubgenres.length > 0 ? (
                                <>
                                    <div className="flex flex-wrap gap-2">
                                        {visibleAvailableSubgenres.map(
                                            (subgenre) => (
                                                <button
                                                    key={subgenre.id}
                                                    type="button"
                                                    className="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-border bg-card px-3 text-sm transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
                                                    disabled={Boolean(busyKey)}
                                                    onClick={() =>
                                                        void linkSubgenre(
                                                            subgenre,
                                                        )
                                                    }
                                                >
                                                    {busyKey ===
                                                    `link:${subgenre.uuid}` ? (
                                                        <Loader2 className="size-3.5 animate-spin" />
                                                    ) : (
                                                        <Link2 className="size-3.5" />
                                                    )}
                                                    {subgenre.name}
                                                </button>
                                            ),
                                        )}
                                    </div>

                                    {hiddenAvailableCount > 0 ? (
                                        <div className="pt-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 px-2 text-xs"
                                                onClick={() =>
                                                    setVisibleLimit(
                                                        (current) =>
                                                            current + 12,
                                                    )
                                                }
                                            >
                                                Carregar mais{' '}
                                                <span className="text-muted-foreground">
                                                    ({hiddenAvailableCount})
                                                </span>
                                            </Button>
                                        </div>
                                    ) : null}
                                </>
                            ) : (
                                <EmptyState
                                    icon={Tags}
                                    title="Nenhum subgênero disponível para atrelar"
                                    description={
                                        hasSearch
                                            ? 'Nenhum subgênero corresponde à busca. Crie um novo com o botão acima.'
                                            : undefined
                                    }
                                />
                            )}
                        </div>
                    </div>
                </ModalShell>
            ) : null}
        </>
    );
}

export function MusicCatalogPicker({
    genres,
    subgenres,
    selectedGenreId,
    selectedSubgenreIds,
    errors,
    canCreate = false,
    onGenresChange,
    onSubgenresChange,
    onGenreChange,
    onSubgenreIdsChange,
}: Props) {
    const selectedGenre = useMemo(
        () => genres.find((genre) => String(genre.id) === selectedGenreId),
        [genres, selectedGenreId],
    );

    const linkedSubgenres = useMemo(
        () => sortSubgenres(selectedGenre?.subgenres ?? []),
        [selectedGenre?.subgenres],
    );

    const linkedIds = useMemo(
        () => new Set(linkedSubgenres.map((subgenre) => subgenre.id)),
        [linkedSubgenres],
    );

    function selectGenre(value: string | null): void {
        const genreId = value ?? '';
        const nextGenre = genres.find((genre) => String(genre.id) === genreId);
        const allowedIds = new Set(
            (nextGenre?.subgenres ?? []).map((subgenre) => subgenre.id),
        );

        onGenreChange(genreId);
        onSubgenreIdsChange(
            selectedSubgenreIds.filter((id) => allowedIds.has(id)),
        );
    }

    function toggleSubgenre(subgenre: MusicSubgenre): void {
        if (selectedSubgenreIds.includes(subgenre.id)) {
            onSubgenreIdsChange(
                selectedSubgenreIds.filter((id) => id !== subgenre.id),
            );

            return;
        }

        onSubgenreIdsChange([...selectedSubgenreIds, subgenre.id]);
    }

    function upsertLinkedSubgenre(subgenre: MusicSubgenre): void {
        const nextGenres = genres.map((genre) =>
            selectedGenre && genre.id === selectedGenre.id
                ? {
                      ...genre,
                      subgenres: sortSubgenres([
                          ...(genre.subgenres ?? []).filter(
                              (item) => item.id !== subgenre.id,
                          ),
                          subgenre,
                      ]),
                  }
                : genre,
        );
        const nextSubgenres = subgenres.some((item) => item.id === subgenre.id)
            ? subgenres.map((item) =>
                  item.id === subgenre.id ? { ...item, ...subgenre } : item,
              )
            : sortSubgenres([...subgenres, subgenre]);

        onGenresChange(nextGenres);
        onSubgenresChange(nextSubgenres);
        onSubgenreIdsChange([
            ...new Set([...selectedSubgenreIds, subgenre.id]),
        ]);
    }

    return (
        <div className="space-y-4">
            <MusicGenrePicker
                genres={genres}
                selectedGenreId={selectedGenreId}
                error={errors.music_genre_id}
                canCreate={canCreate}
                onGenresChange={onGenresChange}
                onGenreChange={selectGenre}
            />

            <div className="rounded-lg border border-border bg-muted p-3">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Tags
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="text-sm font-medium">Subgêneros</span>
                        <Badge variant="secondary">
                            {selectedSubgenreIds.length} selecionado
                            {selectedSubgenreIds.length === 1 ? '' : 's'}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        {selectedSubgenreIds.length > 0 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onSubgenreIdsChange([])}
                            >
                                Limpar
                            </Button>
                        ) : null}
                        {canCreate ? (
                            <AttachSubgenreButton
                                selectedGenre={selectedGenre}
                                subgenres={subgenres}
                                linkedIds={linkedIds}
                                canCreate={canCreate}
                                onLinked={upsertLinkedSubgenre}
                            />
                        ) : null}
                    </div>
                </div>

                {selectedGenre ? (
                    <div className="flex flex-wrap gap-2">
                        {linkedSubgenres.length > 0 ? (
                            linkedSubgenres.map((subgenre) => {
                                const selected = selectedSubgenreIds.includes(
                                    subgenre.id,
                                );

                                return (
                                    <button
                                        key={subgenre.id}
                                        type="button"
                                        disabled={!subgenre.is_active}
                                        aria-pressed={selected}
                                        className={cn(
                                            'inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-sm transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50',
                                            selected
                                                ? 'border-transparent bg-primary-soft font-medium text-primary-soft-foreground'
                                                : 'border-border bg-card hover:bg-accent',
                                        )}
                                        onClick={() => toggleSubgenre(subgenre)}
                                    >
                                        {selected ? (
                                            <Check className="size-3.5" />
                                        ) : (
                                            <Plus className="size-3.5" />
                                        )}
                                        {subgenre.name}
                                    </button>
                                );
                            })
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Este gênero ainda não tem subgêneros vinculados.
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Selecione um gênero para ver ou atrelar subgêneros.
                    </p>
                )}
            </div>

            <InputError message={errors.subgenre_ids} />
        </div>
    );
}

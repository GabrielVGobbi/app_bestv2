import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import {
    Check,
    Link2,
    Loader2,
    Music2,
    Pencil,
    Plus,
    Search,
    SearchX,
    Tags,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import MusicCatalogController from '@/actions/App/Modules/Playlists/Http/Controllers/MusicCatalogController';
import {
    destroySubgenre,
    store as storeGenre,
    storeSubgenre,
    update as updateGenre,
} from '@/actions/App/Modules/Playlists/Http/Controllers/MusicGenreController';
import { update as updateSubgenre } from '@/actions/App/Modules/Playlists/Http/Controllers/MusicSubgenreController';
import { index as playlistsIndex } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    getErrorMessage,
    httpDelete,
    httpPatch,
    httpPost,
    isApiError,
} from '@/lib/http';
import type { ApiResponse, ValidationErrors } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { MusicGenre, MusicSubgenre } from '@/types/app/playlist';

interface Props {
    genres: {
        data: MusicGenre[];
    };
    subgenres: {
        data: MusicSubgenre[];
    };
    permissions: {
        can_create: boolean;
        can_update: boolean;
    };
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

function firstValidationError(error: unknown): string | null {
    if (!isApiError(error) || error.type !== 'validation') {
        return null;
    }

    const errors = error.errors as ValidationErrors<
        'name' | 'is_active' | 'music_subgenre_id'
    >;

    return errors.name ?? errors.music_subgenre_id ?? errors.is_active ?? null;
}

function catalogErrorMessage(error: unknown): string {
    if (!isApiError(error)) {
        return 'Erro inesperado. Tente novamente.';
    }

    return firstValidationError(error) ?? getErrorMessage(error);
}

function CreateGenreControl({
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

            <Dialog
                open={open}
                onOpenChange={(next) => (next ? setOpen(true) : close())}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Novo gênero</DialogTitle>
                        <DialogDescription>
                            Crie um gênero principal para vincular subgêneros do
                            catálogo global.
                        </DialogDescription>
                    </DialogHeader>

                    <Input
                        value={name}
                        placeholder="Nome do gênero"
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
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function MusicCatalogIndex({
    genres: initialGenres,
    subgenres: initialSubgenres,
    permissions,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: playlistsIndex.url() },
            { title: 'Catálogo musical', href: MusicCatalogController.url() },
        ],
    });

    const { auth } = usePage().props;
    const globalFilterStorageKey = `bestplay:music-catalog:global-filter:${auth.user.id}`;
    const [genres, setGenres] = useState(() => sortGenres(initialGenres.data));
    const [subgenres, setSubgenres] = useState(() =>
        sortSubgenres(initialSubgenres.data),
    );
    const [selectedGenreUuid, setSelectedGenreUuid] = useState('');
    const [genreSearch, setGenreSearch] = useState('');
    const [subgenreSearch, setSubgenreSearch] = useState(() =>
        typeof window === 'undefined'
            ? ''
            : (window.localStorage.getItem(globalFilterStorageKey) ?? ''),
    );
    const [editingGenreUuid, setEditingGenreUuid] = useState<string | null>(
        null,
    );
    const [editingSubgenreUuid, setEditingSubgenreUuid] = useState<
        string | null
    >(null);
    const [genreDraft, setGenreDraft] = useState('');
    const [subgenreDraft, setSubgenreDraft] = useState('');
    const [busyKey, setBusyKey] = useState<string | null>(null);

    useEffect(() => {
        window.localStorage.setItem(globalFilterStorageKey, subgenreSearch);
    }, [globalFilterStorageKey, subgenreSearch]);

    const filteredGenres = useMemo(() => {
        const search = normalizeSearch(genreSearch);

        if (!search) {
            return genres;
        }

        return genres.filter((genre) =>
            [genre.name, genre.slug].some((value) =>
                value.toLowerCase().includes(search),
            ),
        );
    }, [genres, genreSearch]);

    const selectedGenre =
        genres.find((genre) => genre.uuid === selectedGenreUuid) ?? null;

    const linkedSubgenres = useMemo(
        () => sortSubgenres(selectedGenre?.subgenres ?? []),
        [selectedGenre?.subgenres],
    );

    const linkedIds = useMemo(
        () => new Set(linkedSubgenres.map((subgenre) => subgenre.id)),
        [linkedSubgenres],
    );

    const filteredGlobalSubgenres = useMemo(() => {
        const search = normalizeSearch(subgenreSearch);

        return subgenres.filter((subgenre) => {
            if (!search) {
                return true;
            }

            return [subgenre.name, subgenre.slug].some((value) =>
                value.toLowerCase().includes(search),
            );
        });
    }, [subgenreSearch, subgenres]);

    const globalCatalogSubgenres = useMemo(
        () =>
            [...filteredGlobalSubgenres].sort((first, second) => {
                const firstLinked = linkedIds.has(first.id);
                const secondLinked = linkedIds.has(second.id);

                if (firstLinked !== secondLinked) {
                    return firstLinked ? 1 : -1;
                }

                return first.name.localeCompare(second.name, 'pt-BR');
            }),
        [filteredGlobalSubgenres, linkedIds],
    );

    const exactExistingSubgenre = useMemo(() => {
        const search = normalizeSearch(subgenreSearch);

        if (!search) {
            return null;
        }

        return (
            subgenres.find(
                (subgenre) =>
                    normalizeSearch(subgenre.name) === search ||
                    normalizeSearch(subgenre.slug) === search,
            ) ?? null
        );
    }, [subgenreSearch, subgenres]);

    const canCreateSubgenre =
        permissions.can_create &&
        Boolean(selectedGenre) &&
        subgenreSearch.trim() !== '' &&
        !exactExistingSubgenre;

    function replaceGenre(nextGenre: MusicGenre): void {
        setGenres((current) =>
            sortGenres(
                current.map((genre) =>
                    genre.uuid === nextGenre.uuid ? nextGenre : genre,
                ),
            ),
        );
    }

    function replaceSubgenreEverywhere(nextSubgenre: MusicSubgenre): void {
        setSubgenres((current) =>
            sortSubgenres(
                current.map((subgenre) =>
                    subgenre.uuid === nextSubgenre.uuid
                        ? { ...subgenre, ...nextSubgenre }
                        : subgenre,
                ),
            ),
        );
        setGenres((current) =>
            current.map((genre) => ({
                ...genre,
                subgenres: sortSubgenres(
                    (genre.subgenres ?? []).map((subgenre) =>
                        subgenre.uuid === nextSubgenre.uuid
                            ? { ...subgenre, ...nextSubgenre }
                            : subgenre,
                    ),
                ),
            })),
        );
    }

    function linkSubgenreLocally(subgenre: MusicSubgenre): void {
        if (!selectedGenre) {
            return;
        }

        setSubgenres((current) =>
            current.some((item) => item.id === subgenre.id)
                ? current
                : sortSubgenres([...current, subgenre]),
        );
        setGenres((current) =>
            current.map((genre) =>
                genre.uuid === selectedGenre.uuid
                    ? {
                          ...genre,
                          subgenres: linkedIds.has(subgenre.id)
                              ? genre.subgenres
                              : sortSubgenres([
                                    ...(genre.subgenres ?? []),
                                    subgenre,
                                ]),
                      }
                    : genre,
            ),
        );
    }

    async function renameGenre(genre: MusicGenre): Promise<void> {
        const name = genreDraft.trim();

        if (!name || !permissions.can_update) {
            return;
        }

        setBusyKey(`genre:update:${genre.uuid}`);

        try {
            const response = await httpPatch<ApiResponse<MusicGenre>>(
                updateGenre.url(genre.uuid),
                { name },
            );
            replaceGenre(response.data);
            setEditingGenreUuid(null);
            toast.success('Gênero atualizado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    async function linkExistingSubgenre(
        subgenre: MusicSubgenre,
    ): Promise<void> {
        if (
            !selectedGenre ||
            !permissions.can_create ||
            linkedIds.has(subgenre.id)
        ) {
            return;
        }

        setBusyKey(`subgenre:link:${subgenre.uuid}`);

        try {
            const response = await httpPost<ApiResponse<MusicSubgenre>>(
                storeSubgenre.url(selectedGenre.uuid),
                { music_subgenre_id: subgenre.id },
            );
            linkSubgenreLocally(response.data);
            toast.success('Subgênero vinculado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    async function unlinkSubgenre(subgenre: MusicSubgenre): Promise<void> {
        if (!selectedGenre || !permissions.can_update) {
            return;
        }

        setBusyKey(`subgenre:unlink:${subgenre.uuid}`);

        try {
            const response = await httpDelete<ApiResponse<MusicGenre>>(
                destroySubgenre.url([selectedGenre.uuid, subgenre.uuid]),
            );
            replaceGenre(response.data);
            toast.success('Subgênero desvinculado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    async function createSubgenre(): Promise<void> {
        const name = subgenreSearch.trim();

        if (!name || !selectedGenre || !permissions.can_create) {
            return;
        }

        if (exactExistingSubgenre) {
            await linkExistingSubgenre(exactExistingSubgenre);

            return;
        }

        setBusyKey('subgenre:create');

        try {
            const response = await httpPost<ApiResponse<MusicSubgenre>>(
                storeSubgenre.url(selectedGenre.uuid),
                { name },
            );
            linkSubgenreLocally(response.data);
            toast.success('Subgênero criado e vinculado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    async function renameSubgenre(subgenre: MusicSubgenre): Promise<void> {
        const name = subgenreDraft.trim();

        if (!name || !permissions.can_update) {
            return;
        }

        setBusyKey(`subgenre:update:${subgenre.uuid}`);

        try {
            const response = await httpPatch<ApiResponse<MusicSubgenre>>(
                updateSubgenre.url(subgenre.uuid),
                { name },
            );
            replaceSubgenreEverywhere(response.data);
            setEditingSubgenreUuid(null);
            toast.success('Subgênero atualizado.');
        } catch (error) {
            toast.error(catalogErrorMessage(error));
        } finally {
            setBusyKey(null);
        }
    }

    return (
        <>
            <Head title="Catálogo musical" />

            <Header
                title="Catálogo musical"
                subtitle="Gêneros selecionam subgêneros do catálogo global"
                backHref={playlistsIndex.url()}
            />

            <div className="page-wrapper-lg">
                <div className="grid gap-4 xl:h-[calc(100dvh-12.5rem)] xl:grid-cols-[21rem_minmax(0,1fr)_22rem]">
                    <Card className="overflow-hidden xl:flex xl:flex-col">
                        <CardHeader className="shrink-0 gap-3 border-b border-separator pb-4">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Music2 className="size-4" />
                                    Gêneros
                                </CardTitle>
                                <CardDescription>
                                    Selecione um gênero para gerenciar
                                    subgêneros.
                                </CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <div className="relative min-w-0 flex-1">
                                    <Search
                                        aria-hidden="true"
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        value={genreSearch}
                                        placeholder="Buscar gênero"
                                        aria-label="Buscar gênero"
                                        className="pl-9"
                                        onChange={(event) =>
                                            setGenreSearch(event.target.value)
                                        }
                                    />
                                </div>
                                {permissions.can_create ? (
                                    <CreateGenreControl
                                        onCreated={(genre) => {
                                            setGenres((current) =>
                                                sortGenres([...current, genre]),
                                            );
                                            setSelectedGenreUuid(genre.uuid);
                                        }}
                                    />
                                ) : null}
                            </div>
                        </CardHeader>
                        <CardContent className="custom-scrollbar min-h-0 space-y-1 overflow-y-auto p-3">
                            {filteredGenres.map((genre) => {
                                const selected =
                                    selectedGenre?.uuid === genre.uuid;

                                return (
                                    <button
                                        key={genre.uuid}
                                        type="button"
                                        aria-pressed={selected}
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                            selected
                                                ? 'bg-secondary text-foreground'
                                                : 'hover:bg-accent',
                                        )}
                                        onClick={() =>
                                            setSelectedGenreUuid(genre.uuid)
                                        }
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium">
                                                {genre.name}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {genre.subgenres?.length ?? 0}{' '}
                                                vínculos
                                            </span>
                                        </span>
                                        {!genre.is_active ? (
                                            <Badge variant="secondary">
                                                Pausado
                                            </Badge>
                                        ) : null}
                                    </button>
                                );
                            })}
                            {filteredGenres.length === 0 ? (
                                <EmptyState
                                    icon={SearchX}
                                    title="Nenhum gênero encontrado"
                                />
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card className="min-h-128 overflow-hidden xl:flex xl:flex-col">
                        {selectedGenre ? (
                            <>
                                <CardHeader className="shrink-0 gap-4 border-b border-separator pb-4">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2">
                                                <Tags className="size-5" />
                                                {selectedGenre.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {linkedSubgenres.length}{' '}
                                                subgêneros vinculados de{' '}
                                                {subgenres.length} no catálogo
                                                global.
                                            </CardDescription>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {permissions.can_update ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        setEditingGenreUuid(
                                                            selectedGenre.uuid,
                                                        );
                                                        setGenreDraft(
                                                            selectedGenre.name,
                                                        );
                                                    }}
                                                >
                                                    <Pencil />
                                                    Editar
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>

                                    {editingGenreUuid === selectedGenre.uuid ? (
                                        <div className="flex gap-2">
                                            <Input
                                                value={genreDraft}
                                                aria-label="Nome do gênero"
                                                autoFocus
                                                onChange={(event) =>
                                                    setGenreDraft(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Button
                                                type="button"
                                                size="lg"
                                                aria-label="Salvar nome"
                                                onClick={() =>
                                                    void renameGenre(
                                                        selectedGenre,
                                                    )
                                                }
                                                disabled={
                                                    busyKey ===
                                                    `genre:update:${selectedGenre.uuid}`
                                                }
                                            >
                                                <Check />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="lg"
                                                aria-label="Cancelar edição"
                                                onClick={() =>
                                                    setEditingGenreUuid(null)
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                    ) : null}
                                </CardHeader>

                                <CardContent className="custom-scrollbar min-h-0 overflow-y-auto p-4">
                                    <div className="grid gap-2 md:grid-cols-2">
                                        {linkedSubgenres.map((subgenre) => (
                                            <div
                                                key={subgenre.uuid}
                                                className={cn(
                                                    'rounded-lg border border-border p-3',
                                                    !subgenre.is_active &&
                                                        'bg-muted',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {subgenre.name}
                                                        </p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-2">
                                                        {!subgenre.is_active ? (
                                                            <Badge variant="secondary">
                                                                Pausado
                                                            </Badge>
                                                        ) : null}
                                                        {permissions.can_update ? (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-7 text-muted-foreground hover:bg-destructive-soft hover:text-destructive-soft-foreground focus-visible:bg-destructive-soft focus-visible:text-destructive-soft-foreground"
                                                                aria-label={`Desvincular ${subgenre.name}`}
                                                                disabled={
                                                                    busyKey ===
                                                                    `subgenre:unlink:${subgenre.uuid}`
                                                                }
                                                                onClick={() =>
                                                                    void unlinkSubgenre(
                                                                        subgenre,
                                                                    )
                                                                }
                                                            >
                                                                {busyKey ===
                                                                `subgenre:unlink:${subgenre.uuid}` ? (
                                                                    <Loader2 className="size-4 animate-spin" />
                                                                ) : (
                                                                    <X className="size-4" />
                                                                )}
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}

                                        {linkedSubgenres.length === 0 ? (
                                            <div className="md:col-span-2">
                                                <EmptyState
                                                    icon={Tags}
                                                    title="Nenhum subgênero vinculado"
                                                    description="Use o catálogo global ao lado para vincular ou criar subgêneros."
                                                />
                                            </div>
                                        ) : null}
                                    </div>
                                </CardContent>
                            </>
                        ) : (
                            <CardContent className="flex flex-1 items-center justify-center">
                                <EmptyState
                                    icon={Music2}
                                    title="Nenhum gênero selecionado"
                                    description="Crie ou selecione um gênero para administrar os vínculos."
                                />
                            </CardContent>
                        )}
                    </Card>

                    <Card className="min-h-112 overflow-hidden xl:flex xl:flex-col">
                        <CardHeader className="shrink-0 gap-3 border-b border-separator pb-4">
                            <div>
                                <CardTitle className="text-base">
                                    Catálogo global
                                </CardTitle>
                                <CardDescription>
                                    Subgêneros existem uma vez e podem ser
                                    usados em vários gêneros.
                                </CardDescription>
                            </div>

                            {selectedGenre ? (
                                <>
                                    <div className="relative">
                                        <Search
                                            aria-hidden="true"
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <Input
                                            value={subgenreSearch}
                                            placeholder="Buscar ou criar subgênero"
                                            aria-label="Buscar ou criar subgênero"
                                            className="pl-9"
                                            disabled={
                                                busyKey === 'subgenre:create'
                                            }
                                            onChange={(event) =>
                                                setSubgenreSearch(
                                                    event.target.value,
                                                )
                                            }
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    event.preventDefault();
                                                    void createSubgenre();
                                                }
                                            }}
                                        />
                                    </div>

                                    {subgenreSearch.trim() !== '' ? (
                                        <div className="space-y-2 rounded-lg border border-border bg-muted p-3">
                                            {canCreateSubgenre ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="w-full justify-start"
                                                    onClick={() =>
                                                        void createSubgenre()
                                                    }
                                                    disabled={
                                                        busyKey ===
                                                        'subgenre:create'
                                                    }
                                                >
                                                    {busyKey ===
                                                    'subgenre:create' ? (
                                                        <Loader2 className="animate-spin" />
                                                    ) : (
                                                        <Plus />
                                                    )}
                                                    Criar “
                                                    {subgenreSearch.trim()}” e
                                                    vincular
                                                </Button>
                                            ) : exactExistingSubgenre &&
                                              !linkedIds.has(
                                                  exactExistingSubgenre.id,
                                              ) &&
                                              permissions.can_create ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="w-full justify-start"
                                                    onClick={() =>
                                                        void linkExistingSubgenre(
                                                            exactExistingSubgenre,
                                                        )
                                                    }
                                                    disabled={
                                                        busyKey ===
                                                        `subgenre:link:${exactExistingSubgenre.uuid}`
                                                    }
                                                >
                                                    <Link2 />
                                                    Vincular “
                                                    {exactExistingSubgenre.name}
                                                    ”
                                                </Button>
                                            ) : exactExistingSubgenre ? (
                                                <p className="text-xs text-muted-foreground">
                                                    “
                                                    {exactExistingSubgenre.name}
                                                    ”{' '}
                                                    {linkedIds.has(
                                                        exactExistingSubgenre.id,
                                                    )
                                                        ? 'já está no catálogo e vinculado ao gênero atual.'
                                                        : 'já existe no catálogo.'}
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </>
                            ) : null}
                        </CardHeader>

                        {selectedGenre ? (
                            <CardContent className="custom-scrollbar min-h-0 space-y-2 overflow-y-auto p-3">
                                {globalCatalogSubgenres.map((subgenre) => {
                                    const linked = linkedIds.has(subgenre.id);
                                    const editing =
                                        editingSubgenreUuid === subgenre.uuid;

                                    return (
                                        <div
                                            key={subgenre.uuid}
                                            className={cn(
                                                'rounded-lg border border-border px-3 py-2 text-sm transition-colors',
                                                linked
                                                    ? 'bg-muted'
                                                    : 'hover:bg-accent',
                                                !linked &&
                                                    !subgenre.is_active &&
                                                    'bg-muted',
                                            )}
                                        >
                                            {editing ? (
                                                <div className="flex gap-2">
                                                    <Input
                                                        value={subgenreDraft}
                                                        aria-label="Nome do subgênero"
                                                        autoFocus
                                                        onChange={(event) =>
                                                            setSubgenreDraft(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        onKeyDown={(event) => {
                                                            if (
                                                                event.key ===
                                                                'Enter'
                                                            ) {
                                                                event.preventDefault();
                                                                void renameSubgenre(
                                                                    subgenre,
                                                                );
                                                            }
                                                        }}
                                                    />
                                                    <Button
                                                        type="button"
                                                        size="lg"
                                                        aria-label="Salvar nome"
                                                        onClick={() =>
                                                            void renameSubgenre(
                                                                subgenre,
                                                            )
                                                        }
                                                        disabled={
                                                            busyKey ===
                                                            `subgenre:update:${subgenre.uuid}`
                                                        }
                                                    >
                                                        <Check />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="lg"
                                                        aria-label="Cancelar edição"
                                                        onClick={() =>
                                                            setEditingSubgenreUuid(
                                                                null,
                                                            )
                                                        }
                                                    >
                                                        <X />
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="flex items-start justify-between gap-3">
                                                    <button
                                                        type="button"
                                                        className="min-w-0 flex-1 text-left disabled:cursor-default"
                                                        disabled={
                                                            linked ||
                                                            !permissions.can_create ||
                                                            busyKey ===
                                                                `subgenre:link:${subgenre.uuid}`
                                                        }
                                                        onClick={() =>
                                                            void linkExistingSubgenre(
                                                                subgenre,
                                                            )
                                                        }
                                                    >
                                                        <span className="block truncate font-medium">
                                                            {subgenre.name}
                                                        </span>
                                                    </button>

                                                    <div className="flex shrink-0 items-center gap-1">
                                                        {linked ? (
                                                            <Badge variant="success">
                                                                Vinculado
                                                            </Badge>
                                                        ) : null}
                                                        {!subgenre.is_active ? (
                                                            <Badge variant="secondary">
                                                                Pausado
                                                            </Badge>
                                                        ) : null}
                                                        {!linked &&
                                                        permissions.can_create ? (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8"
                                                                aria-label={`Vincular ${subgenre.name}`}
                                                                disabled={
                                                                    busyKey ===
                                                                    `subgenre:link:${subgenre.uuid}`
                                                                }
                                                                onClick={() =>
                                                                    void linkExistingSubgenre(
                                                                        subgenre,
                                                                    )
                                                                }
                                                            >
                                                                {busyKey ===
                                                                `subgenre:link:${subgenre.uuid}` ? (
                                                                    <Loader2 className="size-4 animate-spin" />
                                                                ) : (
                                                                    <Link2 className="size-4" />
                                                                )}
                                                            </Button>
                                                        ) : null}
                                                        {permissions.can_update ? (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8"
                                                                aria-label={`Renomear ${subgenre.name}`}
                                                                onClick={() => {
                                                                    setEditingSubgenreUuid(
                                                                        subgenre.uuid,
                                                                    );
                                                                    setSubgenreDraft(
                                                                        subgenre.name,
                                                                    );
                                                                }}
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}

                                {globalCatalogSubgenres.length === 0 ? (
                                    <EmptyState
                                        icon={SearchX}
                                        title="Nenhum subgênero encontrado"
                                        description="Nenhum subgênero do catálogo global corresponde à busca."
                                    />
                                ) : null}
                            </CardContent>
                        ) : (
                            <CardContent className="flex flex-1 items-center justify-center">
                                <EmptyState
                                    icon={Tags}
                                    title="Nenhum gênero selecionado"
                                    description="Selecione um gênero para buscar, criar e vincular subgêneros globais."
                                />
                            </CardContent>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}

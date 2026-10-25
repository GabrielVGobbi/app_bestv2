import { usePage } from '@inertiajs/react';
import { ChevronDown, Settings2, Youtube } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import FormField from '@/components/ui/forms/form-field';
import StackedFormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { MusicCatalogPicker } from '@/pages/app/playlists/components/music-catalog-picker';
import type { Playlist, PlaylistFormOptions } from '@/types/app/playlist';

interface Props {
    playlist?: Partial<Playlist>;
    options: PlaylistFormOptions;
    errors: Record<string, string>;
    layout?: PlaylistFormLayout;
    spotifyImported?: boolean;
}

type PlaylistFormLayout = 'stacked' | 'section-cards';

interface PlaylistFormSectionProps {
    title: string;
    description: string;
    layout: PlaylistFormLayout;
    children: ReactNode;
}

function numberValue(value: number | null | undefined): string {
    return value === null || value === undefined ? '' : String(value);
}

function booleanValue(value: boolean | null | undefined): string {
    if (value === true) {
        return '1';
    }

    if (value === false) {
        return '0';
    }

    return '';
}

function PlaylistFormSection({
    title,
    description,
    layout,
    children,
}: PlaylistFormSectionProps) {
    if (layout === 'section-cards') {
        return (
            <section className="grid grid-cols-1 gap-x-8 gap-y-4 py-6 md:grid-cols-[minmax(0,240px)_minmax(0,1fr)] lg:grid-cols-[minmax(240px,300px)_minmax(0,1fr)]">
                <div className="px-1 md:px-0">
                    <h2 className="text-sm leading-6 font-semibold">{title}</h2>
                    <p className="mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                </div>
                <Card className="min-w-0 overflow-hidden py-0 shadow-xs">
                    <CardContent className="p-4 sm:p-6">{children}</CardContent>
                </Card>
            </section>
        );
    }

    return (
        <StackedFormSection title={title} description={description}>
            {children}
        </StackedFormSection>
    );
}

const spotifyPlaylistBaseUrl = 'https://open.spotify.com/playlist/';
const youtubeBaseUrl = 'https://www.youtube.com/';

export function extractSpotifyPlaylistId(value: string): string {
    const input = value.trim();

    if (!input) {
        return '';
    }

    const uriMatch = input.match(/^spotify:playlist:([A-Za-z0-9]+)$/i);

    if (uriMatch?.[1]) {
        return uriMatch[1];
    }

    const typedSuffix = input.split(/[?#]/)[0].replace(/^\/+|\/+$/g, '');

    if (/^[A-Za-z0-9]*$/.test(typedSuffix)) {
        return typedSuffix;
    }

    try {
        const url = new URL(input);
        const playlistIndex = url.pathname
            .split('/')
            .filter(Boolean)
            .findIndex((segment) => segment === 'playlist');

        if (playlistIndex === -1) {
            return '';
        }

        return url.pathname.split('/').filter(Boolean)[playlistIndex + 1] ?? '';
    } catch {
        return '';
    }
}

function extractYoutubePath(value: string): string {
    const input = value.trim().replace(/^\/+/, '');

    if (!input) {
        return '';
    }

    try {
        const url = new URL(input);
        const segments = url.pathname.split('/').filter(Boolean);

        if (url.hostname.includes('youtu.be')) {
            return segments[0] ? `watch?v=${segments[0]}` : '';
        }

        if (url.hostname.includes('youtube.com')) {
            const videoId = url.searchParams.get('v');
            const playlistId = url.searchParams.get('list');

            if (videoId) {
                return `watch?v=${videoId}`;
            }

            if (segments[0] === 'playlist' && playlistId) {
                return `playlist?list=${playlistId}`;
            }

            return `${segments.join('/')}${url.search}`.replace(/^\/+/, '');
        }
    } catch {
        // Typed suffixes are normalized below.
    }

    return input
        .replace(/^https?:\/\//i, '')
        .replace(/^www\./i, '')
        .replace(/^youtube\.com\/?/i, '')
        .replace(/^youtu\.be\/?/i, 'watch?v=')
        .replace(/^\/+/, '');
}

function canonicalSpotifyUrl(playlistId: string): string {
    return playlistId ? `${spotifyPlaylistBaseUrl}${playlistId}` : '';
}

function canonicalYoutubeUrl(path: string): string {
    return path ? `${youtubeBaseUrl}${path.replace(/^\/+/, '')}` : '';
}

function SpotifyIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            className={className}
            fill="currentColor"
        >
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.59 14.42a.63.63 0 0 1-.86.2c-2.36-1.45-5.33-1.78-8.82-.97a.63.63 0 1 1-.28-1.23c3.82-.87 7.1-.5 9.76 1.13.3.18.39.57.2.87Zm1.22-2.72a.78.78 0 0 1-1.07.25c-2.7-1.66-6.82-2.14-10.02-1.17a.78.78 0 1 1-.45-1.5c3.65-1.11 8.19-.58 11.28 1.32.37.23.48.72.26 1.1Zm.1-2.84C14.67 8.94 9.32 8.76 6.23 9.7a.94.94 0 0 1-.55-1.79c3.55-1.08 9.45-.87 13.18 1.34.44.27.59.84.32 1.29a.94.94 0 0 1-1.28.32Z" />
        </svg>
    );
}

type LinkBrand = 'spotify' | 'youtube';

interface LockedSuffixLinkInputProps {
    id: string;
    value: string;
    brand: LinkBrand;
    prefix: string;
    placeholder: string;
    disabled?: boolean;
    invalid?: boolean;
    normalize: (value: string) => string;
    onChange?: (value: string) => void;
}

function LockedSuffixLinkInput({
    id,
    value,
    brand,
    prefix,
    placeholder,
    disabled = false,
    invalid = false,
    normalize,
    onChange,
}: LockedSuffixLinkInputProps) {
    const isSpotify = brand === 'spotify';

    return (
        <div
            className={cn(
                'flex h-10 items-center gap-2 rounded-md border border-input bg-input-surface px-2 transition-[border-color,box-shadow,color]',
                'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                invalid && 'border-destructive ring-destructive/20',
                disabled && 'opacity-75',
            )}
        >
            <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                {isSpotify ? (
                    <SpotifyIcon className="size-4" />
                ) : (
                    <Youtube className="size-4" />
                )}
            </span>
            <span className="max-w-[13rem] shrink-0 truncate text-xs font-medium text-muted-foreground">
                {prefix}
            </span>
            <Input
                id={id}
                type="text"
                value={value}
                readOnly={disabled}
                disabled={disabled}
                placeholder={placeholder}
                autoComplete="off"
                aria-invalid={invalid}
                className="h-8 border-0 bg-transparent px-0 shadow-none focus-visible:border-transparent focus-visible:ring-0 disabled:pointer-events-auto"
                onChange={(event) => onChange?.(normalize(event.target.value))}
            />
        </div>
    );
}

function hasLegacyMetadata(
    metadata: Playlist['external_metadata'] | undefined,
): boolean {
    return Boolean(
        metadata?.legacy_spotify_id ||
        metadata?.legacy_spotify_key ||
        metadata?.source,
    );
}

function hasLegacyErrors(errors: Record<string, string>): boolean {
    return Object.keys(errors).some((key) =>
        key.startsWith('external_metadata.'),
    );
}

function getRoleIdentifier(role: unknown): string {
    if (typeof role === 'string') {
        return role;
    }

    if (!role || typeof role !== 'object') {
        return '';
    }

    const candidate = role as { slug?: unknown; name?: unknown };

    return String(candidate.slug ?? candidate.name ?? '');
}

function isDeveloperRole(role: unknown): boolean {
    const roleIdentifier = getRoleIdentifier(role).trim().toLowerCase();

    return roleIdentifier === 'developer' || roleIdentifier === 'desenvolvedor';
}

function canSeeLegacyData(user: unknown): boolean {
    if (!user || typeof user !== 'object') {
        return false;
    }

    const roles = (user as { roles?: unknown }).roles;

    return Array.isArray(roles) && roles.some(isDeveloperRole);
}

interface LegacyMetadataFieldsProps {
    metadata: Playlist['external_metadata'] | undefined;
    errors: Record<string, string>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function LegacyMetadataFields({
    metadata,
    errors,
    open,
    onOpenChange,
}: LegacyMetadataFieldsProps) {
    return (
        <Collapsible open={open} onOpenChange={onOpenChange}>
            <section className="rounded-lg border border-border bg-muted">
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="flex w-full items-center justify-between gap-3 rounded-lg px-4 py-3 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <span className="flex min-w-0 items-center gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-card text-muted-foreground">
                                <Settings2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <span className="min-w-0">
                                <span className="block text-sm font-semibold">
                                    Dados legados
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    Campos técnicos mantidos para migração e
                                    rastreabilidade
                                </span>
                            </span>
                        </span>
                        <ChevronDown
                            aria-hidden="true"
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                open && 'rotate-180',
                            )}
                        />
                    </button>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <div className="rounded-b-lg border-t border-separator bg-card px-4 py-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                id="field-legacy-id"
                                label="Spotify ID legado"
                                variant="stacked"
                                error={
                                    errors[
                                        'external_metadata.legacy_spotify_id'
                                    ]
                                }
                            >
                                <Input
                                    id="field-legacy-id"
                                    name="external_metadata[legacy_spotify_id]"
                                    type="text"
                                    defaultValue={
                                        metadata?.legacy_spotify_id ?? ''
                                    }
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                id="field-legacy-key"
                                label="Spotify Key legado"
                                variant="stacked"
                                error={
                                    errors[
                                        'external_metadata.legacy_spotify_key'
                                    ]
                                }
                            >
                                <Input
                                    id="field-legacy-key"
                                    name="external_metadata[legacy_spotify_key]"
                                    type="text"
                                    defaultValue={
                                        metadata?.legacy_spotify_key ?? ''
                                    }
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                id="field-source"
                                label="Origem"
                                variant="stacked"
                                error={errors['external_metadata.source']}
                            >
                                <Input
                                    id="field-source"
                                    name="external_metadata[source]"
                                    type="text"
                                    defaultValue={metadata?.source ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                        </div>
                    </div>
                </CollapsibleContent>
            </section>
        </Collapsible>
    );
}

export default function PlaylistFormFields({
    playlist,
    options,
    errors,
    layout = 'stacked',
    spotifyImported = false,
}: Props) {
    const { auth } = usePage().props;
    const [genreId, setGenreId] = useState(
        playlist?.music_genre_id ? String(playlist.music_genre_id) : '',
    );
    const [selectedSubgenreIds, setSelectedSubgenreIds] = useState<number[]>(
        playlist?.subgenres?.map((subgenre) => subgenre.id) ?? [],
    );
    const [genres, setGenres] = useState(options.genres);
    const [subgenres, setSubgenres] = useState(options.subgenres);
    const [spotifyPlaylistId, setSpotifyPlaylistId] = useState(
        extractSpotifyPlaylistId(
            playlist?.spotify_url ?? playlist?.spotify_playlist_id ?? '',
        ),
    );
    const [youtubePath, setYoutubePath] = useState(
        extractYoutubePath(playlist?.youtube_url ?? ''),
    );
    const [isLegacyOpen, setIsLegacyOpen] = useState(
        hasLegacyMetadata(playlist?.external_metadata) ||
            hasLegacyErrors(errors),
    );
    const showLegacyData = canSeeLegacyData(auth.user);
    const spotifyUrl = canonicalSpotifyUrl(spotifyPlaylistId);
    const youtubeUrl = canonicalYoutubeUrl(youtubePath);

    function changeGenre(value: string): void {
        setGenreId(value);
        const nextGenre = genres.find((genre) => String(genre.id) === value);
        const allowedIds = new Set(
            (nextGenre?.subgenres ?? []).map((subgenre) => subgenre.id),
        );

        setSelectedSubgenreIds((current) =>
            current.filter((id) => allowedIds.has(id)),
        );
    }

    const notesField = (
        <FormField
            id="field-notes"
            label="Observações"
            variant="stacked"
            error={errors.notes}
        >
            <Textarea
                id="field-notes"
                name="notes"
                defaultValue={playlist?.notes ?? ''}
                rows={3}
            />
        </FormField>
    );

    const legacyFields = (
        <LegacyMetadataFields
            metadata={playlist?.external_metadata}
            errors={errors}
            open={isLegacyOpen}
            onOpenChange={setIsLegacyOpen}
        />
    );

    return (
        <div
            className={
                layout === 'section-cards'
                    ? 'divide-y divide-border'
                    : 'space-y-8'
            }
        >
            <PlaylistFormSection
                title="Identidade comercial"
                description="Nome de venda e parceiro. O nome do Spotify fica separado e não sobrescreve este cadastro."
                layout={layout}
            >
                <input type="hidden" name="music_genre_id" value={genreId} />
                <input
                    type="hidden"
                    name="spotify_snapshot_id"
                    value={playlist?.spotify_snapshot_id ?? ''}
                />
                <input
                    type="hidden"
                    name="spotify_owner_id"
                    value={playlist?.spotify_owner_id ?? ''}
                />
                <input
                    type="hidden"
                    name="spotify_owner_name"
                    value={playlist?.spotify_owner_name ?? ''}
                />
                <input
                    type="hidden"
                    name="spotify_tracks_total"
                    value={numberValue(playlist?.spotify_tracks_total)}
                />
                <input
                    type="hidden"
                    name="spotify_name"
                    value={playlist?.spotify_name ?? ''}
                />
                <input
                    type="hidden"
                    name="spotify_description"
                    value={playlist?.spotify_description ?? ''}
                />
                <input
                    type="hidden"
                    name="spotify_public"
                    value={booleanValue(playlist?.spotify_public)}
                />
                <input
                    type="hidden"
                    name="spotify_image_url"
                    value={playlist?.spotify_image_url ?? ''}
                />
                {!showLegacyData && playlist?.external_metadata ? (
                    <input
                        type="hidden"
                        name="external_metadata"
                        value={JSON.stringify(playlist.external_metadata)}
                    />
                ) : null}
                {selectedSubgenreIds.map((id) => (
                    <input
                        key={id}
                        type="hidden"
                        name="subgenre_ids[]"
                        value={id}
                    />
                ))}

                <div className="grid max-w-2xl gap-4 sm:grid-cols-2">
                    <FormField
                        id="field-name"
                        label="Nome"
                        variant="stacked"
                        required
                        error={errors.name}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="field-name"
                            name="name"
                            type="text"
                            defaultValue={playlist?.name ?? ''}
                            required
                            minLength={3}
                            autoComplete="off"
                            aria-invalid={!!errors.name}
                        />
                    </FormField>
                    <FormField
                        id="field-partner-name"
                        label="Parceiro"
                        variant="stacked"
                        error={errors.partner_name}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="field-partner-name"
                            name="partner_name"
                            type="text"
                            defaultValue={playlist?.partner_name ?? ''}
                            autoComplete="off"
                            aria-invalid={!!errors.partner_name}
                        />
                    </FormField>
                </div>
            </PlaylistFormSection>

            <PlaylistFormSection
                title="Classificação"
                description="Gênero obrigatório e subgêneros para organizar o inventário comercial."
                layout={layout}
            >
                <MusicCatalogPicker
                    genres={genres}
                    subgenres={subgenres}
                    selectedGenreId={genreId}
                    selectedSubgenreIds={selectedSubgenreIds}
                    errors={errors}
                    canCreate={options.can_create_music_catalog}
                    required
                    onGenresChange={setGenres}
                    onSubgenresChange={setSubgenres}
                    onGenreChange={changeGenre}
                    onSubgenreIdsChange={setSelectedSubgenreIds}
                />
            </PlaylistFormSection>

            <PlaylistFormSection
                title="Métricas e links extras"
                description={
                    spotifyImported
                        ? 'Alcance comercial e YouTube. O Spotify já veio do import acima.'
                        : 'Referências públicas e números usados na triagem comercial.'
                }
                layout={layout}
            >
                <div className="space-y-4">
                    <input
                        type="hidden"
                        name="spotify_url"
                        value={spotifyUrl}
                    />
                    <input
                        type="hidden"
                        name="spotify_playlist_id"
                        value={spotifyPlaylistId}
                    />
                    {spotifyImported ? (
                        <input
                            type="hidden"
                            name="followers_count"
                            value={numberValue(playlist?.followers_count)}
                        />
                    ) : (
                        <FormField
                            id="field-spotify-url"
                            label="Link Spotify"
                            variant="inline"
                            className="sm:grid-cols-[9.5rem_1fr]"
                            error={
                                errors.spotify_url ?? errors.spotify_playlist_id
                            }
                        >
                            <LockedSuffixLinkInput
                                id="field-spotify-url"
                                brand="spotify"
                                prefix="open.spotify.com/playlist/"
                                value={spotifyPlaylistId}
                                placeholder="5bNqE1YOKGpbylAsxAiNtC"
                                invalid={
                                    !!errors.spotify_url ||
                                    !!errors.spotify_playlist_id
                                }
                                normalize={extractSpotifyPlaylistId}
                                onChange={setSpotifyPlaylistId}
                            />
                        </FormField>
                    )}

                    <FormField
                        id="field-youtube-url"
                        label="Link YouTube"
                        variant="inline"
                        className="sm:grid-cols-[9.5rem_1fr]"
                        error={errors.youtube_url}
                    >
                        <input
                            type="hidden"
                            name="youtube_url"
                            value={youtubeUrl}
                        />
                        <LockedSuffixLinkInput
                            id="field-youtube-url"
                            brand="youtube"
                            prefix="youtube.com/"
                            value={youtubePath}
                            placeholder="@canal, watch?v=... ou playlist?list=..."
                            invalid={!!errors.youtube_url}
                            normalize={extractYoutubePath}
                            onChange={setYoutubePath}
                        />
                    </FormField>

                    {spotifyImported ? null : (
                        <FormField
                            id="field-followers"
                            label="Seguidores"
                            variant="inline"
                            className="sm:grid-cols-[9.5rem_1fr]"
                            error={errors.followers_count}
                        >
                            <Input
                                id="field-followers"
                                name="followers_count"
                                type="number"
                                min={0}
                                defaultValue={numberValue(
                                    playlist?.followers_count,
                                )}
                                aria-invalid={!!errors.followers_count}
                            />
                        </FormField>
                    )}

                    <FormField
                        id="field-estimated-reach"
                        label="Alcance estimado"
                        variant="inline"
                        className="sm:grid-cols-[9.5rem_1fr]"
                        error={errors.estimated_reach}
                    >
                        <Input
                            id="field-estimated-reach"
                            name="estimated_reach"
                            type="number"
                            min={0}
                            defaultValue={numberValue(
                                playlist?.estimated_reach,
                            )}
                            aria-invalid={!!errors.estimated_reach}
                        />
                    </FormField>
                </div>
            </PlaylistFormSection>

            {layout === 'section-cards' ? (
                <PlaylistFormSection
                    title="Observações"
                    description="Anotações internas para qualificação, operação comercial e contexto da curadoria."
                    layout={layout}
                >
                    {notesField}
                </PlaylistFormSection>
            ) : (
                notesField
            )}

            {showLegacyData ? (
                layout === 'section-cards' ? (
                    <PlaylistFormSection
                        title="Dados legados"
                        description="Campos técnicos mantidos para migração e rastreabilidade."
                        layout={layout}
                    >
                        {legacyFields}
                    </PlaylistFormSection>
                ) : (
                    legacyFields
                )
            ) : null}
        </div>
    );
}

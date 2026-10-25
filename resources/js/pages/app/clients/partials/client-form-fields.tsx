import { BadgeCheck, Handshake } from 'lucide-react';
import { useState } from 'react';

import { AsyncSelect } from '@/components/async-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { InputDocument } from '@/components/ui/input-document';
import { InputPhone } from '@/components/ui/input-phone';
import { Separator } from '@/components/ui/separator';
import { SwitchOption } from '@/components/ui/switch-option';
import { Textarea } from '@/components/ui/textarea';
import { apiRoutes } from '@/lib/api/api-routes';
import { MusicGenrePicker } from '@/pages/app/playlists/components/music-catalog-picker';
import type { Client } from '@/types/app/client';
import type { MusicGenre } from '@/types/app/playlist';

const classificationOptions = [
    'Classe S',
    'Classe A',
    'Classe B',
    'Classe C',
    'Classe Iniciante',
];

interface Props {
    client?: Partial<Client>;
    errors: Record<string, string>;
    musicGenres?: MusicGenre[];
    canCreateMusicGenre?: boolean;
}

export default function ClientFormFields({
    client,
    errors,
    musicGenres,
    canCreateMusicGenre = false,
}: Props) {
    const record = client;
    const [availableMusicGenres, setAvailableMusicGenres] = useState<
        MusicGenre[]
    >(musicGenres ?? []);
    const [selectedMusicGenreId, setSelectedMusicGenreId] = useState<string>(
        () => {
            const selectedGenre = musicGenres?.find(
                (genre) => genre.name === record?.musical_genre,
            );

            return selectedGenre ? String(selectedGenre.id) : '';
        },
    );
    const [isAgent, setIsAgent] = useState<boolean>(record?.is_agent ?? false);
    const [responsibleUserId, setResponsibleUserId] = useState<string | null>(
        record?.responsible_user_id ? String(record.responsible_user_id) : null,
    );
    const [classification, setClassification] = useState<string>(
        record?.classification ?? '',
    );

    return (
        <div className="space-y-8">
            <FormSection title="Identificação">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="field-name"
                        label="Nome"
                        variant="stacked"
                        required
                        error={errors.name}
                    >
                        <Input
                            id="field-name"
                            name="name"
                            type="text"
                            defaultValue={record?.name ?? ''}
                            required
                            autoComplete="name"
                        />
                    </FormField>

                    <FormField
                        id="field-document"
                        label="CPF/CNPJ"
                        variant="stacked"
                        error={errors.document}
                    >
                        <InputDocument
                            id="field-document"
                            name="document"
                            defaultValue={record?.document ?? ''}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection title="Contato">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="field-email"
                        label="E-mail"
                        variant="stacked"
                        error={errors.email}
                    >
                        <Input
                            id="field-email"
                            name="email"
                            type="email"
                            defaultValue={record?.email ?? ''}
                            autoComplete="email"
                        />
                    </FormField>

                    <FormField
                        id="field-phone"
                        label="Telefone"
                        variant="stacked"
                        error={errors.phone}
                    >
                        <InputPhone
                            id="field-phone"
                            name="phone"
                            defaultValue={record?.phone ?? ''}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection title="Comercial">
                <div className="grid gap-x-5 gap-y-6 sm:grid-cols-2">
                    <FormField
                        id="field-classification"
                        label="Classificação"
                        variant="stacked"
                        error={errors.classification}
                    >
                        <Select
                            value={classification}
                            onValueChange={setClassification}
                        >
                            <SelectTrigger
                                id="field-classification"
                                className="w-full"
                                placeholder="Selecione a classe"
                                icon={BadgeCheck}
                                error={errors.classification}
                            />
                            <SelectContent>
                                {classificationOptions.map((option, index) => (
                                    <SelectItem
                                        key={option}
                                        index={index}
                                        value={option}
                                    >
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            name="classification"
                            type="hidden"
                            value={classification}
                        />
                    </FormField>

                    {!musicGenres ? (
                        <FormField
                            id="field-musical-genre"
                            label="Gênero musical"
                            variant="stacked"
                            error={errors.musical_genre}
                        >
                            <Input
                                id="field-musical-genre"
                                name="musical_genre"
                                type="text"
                                defaultValue={record?.musical_genre ?? ''}
                            />
                        </FormField>
                    ) : null}

                    <FormField
                        id="field-source-id"
                        label="Origem"
                        variant="stacked"
                        error={errors.source_id}
                    >
                        <Input
                            id="field-source-id"
                            name="source_id"
                            type="text"
                            defaultValue={record?.source_id ?? ''}
                        />
                    </FormField>

                    <FormField
                        id="field-responsible-user-id"
                        label="Responsável interno"
                        variant="stacked"
                        error={errors.responsible_user_id}
                    >
                        <AsyncSelect
                            value={responsibleUserId}
                            initialLabel={record?.responsible_user?.name}
                            endpoint={apiRoutes.users.index}
                            params={{ role: 'commercial' }}
                            placeholder="Selecione o responsável"
                            searchPlaceholder="Buscar por nome ou e-mail"
                            emptyMessage="Nenhum usuário comercial encontrado."
                            clearable
                            onChange={setResponsibleUserId}
                        />
                        <input
                            name="responsible_user_id"
                            type="hidden"
                            value={responsibleUserId ?? ''}
                        />
                    </FormField>
                </div>

                <input
                    type="hidden"
                    name="is_agent"
                    value={isAgent ? '1' : '0'}
                />

                <Separator />

                <SwitchOption
                    checked={isAgent}
                    onCheckedChange={setIsAgent}
                    title="Atua como agente ou produtor"
                    description="Marque se este cliente atua como agente ou produtor nas negociações."
                    icon={<Handshake className="size-4" />}
                />
            </FormSection>

            {musicGenres ? (
                <FormSection
                    title="Gênero musical"
                    description="Organize o cliente pelo catálogo musical e crie novos gêneros sem sair do formulário."
                >
                    <input
                        type="hidden"
                        name="musical_genre"
                        value={
                            availableMusicGenres.find(
                                (genre) =>
                                    String(genre.id) === selectedMusicGenreId,
                            )?.name ?? ''
                        }
                    />
                    <MusicGenrePicker
                        genres={availableMusicGenres}
                        selectedGenreId={selectedMusicGenreId}
                        error={errors.musical_genre}
                        canCreate={canCreateMusicGenre}
                        showSubgenreCount={false}
                        onGenresChange={setAvailableMusicGenres}
                        onGenreChange={setSelectedMusicGenreId}
                    />
                </FormSection>
            ) : null}

            <FormField
                id="field-notes"
                label="Observações"
                variant="stacked"
                error={errors.notes}
            >
                <Textarea
                    id="field-notes"
                    name="notes"
                    defaultValue={record?.notes ?? ''}
                    rows={4}
                />
            </FormField>
        </div>
    );
}

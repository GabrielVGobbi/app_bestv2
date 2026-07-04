import { ChevronDown, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { InputDocument } from '@/components/ui/input-document';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import CompanyLogoDropzone from '@/pages/app/companies/partials/company-logo-dropzone';
import type { Company } from '@/types/app/company';

interface Props {
    company?: Partial<Company>;
    errors: Record<string, string>;
}

interface MetadataRow {
    id: string;
    field: string;
    value: string;
}

let metadataRowSequence = 0;

function createMetadataRow(field = '', value = ''): MetadataRow {
    metadataRowSequence += 1;

    return {
        id: `metadata-row-${metadataRowSequence}`,
        field,
        value,
    };
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function stringifyMetadataValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value);
}

function flattenMetadata(
    metadata: Record<string, unknown>,
    prefix = '',
): MetadataRow[] {
    return Object.entries(metadata).flatMap(([key, value]) => {
        const field = prefix ? `${prefix}.${key}` : key;

        if (isRecord(value)) {
            return flattenMetadata(value, field);
        }

        return [createMetadataRow(field, stringifyMetadataValue(value))];
    });
}

function metadataToRows(
    metadata: Company['fiscal_metadata'] | undefined,
): MetadataRow[] {
    if (!metadata) {
        return [createMetadataRow()];
    }

    const rows = flattenMetadata(metadata);

    return rows.length > 0 ? rows : [createMetadataRow()];
}

function parseMetadataValue(value: string): unknown {
    const trimmedValue = value.trim();
    const looksLikeJson =
        (trimmedValue.startsWith('{') && trimmedValue.endsWith('}')) ||
        (trimmedValue.startsWith('[') && trimmedValue.endsWith(']'));

    if (!looksLikeJson) {
        return value;
    }

    try {
        return JSON.parse(trimmedValue) as unknown;
    } catch {
        return value;
    }
}

function assignMetadataValue(
    metadata: Record<string, unknown>,
    path: string[],
    value: unknown,
): void {
    let current = metadata;

    for (let index = 0; index < path.length; index += 1) {
        const segment = path[index];

        if (!segment) {
            continue;
        }

        if (index === path.length - 1) {
            current[segment] = value;

            return;
        }

        const nextValue = current[segment];

        if (!isRecord(nextValue)) {
            current[segment] = {};
        }

        current = current[segment] as Record<string, unknown>;
    }
}

function rowsToMetadata(rows: MetadataRow[]): Record<string, unknown> {
    return rows.reduce<Record<string, unknown>>((metadata, row) => {
        const path = row.field
            .split('.')
            .map((segment) => segment.trim())
            .filter(Boolean);

        if (path.length === 0) {
            return metadata;
        }

        assignMetadataValue(metadata, path, parseMetadataValue(row.value));

        return metadata;
    }, {});
}

function serializeMetadata(rows: MetadataRow[]): string {
    const hasMetadata = rows.some((row) => row.field.trim() !== '');

    if (!hasMetadata) {
        return '';
    }

    return JSON.stringify(rowsToMetadata(rows));
}

export default function CompanyFormFields({ company, errors }: Props) {
    const record = company;
    const [isMetadataOpen, setMetadataOpen] = useState(
        () => !!errors.fiscal_metadata,
    );
    const [metadataRows, setMetadataRows] = useState<MetadataRow[]>(() =>
        metadataToRows(record?.fiscal_metadata),
    );

    const activeMetadataCount = useMemo(
        () => metadataRows.filter((row) => row.field.trim() !== '').length,
        [metadataRows],
    );
    const serializedMetadata = useMemo(
        () => serializeMetadata(metadataRows),
        [metadataRows],
    );

    function updateMetadataRow(
        id: string,
        key: 'field' | 'value',
        value: string,
    ): void {
        setMetadataRows((rows) =>
            rows.map((row) => (row.id === id ? { ...row, [key]: value } : row)),
        );
    }

    function addMetadataRow(): void {
        setMetadataRows((rows) => [...rows, createMetadataRow()]);
    }

    function removeMetadataRow(id: string): void {
        setMetadataRows((rows) => {
            const nextRows = rows.filter((row) => row.id !== id);

            return nextRows.length > 0 ? nextRows : [createMetadataRow()];
        });
    }

    return (
        <div className="space-y-8">
            <FormSection
                title="Identificação"
                description="Empresas internas do grupo usadas como prestadoras e referência fiscal."
            >
                <div className="grid gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <CompanyLogoDropzone company={record} error={errors.logo} />

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
                                autoComplete="organization"
                                aria-invalid={!!errors.name}
                            />
                        </FormField>

                        <FormField
                            id="field-nickname"
                            label="Apelido"
                            variant="stacked"
                            required
                            error={errors.nickname}
                        >
                            <Input
                                id="field-nickname"
                                name="nickname"
                                type="text"
                                defaultValue={record?.nickname ?? ''}
                                required
                                aria-invalid={!!errors.nickname}
                            />
                        </FormField>

                        <FormField
                            id="field-cnpj"
                            label="CNPJ"
                            variant="stacked"
                            required
                            error={errors.cnpj}
                        >
                            <InputDocument
                                id="field-cnpj"
                                name="cnpj"
                                kind="cnpj"
                                defaultValue={record?.cnpj ?? ''}
                                required
                                aria-invalid={!!errors.cnpj}
                            />
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection
                title="Metadados fiscais"
                description="Payload fiscal original mantido como metadados estruturados."
            >
                <input
                    type="hidden"
                    name="fiscal_metadata"
                    value={serializedMetadata}
                />

                <Collapsible
                    open={isMetadataOpen}
                    onOpenChange={setMetadataOpen}
                >
                    <Card
                        className={cn(
                            'gap-0 overflow-hidden py-0',
                            errors.fiscal_metadata && 'border-destructive',
                        )}
                    >
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition-colors hover:bg-secondary/60 focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/15 focus-visible:outline-none"
                                aria-expanded={isMetadataOpen}
                            >
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold">
                                        Campos fiscais
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {activeMetadataCount === 0
                                            ? 'Nenhum campo informado'
                                            : `${activeMetadataCount} campo${activeMetadataCount === 1 ? '' : 's'}`}
                                    </span>
                                </span>

                                <ChevronDown
                                    className={cn(
                                        'size-4 shrink-0 text-muted-foreground transition-transform',
                                        isMetadataOpen && 'rotate-180',
                                    )}
                                />
                            </button>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <CardContent className="space-y-4 border-t px-4 py-4">
                                <div className="space-y-3">
                                    {metadataRows.map((row, index) => (
                                        <div
                                            key={row.id}
                                            className="grid gap-3 rounded-md border bg-background/60 p-3 sm:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)_auto]"
                                        >
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`${row.id}-field`}
                                                    className="text-sm font-semibold text-foreground"
                                                >
                                                    Campo
                                                </Label>
                                                <Input
                                                    id={`${row.id}-field`}
                                                    type="text"
                                                    value={row.field}
                                                    placeholder="razaoSocial"
                                                    onChange={(event) =>
                                                        updateMetadataRow(
                                                            row.id,
                                                            'field',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`${row.id}-value`}
                                                    className="text-sm font-semibold text-foreground"
                                                >
                                                    Valor
                                                </Label>
                                                <textarea
                                                    id={`${row.id}-value`}
                                                    value={row.value}
                                                    rows={2}
                                                    placeholder="BEST PLAY MARKETING DIRETO LTDA"
                                                    className="border-input bg-background placeholder:text-muted-foreground flex min-h-9 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[border-color,box-shadow,color] focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/15"
                                                    onChange={(event) =>
                                                        updateMetadataRow(
                                                            row.id,
                                                            'value',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="self-end text-muted-foreground hover:text-destructive"
                                                onClick={() =>
                                                    removeMetadataRow(row.id)
                                                }
                                                aria-label={`Remover campo fiscal ${index + 1}`}
                                                title="Remover campo"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    {errors.fiscal_metadata ? (
                                        <p className="text-sm text-destructive">
                                            {errors.fiscal_metadata}
                                        </p>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            Metadados opcionais
                                        </span>
                                    )}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="w-full sm:w-auto"
                                        onClick={addMetadataRow}
                                    >
                                        <Plus className="size-4" />
                                        Adicionar campo
                                    </Button>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Card>
                </Collapsible>
            </FormSection>
        </div>
    );
}

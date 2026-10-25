import { ImagePlus, RefreshCw, Trash2, UploadCloud } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Company } from '@/types/app/company';

interface Props {
    company?: Partial<Company>;
    error?: string;
}

function makeInitials(value: string | null | undefined): string {
    const words = (value ?? '').trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return 'EM';
    }

    return words
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
}

export default function CompanyLogoDropzone({ company, error }: Props) {
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [fileName, setFileName] = useState<string | null>(null);
    const [removeLogo, setRemoveLogo] = useState(false);
    const [isDragging, setDragging] = useState(false);

    const existingLogoUrl = removeLogo ? null : (company?.logo_url ?? null);
    const visiblePreviewUrl = previewUrl ?? existingLogoUrl;
    const hasStoredLogo = Boolean(company?.logo_url || company?.logo_path);
    const initials =
        company?.logo_initials ??
        makeInitials(company?.nickname ?? company?.name);
    const helperText = useMemo(() => {
        if (fileName) {
            return fileName;
        }

        return hasStoredLogo && !removeLogo
            ? 'Imagem atual'
            : 'Mockup automático';
    }, [fileName, hasStoredLogo, removeLogo]);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function setFile(file: File | null): void {
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            return;
        }

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        setPreviewUrl(URL.createObjectURL(file));
        setFileName(file.name);
        setRemoveLogo(false);

        if (inputRef.current) {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            inputRef.current.files = transfer.files;
        }
    }

    function handleInputChange(event: ChangeEvent<HTMLInputElement>): void {
        setFile(event.target.files?.[0] ?? null);
    }

    function handleDrop(event: DragEvent<HTMLDivElement>): void {
        event.preventDefault();
        setDragging(false);
        setFile(event.dataTransfer.files[0] ?? null);
    }

    function clearLogo(): void {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        setPreviewUrl(null);
        setFileName(null);
        setRemoveLogo(hasStoredLogo);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    return (
        <div className="space-y-2">
            <input
                ref={inputRef}
                type="file"
                name="logo"
                accept="image/*"
                className="hidden"
                onChange={handleInputChange}
            />
            <input
                type="hidden"
                name="remove_logo"
                value={removeLogo ? '1' : '0'}
            />

            <div
                className={cn(
                    'rounded-lg border border-dashed border-input bg-card p-4 transition-colors hover:bg-accent',
                    isDragging && 'border-primary bg-accent',
                    error && 'border-destructive',
                )}
                onDragEnter={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragOver={(event) => event.preventDefault()}
                onDragLeave={() => setDragging(false)}
                onDrop={handleDrop}
            >
                <div className="flex items-start gap-4">
                    <div
                        className={cn(
                            'flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg text-lg font-semibold',
                            visiblePreviewUrl
                                ? 'bg-muted'
                                : 'bg-primary-soft text-primary-soft-foreground',
                        )}
                    >
                        {visiblePreviewUrl ? (
                            <img
                                src={visiblePreviewUrl}
                                alt={
                                    company?.name
                                        ? `Logo ${company.name}`
                                        : 'Logo da empresa'
                                }
                                className="size-full object-cover"
                            />
                        ) : (
                            initials
                        )}
                    </div>

                    <div className="min-w-0 flex-1 space-y-3">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 text-sm font-semibold">
                                <UploadCloud
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Imagem da empresa
                            </div>
                            <p className="mt-1 truncate text-xs text-muted-foreground">
                                {helperText}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => inputRef.current?.click()}
                            >
                                {hasStoredLogo || previewUrl ? (
                                    <RefreshCw className="size-4" />
                                ) : (
                                    <ImagePlus className="size-4" />
                                )}
                                {hasStoredLogo || previewUrl
                                    ? 'Trocar'
                                    : 'Adicionar'}
                            </Button>

                            {(hasStoredLogo || previewUrl) && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-muted-foreground hover:bg-destructive-soft hover:text-destructive-soft-foreground"
                                    onClick={clearLogo}
                                >
                                    <Trash2 className="size-4" />
                                    Excluir
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <InputError message={error} />
        </div>
    );
}

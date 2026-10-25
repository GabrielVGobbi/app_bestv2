import { Upload as UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import { partitionFiles } from '../lib/filter-files';
import type { UploadRejection } from '../lib/filter-files';

interface UploadDropzoneProps {
    onDrop: (files: File[], rejected: UploadRejection[]) => void;
    accept?: string;
    maxSizeMb?: number;
    maxFiles?: number;
    disabled?: boolean;
    className?: string;
    helperText?: string;
}

export function UploadDropzone({
    onDrop,
    accept,
    maxSizeMb = 50,
    maxFiles,
    disabled = false,
    className,
    helperText = 'Arraste arquivos aqui ou clique para selecionar',
}: UploadDropzoneProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isOver, setIsOver] = useState(false);

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        if (!e.target.files || disabled) {
            return;
        }

        const { accepted, rejected } = partitionFiles(e.target.files, {
            maxSizeMb,
            maxFiles,
        });

        if (accepted.length > 0 || rejected.length > 0) {
            onDrop(accepted, rejected);
        }

        e.target.value = '';
    }

    function handleDrop(e: React.DragEvent<HTMLDivElement>) {
        e.preventDefault();
        e.stopPropagation();
        setIsOver(false);

        if (disabled) {
            return;
        }

        const { accepted, rejected } = partitionFiles(e.dataTransfer.files, {
            maxSizeMb,
            maxFiles,
        });

        if (accepted.length > 0 || rejected.length > 0) {
            onDrop(accepted, rejected);
        }
    }

    function handleDragOver(e: React.DragEvent<HTMLDivElement>) {
        e.preventDefault();
        e.stopPropagation();

        if (disabled) {
            return;
        }

        setIsOver(true);
    }

    function handleDragLeave(e: React.DragEvent<HTMLDivElement>) {
        e.preventDefault();
        e.stopPropagation();
        setIsOver(false);
    }

    return (
        <div
            onDrop={handleDrop}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onClick={() => !disabled && inputRef.current?.click()}
            className={cn(
                'group relative flex w-full max-w-full min-w-0 cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-lg border-2 border-dashed p-6 text-center transition-colors',
                isOver
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/30',
                disabled && 'pointer-events-none opacity-50',
                className,
            )}
        >
            <input
                ref={inputRef}
                type="file"
                multiple={maxFiles !== 1}
                accept={accept}
                onChange={handleChange}
                className="hidden"
                disabled={disabled}
            />
            <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary/20">
                <UploadIcon className="size-5" />
            </div>
            <p className="text-sm font-medium">{helperText}</p>
            <p className="max-w-full text-xs text-muted-foreground">
                Formatos aceitos: PDF, DOC, DOCX · Máx {maxSizeMb}MB por arquivo
                {maxFiles ? ` · até ${maxFiles} arquivos` : ''}
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-1"
                disabled={disabled}
                onClick={(e) => {
                    e.stopPropagation();
                    inputRef.current?.click();
                }}
            >
                Selecionar arquivo
            </Button>
        </div>
    );
}

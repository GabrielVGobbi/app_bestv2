import { UploadCloud } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { partitionFiles } from '../lib/filter-files';
import type { UploadRejection } from '../lib/filter-files';

interface DropOverlayProps {
    onDrop: (files: File[], rejected: UploadRejection[]) => void;
    accept?: string;
    maxSizeMb?: number;
    maxFiles?: number;
    disabled?: boolean;
    overlayText?: string;
    className?: string;
    children: ReactNode;
}

export function DropOverlay({
    onDrop,
    accept,
    maxSizeMb = 50,
    maxFiles,
    disabled = false,
    overlayText = 'Solte para anexar',
    className,
    children,
}: DropOverlayProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isOver, setIsOver] = useState(false);
    const dragDepth = useRef(0);

    function open() {
        if (disabled) {
            return;
        }

        inputRef.current?.click();
    }

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
        dragDepth.current = 0;
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

    function handleDragEnter(e: React.DragEvent<HTMLDivElement>) {
        e.preventDefault();
        e.stopPropagation();

        if (disabled) {
            return;
        }

        dragDepth.current += 1;
        setIsOver(true);
    }

    function handleDragOver(e: React.DragEvent<HTMLDivElement>) {
        e.preventDefault();
        e.stopPropagation();

        if (disabled) {
            return;
        }

        if (!isOver) {
            setIsOver(true);
        }
    }

    function handleDragLeave(e: React.DragEvent<HTMLDivElement>) {
        e.preventDefault();
        e.stopPropagation();
        dragDepth.current = Math.max(0, dragDepth.current - 1);

        if (dragDepth.current === 0) {
            setIsOver(false);
        }
    }

    return (
        <div
            onDrop={handleDrop}
            onDragEnter={handleDragEnter}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            className={cn('relative', className)}
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

            {children}

            <div
                onClick={open}
                className={cn(
                    'pointer-events-none absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 rounded-[inherit] border-2 border-dashed border-primary bg-primary/10 backdrop-blur-sm transition-opacity duration-150',
                    isOver ? 'opacity-100' : 'opacity-0',
                )}
                aria-hidden={!isOver}
            >
                <div className="flex size-10 items-center justify-center rounded-full bg-primary/15 text-primary">
                    <UploadCloud className="size-5" />
                </div>
                <p className="text-sm font-semibold text-primary">
                    {overlayText}
                </p>
                <p className="text-2xs text-primary/80">
                    Máx {maxSizeMb}MB por arquivo
                    {maxFiles ? ` · até ${maxFiles} arquivos` : ''}
                </p>
            </div>
        </div>
    );
}

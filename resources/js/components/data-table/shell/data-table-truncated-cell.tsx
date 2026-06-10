import type { ReactNode } from 'react';
import { useCallback, useRef, useState } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface DataTableTruncatedCellProps {
    children: ReactNode;
}

export function DataTableTruncatedCell({
    children,
}: DataTableTruncatedCellProps) {
    const elementRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [content, setContent] = useState('');

    const measureOverflow = useCallback(() => {
        const element = elementRef.current;

        if (!element) {
            return false;
        }

        const nextContent = element.textContent?.trim() ?? '';
        const isOverflowing =
            nextContent.length > 0 && element.scrollWidth > element.clientWidth;

        setContent(isOverflowing ? nextContent : '');

        return isOverflowing;
    }, []);

    function handleOpenChange(nextOpen: boolean): void {
        if (!nextOpen) {
            setOpen(false);

            return;
        }

        setOpen(measureOverflow());
    }

    return (
        <Tooltip open={open} onOpenChange={handleOpenChange}>
            <TooltipTrigger asChild>
                <div
                    ref={elementRef}
                    className="min-w-0 truncate"
                    onPointerEnter={measureOverflow}
                >
                    {children}
                </div>
            </TooltipTrigger>
            {content.length > 0 && (
                <TooltipContent
                    side="top"
                    align="start"
                    sideOffset={6}
                    className="max-w-96 rounded-md border bg-popover px-3 py-2 text-xs leading-relaxed text-popover-foreground shadow-md"
                >
                    {content}
                </TooltipContent>
            )}
        </Tooltip>
    );
}

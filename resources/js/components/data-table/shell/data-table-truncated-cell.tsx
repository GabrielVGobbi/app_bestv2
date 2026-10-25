import type { ReactNode } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Tooltip } from '@/components/ui/fluid/tooltip';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface DataTableTruncatedCellProps {
    children: ReactNode;
}

export function DataTableTruncatedCell({
    children,
}: DataTableTruncatedCellProps) {
    const elementRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [content, setContent] = useState('');
    const [expanded, setExpanded] = useState(false);

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

    useEffect(() => {
        measureOverflow();
        const element = elementRef.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(measureOverflow);
        observer.observe(element);

        return () => observer.disconnect();
    }, [children, measureOverflow]);

    function handleOpenChange(nextOpen: boolean): void {
        if (!nextOpen) {
            setOpen(false);

            return;
        }

        setOpen(!expanded && measureOverflow());
    }

    return (
        <Popover
            open={expanded}
            onOpenChange={(nextOpen) => {
                setExpanded(nextOpen);
                setOpen(false);
            }}
        >
            <div className="flex min-w-0 items-center gap-1">
                {/* forceOpen + onOpenChange: o Fluid informa hover/foco e a medição decide abrir. */}
                <Tooltip
                    forceOpen={open}
                    onOpenChange={handleOpenChange}
                    side="top"
                    sideOffset={6}
                    className="max-w-96 leading-relaxed [overflow-wrap:anywhere] whitespace-pre-wrap"
                    content={content}
                >
                    <div
                        ref={elementRef}
                        className="min-w-0 flex-1 truncate"
                        onPointerEnter={measureOverflow}
                        onPointerUp={(event) => {
                            if (
                                event.target instanceof Element &&
                                event.target.closest(
                                    'a, button, input, select, textarea, [role="button"]',
                                )
                            ) {
                                return;
                            }

                            if (measureOverflow()) {
                                setOpen(false);
                                setExpanded(true);
                            }
                        }}
                    >
                        {children}
                    </div>
                </Tooltip>
                {content.length > 0 && (
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-6 shrink-0 text-muted-foreground"
                            aria-label="Expandir texto completo"
                            aria-expanded={expanded}
                        >
                            <span aria-hidden="true">…</span>
                        </Button>
                    </PopoverTrigger>
                )}
            </div>
            <PopoverContent
                align="end"
                side="top"
                sideOffset={8}
                className="max-h-80 w-max max-w-[min(24rem,calc(100vw-2rem))] overflow-y-auto rounded-lg px-3 py-2 text-xs leading-relaxed [overflow-wrap:anywhere] whitespace-pre-wrap shadow-sm"
                aria-label="Texto completo"
            >
                {content}
            </PopoverContent>
        </Popover>
    );
}

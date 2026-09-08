import * as TabsPrimitive from '@radix-ui/react-tabs';
import * as React from 'react';

import { cn } from '@/lib/utils';

function Tabs({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.Root>) {
    return (
        <TabsPrimitive.Root
            data-slot="tabs"
            className={cn('flex flex-col gap-2', className)}
            {...props}
        />
    );
}

function TabsList({
    className,
    variant = 'pills',
    ...props
}: React.ComponentProps<typeof TabsPrimitive.List> & {
    variant?: 'pills' | 'line';
}) {
    return (
        <TabsPrimitive.List
            data-slot="tabs-list"
            data-variant={variant}
            className={cn(
                'group/tabs inline-flex items-center',
                variant === 'pills' &&
                    'h-10 w-fit justify-center rounded-md border border-border/60 bg-muted/80 p-1 text-muted-foreground',
                variant === 'line' &&
                    'h-11 w-full justify-start gap-0 overflow-x-auto rounded-none border-0 border-b border-border/70 bg-transparent p-0 text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

function TabsTrigger({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.Trigger>) {
    return (
        <TabsPrimitive.Trigger
            data-slot="tabs-trigger"
            className={cn(
                'inline-flex items-center justify-center gap-1.5 text-sm font-medium whitespace-nowrap transition-[color,box-shadow,background] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-1 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50',
                'h-8 flex-1 rounded-md border border-transparent px-3 data-[state=active]:bg-card data-[state=active]:text-foreground data-[state=active]:shadow-sm',
                'group-data-[variant=line]/tabs:relative group-data-[variant=line]/tabs:h-11 group-data-[variant=line]/tabs:flex-none group-data-[variant=line]/tabs:rounded-none group-data-[variant=line]/tabs:border-0 group-data-[variant=line]/tabs:bg-transparent group-data-[variant=line]/tabs:px-3.5 group-data-[variant=line]/tabs:text-muted-foreground group-data-[variant=line]/tabs:shadow-none group-data-[variant=line]/tabs:hover:bg-transparent group-data-[variant=line]/tabs:hover:text-foreground group-data-[variant=line]/tabs:data-[state=active]:bg-transparent group-data-[variant=line]/tabs:data-[state=active]:text-foreground group-data-[variant=line]/tabs:data-[state=active]:shadow-none',
                'group-data-[variant=line]/tabs:after:absolute group-data-[variant=line]/tabs:after:right-2 group-data-[variant=line]/tabs:after:bottom-0 group-data-[variant=line]/tabs:after:left-2 group-data-[variant=line]/tabs:after:h-0.5 group-data-[variant=line]/tabs:after:rounded-full group-data-[variant=line]/tabs:after:bg-transparent group-data-[variant=line]/tabs:after:transition-colors group-data-[variant=line]/tabs:data-[state=active]:after:bg-primary',
                className,
            )}
            {...props}
        />
    );
}

function TabsContent({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.Content>) {
    return (
        <TabsPrimitive.Content
            data-slot="tabs-content"
            className={cn('flex-1 outline-none', className)}
            {...props}
        />
    );
}

export { Tabs, TabsContent, TabsList, TabsTrigger };

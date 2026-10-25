import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
    "inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/20 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
    {
        variants: {
            variant: {
                default:
                    "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
                secondary:
                    "border-border/60 bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/80",
                destructive:
                    "border-border bg-card text-foreground before:size-1.5 before:shrink-0 before:rounded-full before:bg-destructive",
                outline:
                    "border-border/80 bg-card text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
                success:
                    "border-border bg-card text-foreground before:size-1.5 before:shrink-0 before:rounded-full before:bg-success",
                warning:
                    "border-border bg-card text-foreground before:size-1.5 before:shrink-0 before:rounded-full before:bg-warning",
                info:
                    "border-border bg-card text-foreground before:size-1.5 before:shrink-0 before:rounded-full before:bg-info",
                inflow:
                    "border-border bg-card text-foreground before:size-1.5 before:shrink-0 before:rounded-full before:bg-inflow",
                outflow:
                    "border-border bg-card text-foreground before:size-1.5 before:shrink-0 before:rounded-full before:bg-outflow",
                muted:
                    "border-border/70 bg-secondary text-muted-foreground [a&]:hover:bg-secondary/80",
            },
        },
        defaultVariants: {
            variant: "default",
        },
    }
)

function Badge({
    className,
    variant,
    asChild = false,
    ...props
}: React.ComponentProps<"span"> &
    VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : "span"

    return (
        <Comp
            data-slot="badge"
            className={cn(badgeVariants({ variant }), className)}
            {...props}
        />
    )
}

export { Badge, badgeVariants }

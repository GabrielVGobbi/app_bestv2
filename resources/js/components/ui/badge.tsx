import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
    "inline-flex items-center justify-center rounded-sm border px-2 py-0.5 text-[11px] font-semibold tracking-[0.01em] w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/20 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
    {
        variants: {
            variant: {
                default:
                    "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
                secondary:
                    "border-border/60 bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/80",
                destructive:
                    "border-destructive/25 bg-destructive-soft text-destructive-soft-foreground [a&]:hover:bg-destructive-soft/80",
                outline:
                    "border-border/80 bg-card text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
                success:
                    "border-success/25 bg-success-soft text-success-soft-foreground [a&]:hover:bg-success-soft/80",
                warning:
                    "border-warning/25 bg-warning-soft text-warning-soft-foreground [a&]:hover:bg-warning-soft/80",
                info:
                    "border-info/25 bg-info-soft text-info-soft-foreground [a&]:hover:bg-info-soft/80",
                inflow:
                    "border-inflow/25 bg-inflow-soft text-inflow-soft-foreground [a&]:hover:bg-inflow-soft/80",
                outflow:
                    "border-outflow/25 bg-outflow-soft text-outflow-soft-foreground [a&]:hover:bg-outflow-soft/80",
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

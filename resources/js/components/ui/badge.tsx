import { Slot } from "@radix-ui/react-slot"
import { cva } from "class-variance-authority"
import type { VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
    "inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:ring-2 focus-visible:ring-ring aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
    {
        variants: {
            variant: {
                default: "border-transparent bg-primary-soft text-primary-soft-foreground",
                secondary: "border-transparent bg-secondary text-secondary-foreground",
                success: "border-transparent bg-success-soft text-success-soft-foreground",
                warning: "border-transparent bg-warning-soft text-warning-soft-foreground",
                destructive: "border-transparent bg-destructive-soft text-destructive-soft-foreground",
                info: "border-transparent bg-info-soft text-info-soft-foreground",
                outline: "border-border text-foreground [a&]:hover:bg-accent",
            },
        },
        defaultVariants: { variant: "default" },
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

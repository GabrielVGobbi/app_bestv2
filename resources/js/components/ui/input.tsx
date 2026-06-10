import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                "bg-background/60 border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary/10 selection:text-primary-foreground flex h-10 w-full min-w-0 ",
                "px-3 py-1 text-base shadow-xs transition-[border-color,box-shadow,color] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent ",
                "file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
                "rounded-md border focus-visible:border-ring focus-visible:ring-ring/15 focus-visible:ring-[2px]",
                "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
                className
            )}
            {...props}
        />
    )
}

export { Input }



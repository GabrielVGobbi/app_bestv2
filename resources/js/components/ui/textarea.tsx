import * as React from 'react';

import { fieldSkinClass } from '@/components/ui/input';
import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                fieldSkinClass,
                'placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex field-sizing-content min-h-16 w-full px-3 py-2 text-base disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };

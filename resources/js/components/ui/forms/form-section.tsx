import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type FormSectionHeadingLevel = 2 | 3 | 4 | 5 | 6;

interface FormSectionProps {
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    actions?: ReactNode;
    headingLevel?: FormSectionHeadingLevel;
    className?: string;
    headerClassName?: string;
    titleClassName?: string;
    descriptionClassName?: string;
    contentClassName?: string;
}

const headingTags: Record<FormSectionHeadingLevel, 'h2' | 'h3' | 'h4' | 'h5' | 'h6'> = {
    2: 'h2',
    3: 'h3',
    4: 'h4',
    5: 'h5',
    6: 'h6',
};

export default function FormSection({
    title,
    description,
    children,
    actions,
    headingLevel = 3,
    className,
    headerClassName,
    titleClassName,
    descriptionClassName,
    contentClassName,
}: FormSectionProps) {
    const Heading = headingTags[headingLevel];

    return (
        <section
            className={cn(
                'grid grid-cols-1 gap-5 border-t py-7 first:border-t-0 first:pt-0 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-8',
                className,
            )}
        >
            <div
                className={cn(
                    'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
                    headerClassName,
                )}
            >
                <div className="space-y-1">
                    <Heading className={cn('text-[15px] font-semibold', titleClassName)}>
                        {title}
                    </Heading>
                    {description ? (
                        <p
                            className={cn(
                                'text-sm leading-6 text-muted-foreground',
                                descriptionClassName,
                            )}
                        >
                            {description}
                        </p>
                    ) : null}
                </div>

                {actions ? <div className="shrink-0">{actions}</div> : null}
            </div>

            <div className={cn("min-w-0", contentClassName)}>{children}</div>
        </section>
    );
}

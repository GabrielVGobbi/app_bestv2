export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'space-y-1'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-semibold tracking-tight'
                        : 'text-2xl font-semibold tracking-[-0.03em]'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}

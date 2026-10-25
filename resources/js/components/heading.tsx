export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    const Title = variant === 'small' ? 'h2' : 'h1';

    return (
        <div className="page-heading">
            <Title
                className={
                    variant === 'small'
                        ? 'text-base font-semibold tracking-tight'
                        : 'page-heading-title'
                }
            >
                {title}
            </Title>
            {description && (
                <p className="page-heading-description">{description}</p>
            )}
        </div>
    );
}

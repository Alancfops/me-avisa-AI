export function PageHeader({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <header className="space-y-1">
            <h1 className="text-3xl font-semibold md:text-4xl">{title}</h1>
            {description && (
                <p className="text-muted-foreground">{description}</p>
            )}
        </header>
    );
}

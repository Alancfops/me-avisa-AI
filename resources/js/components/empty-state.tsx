import type { LucideIcon } from 'lucide-react';

export function EmptyState({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children?: React.ReactNode;
}) {
    return (
        <div className="flex flex-col items-center gap-3 surface p-8 text-center md:p-12">
            <span className="grid size-12 place-items-center rounded-xl bg-primary-soft text-primary">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <p className="max-w-md text-muted-foreground">{title}</p>
            {children}
        </div>
    );
}

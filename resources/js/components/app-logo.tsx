import { usePage } from '@inertiajs/react';
import { BellRing } from 'lucide-react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <BellRing className="size-4" aria-hidden="true" />
            </div>
            <span className="ml-1 flex-1 text-left font-display text-base leading-tight font-semibold">
                {name}
            </span>
        </>
    );
}

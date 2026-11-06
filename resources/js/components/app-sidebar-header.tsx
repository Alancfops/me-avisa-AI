import { ThemeToggle } from '@/components/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';

export function AppSidebarHeader() {
    return (
        <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center justify-between gap-2 border-b bg-background px-3 md:px-4">
            <SidebarTrigger className="size-9" />
            <ThemeToggle />
        </header>
    );
}

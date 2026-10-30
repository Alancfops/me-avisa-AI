import { useEffect, useRef } from 'react';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children }: AppLayoutProps) {
    const { currentUrl } = useCurrentUrl();
    const main = useRef<HTMLElement>(null);
    const previousUrl = useRef(currentUrl);

    // Troca de página no Inertia não recarrega o documento: sem isso, o foco
    // de quem usa teclado se perde no link que acabou de sumir ou ficou para trás.
    useEffect(() => {
        if (previousUrl.current === currentUrl) {
            return;
        }

        previousUrl.current = currentUrl;

        if (!main.current?.contains(document.activeElement)) {
            main.current?.focus({ preventScroll: true });
        }
    }, [currentUrl]);

    return (
        <AppShell variant="sidebar">
            <a
                href="#conteudo"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-60 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-primary-foreground"
            >
                Pular para o conteúdo
            </a>
            <AppSidebar />
            <div className="flex min-h-svh min-w-0 flex-1 flex-col">
                <AppSidebarHeader />
                <main
                    id="conteudo"
                    ref={main}
                    tabIndex={-1}
                    className="flex-1 p-4 outline-none md:p-8"
                >
                    {children}
                </main>
            </div>
        </AppShell>
    );
}

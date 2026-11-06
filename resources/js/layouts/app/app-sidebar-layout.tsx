import { Link, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children }: AppLayoutProps) {
    const { currentUrl } = useCurrentUrl();
    const { brapiConfigured } = usePage().props;
    const onSettings = currentUrl.startsWith('/settings');
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
                    {!brapiConfigured && !onSettings && (
                        <div
                            role="status"
                            className="mx-auto mb-6 flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 surface p-4 text-sm"
                        >
                            <span className="flex items-start gap-2">
                                <KeyRound
                                    className="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                Falta a chave da brapi: sem ela, as cotações dos
                                favoritos não atualizam.
                            </span>
                            <Link
                                href="/settings/appearance#brapi"
                                className="font-semibold text-primary underline"
                            >
                                Colar a chave
                            </Link>
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </AppShell>
    );
}

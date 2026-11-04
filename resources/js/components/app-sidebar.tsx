import { Link } from '@inertiajs/react';
import {
    Activity,
    Bell,
    Building2,
    List,
    Settings,
    Star,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavGroup, NavItem } from '@/types';

const mainNavItems: (NavItem | NavGroup)[] = [
    {
        title: 'FIIs',
        icon: Building2,
        items: [
            { title: 'Favoritos', href: '/', icon: Star },
            { title: 'Todos os FIIs', href: '/fundos', icon: List },
            { title: 'Minha carteira', href: '/carteira', icon: Wallet },
        ],
    },
    {
        title: 'Avisos',
        href: '/avisos',
        icon: Bell,
    },
    {
        title: 'Saúde',
        href: '/saude',
        icon: Activity,
    },
    {
        title: 'Configurações',
        href: '/settings/appearance',
        icon: Settings,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon">
            <nav aria-label="Menu principal" className="contents">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link
                                    href="/"
                                    prefetch
                                    aria-label="Me Avisa Aí, ir para os favoritos"
                                >
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <NavMain items={mainNavItems} />
                </SidebarContent>
            </nav>
        </Sidebar>
    );
}

import { Link } from '@inertiajs/react';
import { Activity, Bell, LayoutGrid, Settings } from 'lucide-react';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Painel',
        href: '/',
        icon: LayoutGrid,
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
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href="/"
                                prefetch
                                aria-label="Me Avisa Aí, ir para o painel"
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
        </Sidebar>
    );
}

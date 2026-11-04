import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup, NavItem } from '@/types';

function isGroup(item: NavItem | NavGroup): item is NavGroup {
    return 'items' in item;
}

function useIsActive() {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    // A tela de um fundo é aberta a partir dos Favoritos.
    return (item: NavItem) =>
        isCurrentUrl(item.href) ||
        (item.href === '/' && currentUrl.startsWith('/fundos/'));
}

export function NavMain({ items }: { items: (NavItem | NavGroup)[] }) {
    const isActive = useIsActive();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {items.map((item) =>
                    isGroup(item) ? (
                        <NavGroupItem
                            key={item.title}
                            group={item}
                            isActive={isActive}
                        />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive(item)}
                                tooltip={{ children: item.title }}
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={
                                        isActive(item) ? 'page' : undefined
                                    }
                                >
                                    {item.icon && (
                                        <item.icon aria-hidden="true" />
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function NavGroupItem({
    group,
    isActive,
}: {
    group: NavGroup;
    isActive: (item: NavItem) => boolean;
}) {
    const [open, setOpen] = useState(true);
    const { state, isMobile, setOpen: setSidebarOpen } = useSidebar();
    // Recolhido, o menu só mostra ícones e esconde os itens do grupo: o clique abre o menu.
    const iconOnly = state === 'collapsed' && !isMobile;
    const hasActive = group.items.some(isActive);

    return (
        <Collapsible
            asChild
            open={open}
            onOpenChange={setOpen}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={iconOnly && hasActive}
                        tooltip={{ children: group.title }}
                        onClick={(event) => {
                            if (iconOnly) {
                                event.preventDefault();
                                setOpen(true);
                                setSidebarOpen(true);
                            }
                        }}
                    >
                        <group.icon aria-hidden="true" />
                        <span>{group.title}</span>
                        <ChevronRight
                            aria-hidden="true"
                            className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none"
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {group.items.map((item) => (
                            <SidebarMenuSubItem key={item.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isActive(item)}
                                >
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-current={
                                            isActive(item) ? 'page' : undefined
                                        }
                                    >
                                        {item.icon && (
                                            <item.icon aria-hidden="true" />
                                        )}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

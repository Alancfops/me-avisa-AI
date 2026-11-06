import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/** Grupo do menu que abre e fecha, como "FIIs" com Favoritos e Todos os FIIs. */
export type NavGroup = {
    title: string;
    icon: LucideIcon;
    items: NavItem[];
};

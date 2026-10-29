import { Link, useRouterState } from "@tanstack/react-router";
import { BellRing, LayoutGrid, Palette, Send } from "lucide-react";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";

const items = [
  { title: "Painel", url: "/", icon: LayoutGrid },
  { title: "Avisos", url: "/alertas", icon: BellRing },
  { title: "Guia de estilo", url: "/estilo", icon: Palette },
] as const;

export function AppSidebar() {
  const path = useRouterState({ select: (r) => r.location.pathname });
  return (
    <Sidebar collapsible="icon">
      <SidebarHeader>
        <div className="flex items-center gap-2 px-1 py-2">
          <div className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground">
            <BellRing className="size-4" />
          </div>
          <span className="font-display text-base font-semibold group-data-[collapsible=icon]:hidden">
            Me Avisa Aí
          </span>
        </div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Menu</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {items.map((i) => (
                <SidebarMenuItem key={i.url}>
                  <SidebarMenuButton asChild isActive={i.url === "/" ? path === "/" || path.startsWith("/fundo") : path === i.url} tooltip={i.title}>
                    <Link to={i.url}>
                      <i.icon />
                      <span>{i.title}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter>
        <div className="flex items-center gap-2 rounded-lg bg-signal-soft px-2 py-2 text-xs text-signal group-data-[collapsible=icon]:hidden">
          <Send className="size-3.5" />
          Telegram conectado
        </div>
      </SidebarFooter>
    </Sidebar>
  );
}

import { Icon } from '@/components/icon';
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { ExternalLink } from 'lucide-react';

/**
 * Enlaces externos al pie del menú.
 *
 * Todos abren en una pestaña nueva, y eso hay que anunciarlo: un cambio de
 * contexto que no se avisa desorienta, sobre todo con lector de pantalla.
 */
export function NavFooter({
    items,
    className,
    ...props
}: React.ComponentPropsWithoutRef<typeof SidebarGroup> & {
    items: NavItem[];
}) {
    return (
        <SidebarGroup {...props} className={`group-data-[collapsible=icon]:p-0 ${className || ''}`}>
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                tooltip={item.title}
                                className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                            >
                                <a
                                    href={item.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    // El texto visible no dice que abre fuera; el icono
                                    // lo sugiere pero no lo lee nadie.
                                    aria-label={`${item.title} (se abre en una pestaña nueva)`}
                                >
                                    {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" aria-hidden="true" />}
                                    <span className="flex-1 truncate">{item.title}</span>
                                    <ExternalLink className="size-3.5 shrink-0 opacity-60" aria-hidden="true" />
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

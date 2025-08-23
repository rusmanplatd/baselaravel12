import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronRight } from 'lucide-react';
import { usePermissions } from '@/hooks/use-permissions';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const { canAccess } = usePermissions();
    
    // Filter items based on permissions
    const visibleItems = items.filter(item => canAccess(item));
    
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {visibleItems.map((item) => {
                    // Filter sub-items based on permissions
                    const visibleSubItems = item.items?.filter(subItem => canAccess(subItem)) || [];
                    
                    // Skip parent items with sub-items if no sub-items are visible
                    if (item.items && visibleSubItems.length === 0) {
                        return null;
                    }
                    
                    return (
                        <Collapsible key={item.title} asChild defaultOpen={visibleSubItems.some(subItem => page.url.startsWith(subItem.href || ''))}>
                            <SidebarMenuItem>
                                {item.items ? (
                                    <>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton tooltip={{ children: item.title }}>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {visibleSubItems.map((subItem) => (
                                                    <SidebarMenuSubItem key={subItem.title}>
                                                        <SidebarMenuSubButton asChild isActive={Boolean(subItem.href && page.url.startsWith(subItem.href))}>
                                                            <Link href={subItem.href || '#'} prefetch>
                                                                <span>{subItem.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </>
                                ) : (
                                    <SidebarMenuButton asChild isActive={Boolean(item.href && page.url.startsWith(item.href))} tooltip={{ children: item.title }}>
                                        <Link href={item.href || '#'} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                )}
                            </SidebarMenuItem>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavbarUser } from '@/components/navbar-user';
import { NotificationPanel } from '@/components/ui/notification-panel';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useNotifications } from '@/hooks/use-notifications';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { notifications, markAsRead, markAllAsRead, removeNotification } = useNotifications();
    
    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                <NotificationPanel
                    notifications={notifications}
                    onMarkAsRead={markAsRead}
                    onMarkAllAsRead={markAllAsRead}
                    onRemove={removeNotification}
                />
                <NavbarUser />
            </div>
        </header>
    );
}

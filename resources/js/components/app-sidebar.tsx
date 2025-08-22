import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Building, Users, TrendingUp, Briefcase, Network, UserCheck, Target } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Organizations',
        icon: Building,
        items: [
            {
                title: 'All Organizations',
                href: '/organizations',
            },
            {
                title: 'Hierarchy View',
                href: '/organizations-hierarchy',
            },
        ],
    },
    {
        title: 'Organizational Units',
        icon: Network,
        items: [
            {
                title: 'All Units',
                href: '/organization-units',
            },
            {
                title: 'Governance Units',
                href: '/organization-units-governance',
            },
            {
                title: 'Operational Units',
                href: '/organization-units-operational',
            },
        ],
    },
    {
        title: 'Positions',
        href: '/organization-positions',
        icon: Target,
    },
    {
        title: 'Memberships',
        icon: UserCheck,
        items: [
            {
                title: 'All Memberships',
                href: '/organization-memberships',
            },
            {
                title: 'Board Members',
                href: '/board-members',
            },
            {
                title: 'Executives',
                href: '/executives',
            },
        ],
    },
    {
        title: 'Legacy',
        icon: Shield,
        items: [
            {
                title: 'Departments',
                href: '/departments',
            },
            {
                title: 'Job Levels',
                href: '/job-levels',
            },
            {
                title: 'Job Positions',
                href: '/job-positions',
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

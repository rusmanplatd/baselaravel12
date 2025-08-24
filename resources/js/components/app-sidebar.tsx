import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Building, Users, Network, UserCheck, Target, Shield, KeyRound, Award, MessageCircle } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Chat',
        href: '/chat',
        icon: MessageCircle,
    },
    {
        title: 'Organizations',
        icon: Building,
        permission: 'organization.view',
        items: [
            {
                title: 'All Organizations',
                href: '/organizations',
                permission: 'organization.view',
            },
            {
                title: 'Hierarchy View',
                href: '/organizations-hierarchy',
                permission: 'organization.hierarchy.view',
            },
        ],
    },
    {
        title: 'Organizational Units',
        icon: Network,
        permission: 'unit.view',
        items: [
            {
                title: 'All Units',
                href: '/organization-units',
                permission: 'unit.view',
            },
            {
                title: 'Governance Units',
                href: '/organization-units-governance',
                permission: 'unit.view',
            },
            {
                title: 'Operational Units',
                href: '/organization-units-operational',
                permission: 'unit.view',
            },
        ],
    },
    {
        title: 'Position Levels',
        href: '/organization-position-levels',
        icon: Award,
        permission: 'position.level.view',
    },
    {
        title: 'Positions',
        href: '/organization-positions',
        icon: Target,
        permission: 'position.view',
    },
    {
        title: 'Memberships',
        icon: UserCheck,
        permission: 'membership.view',
        items: [
            {
                title: 'All Memberships',
                href: '/organization-memberships',
                permission: 'membership.view',
            },
            {
                title: 'Board Members',
                href: '/board-members',
                permission: 'membership.view',
            },
            {
                title: 'Executives',
                href: '/executives',
                permission: 'membership.view',
            },
        ],
    },
    {
        title: 'User Management',
        icon: Users,
        permission: 'user.view',
        items: [
            {
                title: 'All Users',
                href: '/users',
                permission: 'user.view',
            },
        ],
    },
    {
        title: 'Access Control',
        icon: KeyRound,
        permissions: ['view roles', 'view permissions'],
        items: [
            {
                title: 'Roles',
                href: '/roles',
                permission: 'view roles',
            },
            {
                title: 'Permissions',
                href: '/permissions',
                permission: 'view permissions',
            },
        ],
    },
    {
        title: 'OAuth',
        icon: Shield,
        permission: 'oauth.client.view',
        items: [
            {
                title: 'OAuth Clients',
                href: '/oauth/clients',
                permission: 'oauth.client.view',
            },
            {
                title: 'Analytics',
                href: '/oauth/analytics',
                permission: 'oauth.analytics.view',
            },
            {
                title: 'Test Client',
                href: '/oauth/test',
                permission: 'oauth.client.view',
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

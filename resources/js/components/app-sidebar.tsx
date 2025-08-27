import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Building, Users, Network, UserCheck, Target, Shield, KeyRound, Award, MessageCircle, Activity } from 'lucide-react';
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
        permission: 'org:read',
        items: [
            {
                title: 'All Organizations',
                href: '/organizations',
                permission: 'org:read',
            },
            {
                title: 'Hierarchy View',
                href: '/organizations-hierarchy',
                permission: 'org:read',
            },
        ],
    },
    {
        title: 'Organizational Units',
        icon: Network,
        permission: 'org_unit:read',
        items: [
            {
                title: 'All Units',
                href: '/organization-units',
                permission: 'org_unit:read',
            },
            {
                title: 'Governance Units',
                href: '/organization-units-governance',
                permission: 'org_unit:read',
            },
            {
                title: 'Operational Units',
                href: '/organization-units-operational',
                permission: 'org_unit:read',
            },
        ],
    },
    {
        title: 'Position Levels',
        href: '/organization-position-levels',
        icon: Award,
        permission: 'org_position:read',
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
                permission: 'org_member:read',
            },
            {
                title: 'Board Members',
                href: '/board-members',
                permission: 'org_member:read',
            },
            {
                title: 'Executives',
                href: '/executives',
                permission: 'org_member:read',
            },
        ],
    },
    {
        title: 'User Management',
        icon: Users,
        permission: 'user:read',
        items: [
            {
                title: 'All Users',
                href: '/users',
                permission: 'user:read',
            },
        ],
    },
    {
        title: 'Access Control',
        icon: KeyRound,
        permissions: ['role:read', 'permission:read'],
        items: [
            {
                title: 'Roles',
                href: '/roles',
                permission: 'role:read',
            },
            {
                title: 'Permissions',
                href: '/permissions',
                permission: 'permission:read',
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
    {
        title: 'Activity Log',
        href: '/activity-log',
        icon: Activity,
        permissions: ['audit_log:read', 'audit_log:admin'],
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

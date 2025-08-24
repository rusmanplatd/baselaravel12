import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
    permissions: string[];
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href?: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
    permission?: string;
    role?: string;
    permissions?: string[];
    roles?: string[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}

// Security & Session Management Types
export interface TrustedDevice {
    id: string;
    device_name: string;
    device_type: 'desktop' | 'mobile' | 'tablet';
    browser: string;
    platform: string;
    ip_address: string;
    location: string | null;
    last_used_at: string;
    expires_at: string;
    is_active: boolean;
    is_current: boolean;
}

export interface UserSession {
    id: string;
    ip_address: string;
    browser: string;
    platform: string;
    device_type: 'desktop' | 'mobile' | 'tablet';
    location: string | null;
    last_activity: string;
    login_at: string;
    is_active: boolean;
    is_current: boolean;
    trusted_device: {
        id: string;
        device_name: string;
        device_type: string;
    } | null;
}

export interface SessionStats {
    active_sessions: number;
    total_sessions: number;
    trusted_device_sessions: number;
    recent_logins: number;
    unique_ips: number;
    device_types: Record<string, number>;
    current_session_id: string;
}

export interface SecurityAlert {
    type: 'multiple_locations' | 'multiple_sessions' | 'untrusted_devices';
    message: string;
    data: Record<string, unknown>;
}

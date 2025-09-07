import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Echo: Echo;
        Pusher: typeof Pusher;
    }
}

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
    username?: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Organization {
    id: string;
    name: string;
    organization_code: string;
    organization_type: string;
    description?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface Activity {
    id: string;
    log_name: string;
    description: string;
    subject_type: string | null;
    subject_id: string | null;
    causer_type: string | null;
    causer_id: string | null;
    event: string | null;
    properties: Record<string, any>;
    batch_uuid: string | null;
    organization_id: string | null;
    tenant_id: string | null;
    created_at: string;
    updated_at: string;
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

// Generic PageProps interface for Inertia.js pages
export interface PageProps<T = Record<string, unknown>> {
    auth: Auth;
    ziggy?: Config & { location: string };
    flash?: {
        message?: string;
        error?: string;
        success?: string;
    };
    errors?: Record<string, string>;
    [key: string]: unknown;
}

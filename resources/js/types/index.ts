import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
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
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    /** Absolute URL of the default social-share card, built from APP_URL server-side. */
    ogImage: string;
    /**
     * Whether WhatsApp can actually deliver a sign-in code. False means the OTP door
     * is offered nowhere and the phone form is replaced by a "use email" notice —
     * the log driver reports sends as successful, so an unguarded flow would ask for
     * a code that went to the server log.
     */
    whatsappAuth: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    /**
     * The signed-in editor's resolved section→action grants, shared on every
     * page by HandleInertiaRequests. Null for admins (full access) and for
     * signed-out / customer requests. Read it through `useCan()` rather than
     * poking at it directly, so client gating mirrors the server's hasPermission.
     */
    permissions?: Record<string, Record<string, boolean>> | null;
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
    /** True only when a Google OAuth client is configured; gates the button. */
    googleAuth: boolean;
    /**
     * One-shot messages from the last request. `id` is a per-response nonce so an
     * identical message twice in a row still re-triggers value-compared effects.
     */
    flash: { success?: string | null; error?: string | null; id?: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    /** Nullable in the DB under the OTP identity model — a WhatsApp signup has only a phone. */
    name: string;
    email: string;
    /**
     * Declared explicitly even though the index signature below would cover it:
     * through that signature it resolves to `unknown`, which silently poisons any
     * expression that uses it (`user?.phone ?? ''` becomes `{}`, not `string`).
     */
    phone?: string | null;
    avatar?: string;
    /** 'admin' | 'editor' | 'customer'. Drives admin access; see useCan(). */
    role?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

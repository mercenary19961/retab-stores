import { usePage } from '@inertiajs/react';

import { type SharedData } from '@/types';

/**
 * Client-side permission check for the admin panel, mirroring the server's
 * App\Models\User::hasPermission exactly.
 *
 * 🔑 This is UX, never a security boundary. Its job is to stop rendering a
 * control the signed-in editor would only get a 403 from — the button they can
 * see but can't use. Every mutating route is still guarded server-side by the
 * `permission:` middleware, which is the real enforcement; hiding the control
 * just means they never reach the wall in the first place.
 *
 * Semantics, matching the server:
 *   - admin            → everything (permissions come through as null)
 *   - editor           → the resolved section→action map they were granted
 *   - anyone else       → nothing
 *
 * The map shared to the client is ALREADY resolved (stored grants merged over
 * DEFAULTS in resolvedPermissions()), so there is no defaults fallback to redo
 * here — an absent action simply reads as denied, exactly as it renders.
 *
 * Usage:  const can = useCan();  … {can('products.delete') && <DeleteButton />}
 */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth?.user?.role === 'admin';
    const permissions = auth?.permissions ?? null;

    return (permission: string): boolean => {
        if (isAdmin) {
            return true;
        }

        const [section, action] = permission.split('.');

        return Boolean(permissions?.[section]?.[action]);
    };
}

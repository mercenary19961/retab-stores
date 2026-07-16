import { useTranslation } from 'react-i18next';
import adminI18n from './admin';

/**
 * Translation hook bound explicitly to the admin i18next instance.
 *
 * Admin PAGE components render <AdminLayout> as a child, so a plain
 * useTranslation() called in a page body sits ABOVE the admin I18nextProvider
 * (which lives inside AdminLayout) and would resolve to the storefront
 * Arabic-session instance — or the fragile module-global default, whichever
 * i18n `init()` ran last. Passing { i18n: adminI18n } binds to the admin
 * instance regardless of tree position, and still re-renders when the admin
 * language is toggled (AdminShell calls adminI18n.changeLanguage()).
 *
 * Use this in every admin page/component that needs translation.
 */
export function useAdminT() {
    return useTranslation(undefined, { i18n: adminI18n });
}

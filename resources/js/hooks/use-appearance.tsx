import { useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

// Dark mode is ADMIN-ONLY in this project: the admin scopes it to its own
// `.admin-shell dark` wrapper. The customer-facing surfaces (storefront + auth)
// are light-only, so the document root must NEVER carry `.dark` — otherwise a
// visitor whose OS is set to dark mode gets a broken (black bg, invisible text,
// inverted buttons) storefront/login. We keep the appearance API surface so the
// starter-kit settings page still imports cleanly, but pin the public app to light.
const forceLight = () => {
    if (typeof document !== 'undefined') {
        document.documentElement.classList.remove('dark');
    }
};

export function initializeTheme() {
    forceLight();
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('light');

    // Reflects the setting's selection in the UI but never darkens the public
    // app — the document root stays light regardless of the chosen mode.
    const updateAppearance = (mode: Appearance) => {
        setAppearance(mode);
        forceLight();
    };

    useEffect(() => {
        forceLight();
    }, []);

    return { appearance, updateAppearance };
}

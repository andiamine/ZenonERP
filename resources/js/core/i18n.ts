import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

/**
 * Shell i18n: one 'shell' namespace with inline English resources. Modules bring
 * their own namespaces later via ZenonModule.locales (ns = module id, CLAUDE.md §7):
 * the loader will call i18next.addResourceBundle(lang, module.id, resources).
 */
const SUPPORTED_LOCALES = ['en'];

export async function initI18n(): Promise<void> {
    await i18next.use(initReactI18next).init({
        lng: 'en',
        fallbackLng: 'en',
        defaultNS: 'shell',
        ns: ['shell'],
        interpolation: { escapeValue: false }, // React already escapes
        resources: {
            en: {
                shell: {
                    appName: 'ZenonERP',
                    login: {
                        title: 'Sign in',
                        description: 'Enter your credentials to access your workspace.',
                        email: 'Email',
                        password: 'Password',
                        submit: 'Sign in',
                        submitting: 'Signing in…',
                        failed: 'Sign-in failed',
                    },
                    nav: {
                        dashboard: 'Dashboard',
                        logout: 'Sign out',
                    },
                    dashboard: {
                        title: 'Dashboard',
                        empty: 'No widgets enabled yet — enable modules to populate this dashboard.',
                    },
                    errors: {
                        forbiddenTitle: 'Access denied',
                        forbiddenBody: "You don't have permission to view this page.",
                        notFoundTitle: 'Page not found',
                        notFoundBody: 'The page you are looking for does not exist or is not enabled for this workspace.',
                        bootTitle: 'Something went wrong',
                        bootBody: 'ZenonERP could not start. Check your connection and try again.',
                        retry: 'Retry',
                        backHome: 'Back to dashboard',
                    },
                    central: {
                        title: 'ZenonERP',
                        body: 'This is the ZenonERP platform domain. Tenant workspaces live on their own subdomain — the platform admin UI arrives in a later milestone.',
                    },
                },
            },
        },
    });
}

/** Switch to the tenant's locale from bootstrap; unknown locales keep the fallback. */
export async function applyLocale(locale: string): Promise<void> {
    if (SUPPORTED_LOCALES.includes(locale) && i18next.language !== locale) {
        await i18next.changeLanguage(locale);
    }
}

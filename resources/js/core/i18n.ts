import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import type { ZenonModule } from './moduleTypes';

/**
 * Shell i18n: one 'shell' namespace with inline resources. Modules bring their own
 * namespaces via ZenonModule.locales (ns = module id, CLAUDE.md §7) — see
 * registerModuleLocales below. Modules reuse shell strings cross-namespace, e.g.
 * t('shell:common.save'), rather than redefining them.
 */
const SUPPORTED_LOCALES = ['en', 'fr'];

const resources = {
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
                collapse: 'Collapse',
                expand: 'Expand',
            },
            common: {
                save: 'Save',
                cancel: 'Cancel',
                create: 'Create',
                edit: 'Edit',
                delete: 'Delete',
                search: 'Search',
                actions: 'Actions',
                loading: 'Loading…',
                confirmTitle: 'Confirm',
                confirmDeleteBody: 'This action cannot be undone. Are you sure you want to delete this item?',
                saved: 'Saved',
                none: 'None',
            },
            company: {
                switcher: 'Company',
            },
            dashboard: {
                title: 'Dashboard',
                empty: 'No widgets enabled yet — enable modules to populate this dashboard.',
            },
            remoteModules: {
                noticeTitle: 'Some add-ons could not be loaded',
                incompatible: 'Add-on "{{id}}" is not compatible with this platform version',
                load_failed: 'Add-on "{{id}}" failed to load',
                dismiss: 'Dismiss',
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
    fr: {
        shell: {
            appName: 'ZenonERP',
            login: {
                title: 'Connexion',
                description: 'Saisissez vos identifiants pour accéder à votre espace de travail.',
                email: 'E-mail',
                password: 'Mot de passe',
                submit: 'Se connecter',
                submitting: 'Connexion…',
                failed: 'Échec de la connexion',
            },
            nav: {
                dashboard: 'Tableau de bord',
                logout: 'Se déconnecter',
                collapse: 'Réduire',
                expand: 'Développer',
            },
            common: {
                save: 'Enregistrer',
                cancel: 'Annuler',
                create: 'Créer',
                edit: 'Modifier',
                delete: 'Supprimer',
                search: 'Rechercher',
                actions: 'Actions',
                loading: 'Chargement…',
                confirmTitle: 'Confirmer',
                confirmDeleteBody: 'Cette action est irréversible. Voulez-vous vraiment supprimer cet élément ?',
                saved: 'Enregistré',
                none: 'Aucun',
            },
            company: {
                switcher: 'Société',
            },
            dashboard: {
                title: 'Tableau de bord',
                empty: 'Aucun widget activé pour le moment — activez des modules pour remplir ce tableau de bord.',
            },
            remoteModules: {
                noticeTitle: "Certains modules complémentaires n'ont pas pu être chargés",
                incompatible: "Le module \"{{id}}\" n'est pas compatible avec cette version de la plateforme",
                load_failed: "Le module \"{{id}}\" n'a pas pu être chargé",
                dismiss: 'Ignorer',
            },
            errors: {
                forbiddenTitle: 'Accès refusé',
                forbiddenBody: "Vous n'avez pas l'autorisation de consulter cette page.",
                notFoundTitle: 'Page introuvable',
                notFoundBody: "La page que vous recherchez n'existe pas ou n'est pas activée pour cet espace de travail.",
                bootTitle: "Une erreur s'est produite",
                bootBody: "ZenonERP n'a pas pu démarrer. Vérifiez votre connexion et réessayez.",
                retry: 'Réessayer',
                backHome: 'Retour au tableau de bord',
            },
            central: {
                title: 'ZenonERP',
                body: "Ceci est le domaine de la plateforme ZenonERP. Les espaces de travail des locataires se trouvent sur leur propre sous-domaine — l'interface d'administration de la plateforme arrivera dans une version ultérieure.",
            },
        },
    },
};

export async function initI18n(): Promise<void> {
    await i18next.use(initReactI18next).init({
        lng: 'en',
        fallbackLng: 'en',
        defaultNS: 'shell',
        ns: ['shell'],
        interpolation: { escapeValue: false }, // React already escapes
        resources,
    });
}

/** Switch to the tenant's locale from bootstrap; unknown locales keep the fallback. */
export async function applyLocale(locale: string): Promise<void> {
    if (SUPPORTED_LOCALES.includes(locale) && i18next.language !== locale) {
        await i18next.changeLanguage(locale);
    }
}

/**
 * Registers a loaded module's locale bundles under its own namespace (ns = module id,
 * CLAUDE.md §7). Loads the CURRENT language AND the 'en' fallback — main.tsx runs
 * applyLocale BEFORE loadEnabledModules, so i18next.language is already final here; when
 * it is 'fr' we still register 'en' so untranslated module keys fall back cleanly. The
 * Set dedupes when the current language already is 'en'.
 */
export async function registerModuleLocales(module: ZenonModule): Promise<void> {
    if (module.locales === undefined) {
        return;
    }

    for (const lang of new Set([i18next.language, 'en'])) {
        const load = module.locales[lang];
        if (load === undefined) {
            continue;
        }

        i18next.addResourceBundle(lang, module.id, await load(), true, true);
    }
}

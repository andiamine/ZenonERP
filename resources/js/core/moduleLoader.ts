import { moduleRegistry, registryHash } from '@generated/module-registry';
import { registerModuleLocales } from './i18n';
import type { BootstrapData, ZenonModule } from './moduleTypes';

/**
 * Loads the frontends of the modules enabled for the current tenant (CLAUDE.md §7).
 * Phase 4 ships the seams; the registry is empty until Phase 5's first real module:
 *   - unknown module id → warn + skip (backend enabled something this build lacks)
 *   - registry hash mismatch → server assets are newer than this bundle → prompt reload
 *   - remote modules (bootstrap.remote_modules) → Phase 7 runtime MF loading; skip + warn
 */
export async function loadEnabledModules(boot: BootstrapData): Promise<ZenonModule[]> {
    if (boot.registryHash !== null && boot.registryHash !== registryHash) {
        if (window.confirm('A new version of ZenonERP is available. Reload now?')) {
            window.location.reload();
        }
    }

    for (const remote of boot.remote_modules) {
        console.warn(`[zenon] remote module "${remote.id}" skipped — runtime federation loading lands in Phase 7`);
    }

    const loaded: ZenonModule[] = [];

    for (const id of boot.enabled_modules) {
        const entry = moduleRegistry[id];

        if (entry === undefined) {
            console.warn(`[zenon] unknown module id "${id}" — skipped (not in this build's registry)`);
            continue;
        }

        const module = (await entry.load()).default;
        await registerModuleLocales(module);
        loaded.push(module);
    }

    return loaded;
}

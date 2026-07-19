import { moduleRegistry, registryHash } from '@generated/module-registry';
import { registerModuleLocales } from './i18n';
import type { BootstrapData, ZenonModule } from './moduleTypes';
import { loadRemoteModule, platformSatisfies, withTimeout } from './remoteModules';
import { reportRemoteFailure } from './store';

/**
 * Loads the frontends of the modules enabled for the current tenant (CLAUDE.md §7, §2).
 * Two transports behind one contract:
 *   - bundled (first-party) → the generated registry. Unknown id → warn + skip; a registry
 *     hash mismatch means server assets are newer than this bundle → prompt reload.
 *   - remote (third-party addon, boot.remote_modules) → Phase 7 runtime Module Federation:
 *     platform-compat check → registerRemotes/loadRemote, EACH wrapped so any failure (404,
 *     throw, timeout, bad export, platform mismatch, double id) isolates to a console.warn +
 *     an admin banner notice and never breaks boot. Remotes append AFTER bundled → stable nav.
 */
export async function loadEnabledModules(boot: BootstrapData): Promise<ZenonModule[]> {
    if (boot.registryHash !== null && boot.registryHash !== registryHash) {
        if (window.confirm('A new version of ZenonERP is available. Reload now?')) {
            window.location.reload();
        }
    }

    const loaded: ZenonModule[] = [];

    for (const id of boot.enabled_modules) {
        const entry = moduleRegistry[id];

        if (entry === undefined) {
            console.warn(`[zenon] unknown module id "${id}" — skipped (not in this build's registry)`);
            continue;
        }

        // The generated registry is bundled-only by construction (remote refs arrive via
        // boot.remote_modules, loaded below); this guard only narrows the widened union.
        if (entry.source !== 'bundled') {
            continue;
        }

        const module = (await entry.load()).default;
        await registerModuleLocales(module);
        loaded.push(module);
    }

    // Remote addons (Phase 7). Concurrent + fully isolated: one broken remote never affects
    // another or the bundled modules. `loadedIds` guards the (in-practice disjoint) case of an
    // id present in both the registry and boot.remote_modules — skip the remote, warn only.
    const loadedIds = new Set(loaded.map((module) => module.id));

    const remoteResults = await Promise.allSettled(
        boot.remote_modules.map(async (ref): Promise<ZenonModule | null> => {
            if (loadedIds.has(ref.id)) {
                console.warn(`[zenon] remote module "${ref.id}" skipped — id already loaded as a bundled module`);
                return null;
            }

            if (!platformSatisfies(ref.platform, boot.platform_version)) {
                reportRemoteFailure(
                    ref.id,
                    'incompatible',
                    `addon requires platform ${ref.platform}, host is ${boot.platform_version}`,
                );
                return null;
            }

            const module = await withTimeout(loadRemoteModule(ref), 10_000);
            await registerModuleLocales(module);

            return module;
        }),
    );

    for (const [i, ref] of boot.remote_modules.entries()) {
        const result = remoteResults[i];
        if (result === undefined) {
            continue;
        }

        if (result.status === 'fulfilled') {
            if (result.value !== null) {
                loaded.push(result.value);
            }
        } else {
            reportRemoteFailure(ref.id, 'load_failed', String(result.reason));
        }
    }

    return loaded;
}

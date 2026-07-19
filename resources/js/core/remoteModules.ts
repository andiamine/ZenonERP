import { getInstance } from '@module-federation/runtime';
import type { RemoteModuleRef, ZenonModule } from './moduleTypes';

/**
 * Runtime Module Federation loading for third-party addon frontends (CLAUDE.md §7, §2
 * hybrid loading). Bundled first-party modules never touch this file — they load from the
 * generated registry. These helpers stay pure/isolated so moduleLoader can wrap each remote
 * in total failure isolation (a broken addon must never break boot).
 */

/**
 * Derives the Module Federation container name for a module alias. The SAME contract lives
 * in @zenon/module-kit's index.mjs (the addon build preset) — keep the two in sync, or a
 * remote's registered name won't match the container name it built into `remoteEntry.js`.
 */
export function remoteNameForAlias(alias: string): string {
    if (!/^[a-z][a-z0-9_]*$/.test(alias)) {
        throw new Error(`invalid module alias "${alias}"`);
    }

    return `zenon_addon_${alias}`;
}

type ParsedVersion = { major: number; minor: number; patch: number };

/** Strict `X`, `X.Y`, or `X.Y.Z` (numeric only); missing parts default to 0. `null` = malformed. */
function parseVersion(version: string): ParsedVersion | null {
    const segments = version.split('.');
    if (segments.length < 1 || segments.length > 3) {
        return null;
    }

    const nums: number[] = [];
    for (const segment of segments) {
        if (!/^\d+$/.test(segment)) {
            return null;
        }
        nums.push(Number.parseInt(segment, 10));
    }

    return { major: nums[0] ?? 0, minor: nums[1] ?? 0, patch: nums[2] ?? 0 };
}

/** Parses the `MAJ[.MIN[.PAT]]` body of a constraint; absent parts stay `undefined`. `null` = malformed. */
function parseConstraintParts(body: string): [number, number | undefined, number | undefined] | null {
    const segments = body.split('.');
    if (segments.length < 1 || segments.length > 3) {
        return null;
    }

    const nums: [number | undefined, number | undefined, number | undefined] = [undefined, undefined, undefined];
    for (const [i, segment] of segments.entries()) {
        if (!/^\d+$/.test(segment)) {
            return null;
        }
        nums[i] = Number.parseInt(segment, 10);
    }

    const major = nums[0];
    if (major === undefined) {
        return null;
    }

    return [major, nums[1], nums[2]];
}

/**
 * Hand-rolled platform-compatibility matcher — the platform contract grammar
 * (`^MAJOR[.MINOR[.PATCH]]`, bare `MAJOR[.MINOR[.PATCH]]`, or `*`):
 *   - `*`                       → always true.
 *   - `^MAJ[.MIN[.PAT]]`        → caret: same major; higher minor ok; equal minor needs
 *                                 patch >= given (when a patch is given).
 *   - bare `MAJ[.MIN[.PAT]]`    → exact prefix match on the parts the constraint gives.
 *   - anything else / malformed → false (fail closed; the caller reports 'incompatible').
 * Authoritative install-time validation is composer/semver server-side; this client check
 * only gates mounting. The caret-on-0.x nuance is deliberately ignored (platform starts at
 * 1.0.0). A malformed host version also fails closed.
 */
export function platformSatisfies(constraint: string, version: string): boolean {
    if (constraint === '*') {
        return true;
    }

    const v = parseVersion(version);
    if (v === null) {
        return false;
    }

    const caret = constraint.startsWith('^');
    const parts = parseConstraintParts(caret ? constraint.slice(1) : constraint);
    if (parts === null) {
        return false;
    }

    const [cMajor, cMinor, cPatch] = parts;

    if (caret) {
        if (v.major !== cMajor) {
            return false;
        }
        if (cMinor === undefined || v.minor > cMinor) {
            return true;
        }
        if (v.minor < cMinor) {
            return false;
        }

        return cPatch === undefined || v.patch >= cPatch;
    }

    // Bare version — exact prefix match on the parts the constraint supplies.
    if (v.major !== cMajor) {
        return false;
    }
    if (cMinor !== undefined && v.minor !== cMinor) {
        return false;
    }

    return cPatch === undefined || v.patch === cPatch;
}

/**
 * Registers a remote against the live MF runtime and loads its default-exported ZenonModule.
 * The runtime instance is created by the MF vite plugin's hostInit BEFORE main.tsx runs
 * (hostInitInjectLocation: 'entry'), and @module-federation/runtime@2.8.0 is the same copy
 * the plugin uses (deduped), so getInstance() returns that live instance here.
 *
 * Throws on any failure (uninitialized runtime, load error, non-matching export); the caller
 * (moduleLoader) turns every throw into an isolated 'load_failed' notice.
 */
export async function loadRemoteModule(ref: RemoteModuleRef): Promise<ZenonModule> {
    const instance = getInstance(); // created by the MF plugin's hostInit before main.tsx ran
    if (instance == null) {
        throw new Error('module federation runtime not initialized');
    }

    instance.registerRemotes([{ name: remoteNameForAlias(ref.id), alias: ref.id, entry: ref.url, type: 'module' }]);

    const container = await instance.loadRemote<{ default: ZenonModule }>(`${ref.id}/module`);
    const module = container?.default;
    if (module === undefined || module.id !== ref.id) {
        throw new Error(`remote "${ref.id}" did not export a matching ZenonModule`);
    }

    return module;

    // FALLBACK — manual container protocol, the one-line-flip seam if registerRemotes/
    // loadRemote misbehaves at integration (upstream module-federation-examples#4404). The
    // emitted container is ESM (Task 6), so a manual mount is:
    //   const container = (await import(/* @vite-ignore */ ref.url)) as RemoteEntryExports;
    //   await container.init(instance.shareScopeMap['default'] ?? {}, []);
    //   const factory = await container.get('./module');
    //   const module = (factory() as { default: ZenonModule }).default;
    // Keep this documented; do NOT delete.
}

/**
 * Promise.race against a rejecting timer so a remote that never resolves (hung network,
 * silent stall) cannot wedge boot. The timer is cleared on settle either way so no stray
 * rejection fires after the promise wins.
 */
export function withTimeout<T>(promise: Promise<T>, ms: number): Promise<T> {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const timeout = new Promise<never>((_resolve, reject) => {
        timer = setTimeout(() => {
            reject(new Error(`timed out after ${ms}ms`));
        }, ms);
    });

    return Promise.race([promise, timeout]).finally(() => {
        clearTimeout(timer);
    });
}

import type { UserConfig } from 'vite';

export interface AddonConfigOptions {
    alias: string;
    entry?: string;
}

export declare function defineAddonConfig(options: AddonConfigOptions): UserConfig;
export declare function remoteNameForAlias(alias: string): string;

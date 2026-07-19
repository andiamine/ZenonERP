#!/usr/bin/env node
import { build } from 'vite';

const [command, ...rest] = process.argv.slice(2);
const watch = rest.includes('--watch');

function usage() {
    console.error('Usage: zenon-module build [--watch]');
}

async function main() {
    if (command === undefined || command === 'build') {
        // Vite auto-loads the addon's vite.config.ts from root.
        await build({
            root: process.cwd(),
            ...(watch ? { build: { watch: {} } } : {}),
        });
        return;
    }
    usage();
    process.exit(1);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});

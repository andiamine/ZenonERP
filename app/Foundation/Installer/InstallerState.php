<?php

namespace App\Foundation\Installer;

use Illuminate\Support\Facades\File;

/**
 * The single source of truth for "has the standalone wizard already provisioned this
 * install" (CLAUDE.md §7/§12 Phase 8) — a lock FILE, not a DB row, because a fresh
 * standalone extract has no database to ask yet. Location defaults under storage/
 * (survives an update zip by construction: release packaging never touches storage/,
 * CLAUDE.md's Phase 8 exclude-rule notes) but is config-indirected
 * (`zenon.installer.lock_path`) so tests never write a real installed.lock.
 */
final class InstallerState
{
    public function isInstalled(): bool
    {
        return File::exists($this->lockPath());
    }

    public function markInstalled(): void
    {
        $path = $this->lockPath();

        File::ensureDirectoryExists(dirname($path));

        File::put($path, json_encode([
            'installed_at' => now()->toIso8601String(),
            'app_version' => config('zenon.platform_version'),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function lockPath(): string
    {
        return (string) config('zenon.installer.lock_path');
    }
}

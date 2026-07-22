<?php

namespace App\Foundation\Installer;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

/**
 * Preflight checks the standalone installer wizard runs before touching anything
 * (CLAUDE.md §7 Phase 8 Task 6): PHP version + required extensions (blocking — the app
 * cannot run at all without them), writability of the directories the wizard itself
 * must write into next (.env's directory, storage/, bootstrap/cache/ — also blocking),
 * and two soft signals that must never block the wizard: modules/thirdparty writability
 * (only needed for the addon-upload UI, deferred to Phase 9/M2) and
 * public/build/manifest.json (a dev/test walkthrough of the wizard may legitimately run
 * against an unbuilt SPA). As a side effect, clears a stale
 * bootstrap/cache/config.php left over from a copied dev environment or a previous
 * install attempt — every env() read in this pipeline must see the real .env, never a
 * frozen config snapshot.
 */
final class RequirementsCheck
{
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql', 'mbstring', 'openssl', 'curl', 'dom', 'fileinfo', 'xml', 'zip',
    ];

    /**
     * @return list<array{key: string, label: string, status: string, detail: string|null}>
     */
    public function run(): array
    {
        $items = [$this->phpVersion()];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $items[] = $this->extension($extension);
        }

        $items[] = $this->writable('env', dirname($this->envPath()), fail: true);
        $items[] = $this->writable('storage', storage_path(), fail: true);
        $items[] = $this->writable('bootstrap_cache', base_path('bootstrap/cache'), fail: true);
        $items[] = $this->writable('modules_thirdparty', (string) config('zenon.thirdparty_path'), fail: false);
        $items[] = $this->buildManifest();
        $items[] = $this->clearStaleConfigCache();

        return $items;
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string|null}
     */
    private function phpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');

        return $this->item('php_version', 'PHP >= 8.3', $ok ? 'pass' : 'fail', PHP_VERSION);
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string|null}
     */
    private function extension(string $name): array
    {
        $ok = extension_loaded($name);

        return $this->item("ext_{$name}", "PHP extension: {$name}", $ok ? 'pass' : 'fail');
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string|null}
     */
    private function writable(string $key, string $path, bool $fail): array
    {
        $ok = file_exists($path) && is_writable($path);

        return $this->item("writable_{$key}", "Writable: {$path}", $ok ? 'pass' : ($fail ? 'fail' : 'warn'), $path);
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string|null}
     */
    private function buildManifest(): array
    {
        $path = public_path('build/manifest.json');
        $ok = is_file($path);

        return $this->item('build_manifest', 'Prebuilt SPA assets (public/build/manifest.json)', $ok ? 'pass' : 'warn', $path);
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string|null}
     */
    private function clearStaleConfigCache(): array
    {
        $cachePath = base_path('bootstrap/cache/config.php');

        if (! is_file($cachePath)) {
            return $this->item('config_cache', 'Stale config cache', 'pass', 'none found');
        }

        Artisan::call('config:clear');

        return $this->item('config_cache', 'Stale config cache', 'pass', 'cleared');
    }

    private function envPath(): string
    {
        $configured = config('zenon.installer.env_path');

        return $configured !== null ? (string) $configured : App::environmentFilePath();
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string|null}
     */
    private function item(string $key, string $label, string $status, ?string $detail = null): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }
}

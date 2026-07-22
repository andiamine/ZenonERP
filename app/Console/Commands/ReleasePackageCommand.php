<?php

namespace App\Console\Commands;

use App\Foundation\Release\ReleasePackager;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Thin CLI wrapper around {@see ReleasePackager} (CLAUDE.md §7/§12 Phase 8 Task 12) — all
 * pipeline logic lives in the service; this command only reads options and reports.
 */
class ReleasePackageCommand extends Command
{
    protected $signature = 'zenon:release:package
        {--update : Build an update package (no vendor/thirdparty reset — no .env, no modules/thirdparty/ entry)}
        {--out= : Output directory (default: config(zenon.release.out_dir))}
        {--allow-dirty : Do not fail when the working tree has uncommitted changes}';

    protected $description = 'Package a release zip (full or update) with a --no-dev vendor build and prebuilt SPA assets';

    public function handle(ReleasePackager $packager): int
    {
        $out = (string) ($this->option('out') ?? '');

        try {
            $result = $packager->package(
                update: (bool) $this->option('update'),
                outDir: $out !== '' ? $out : null,
                allowDirty: (bool) $this->option('allow-dirty'),
            );
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info(sprintf('Packaged release to [%s].', $result->zipPath));

        return self::SUCCESS;
    }
}

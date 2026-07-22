<?php

namespace App\Foundation\Release;

/**
 * Outcome of {@see ReleasePackager::package()} — the produced zip's absolute path plus
 * any NON-fatal preflight warnings (currently only the git-clean check: git missing/not
 * a repo, or a dirty tree tolerated via --allow-dirty) that the caller (the command)
 * surfaces to the operator without failing the run.
 */
final class ReleasePackageResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $zipPath,
        public readonly array $warnings = [],
    ) {}
}

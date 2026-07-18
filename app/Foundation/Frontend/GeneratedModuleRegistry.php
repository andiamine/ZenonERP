<?php

namespace App\Foundation\Frontend;

/**
 * Reads the registryHash exported by the committed generated registry artifact
 * (resources/js/generated/module-registry.ts, written by zenon:frontend:generate).
 * The hash is parsed from the file — never recomputed — so the API can only ever
 * advertise the artifact that was actually generated and committed; a SPA bundle
 * built from an older artifact sees a mismatch and prompts a reload (CLAUDE.md §7).
 */
final class GeneratedModuleRegistry
{
    private bool $resolved = false;

    private ?string $hash = null;

    public function path(): string
    {
        return resource_path('js/generated/module-registry.ts');
    }

    /** Null when the artifact is absent or unparseable (build-less environments). */
    public function hash(): ?string
    {
        if ($this->resolved) {
            return $this->hash;
        }

        $this->resolved = true;

        $contents = is_file($this->path()) ? file_get_contents($this->path()) : false;

        if ($contents === false) {
            return $this->hash = null;
        }

        return $this->hash = preg_match("/^export const registryHash = '([0-9a-f]{40})';/m", $contents, $matches) === 1
            ? $matches[1]
            : null;
    }
}

<?php

namespace App\Foundation\Modules;

use Nwidart\Modules\Module;

/**
 * Typed value object for a module.json manifest (nwidart fields + the `zenon` block, CLAUDE.md §3).
 * Construct via ManifestValidator::validate() for validated data, or fromArray()/fromNwidartModule()
 * for raw reads of already-trusted manifests.
 */
final readonly class ManifestData
{
    /**
     * @param  array<string, string>  $requires  module alias => semver constraint
     * @param  list<string>  $provides
     * @param  list<string>  $hookEmits
     * @param  list<string>  $permissions
     * @param  list<string>  $providers  provider FQCNs as declared (existence is ManifestValidator's concern)
     */
    public function __construct(
        public string $name,
        public string $alias,
        public string $id,
        public string $version,
        public bool $core,
        public array $requires,
        public array $provides,
        public array $hookEmits,
        public array $permissions,
        public ?string $frontendEntry,
        public ?string $frontendRemote,
        public string $platform,
        public bool $defaultEnabled,
        public string $path,
        public array $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest  decoded module.json
     */
    public static function fromArray(array $manifest, string $path): self
    {
        /** @var array<string, mixed> $zenon */
        $zenon = $manifest['zenon'] ?? [];
        /** @var array<string, mixed> $frontend */
        $frontend = $zenon['frontend'] ?? [];
        /** @var array<string, mixed> $hooks */
        $hooks = $zenon['hooks'] ?? [];

        return new self(
            name: (string) ($manifest['name'] ?? ''),
            alias: (string) ($manifest['alias'] ?? ''),
            id: (string) ($zenon['id'] ?? ''),
            version: (string) ($zenon['version'] ?? ''),
            core: (bool) ($zenon['core'] ?? false),
            requires: array_map(strval(...), (array) ($zenon['requires'] ?? [])),
            provides: array_values(array_map(strval(...), (array) ($zenon['provides'] ?? []))),
            hookEmits: array_values(array_map(strval(...), (array) ($hooks['emits'] ?? []))),
            permissions: array_values(array_map(strval(...), (array) ($zenon['permissions'] ?? []))),
            frontendEntry: isset($frontend['entry']) ? (string) $frontend['entry'] : null,
            frontendRemote: isset($frontend['remote']) ? (string) $frontend['remote'] : null,
            platform: (string) ($zenon['platform'] ?? ''),
            defaultEnabled: (bool) ($zenon['defaultEnabled'] ?? false),
            path: $path,
            providers: array_values(array_map(strval(...), (array) ($manifest['providers'] ?? []))),
        );
    }

    public static function fromNwidartModule(Module $module): self
    {
        return self::fromArray($module->json()->getAttributes(), $module->getPath());
    }

    public function hasZenonBlock(): bool
    {
        return $this->id !== '';
    }
}

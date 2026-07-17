<?php

namespace App\Foundation\Modules;

use App\Foundation\Modules\Exceptions\DependencyException;
use Composer\Semver\Semver;

/**
 * Pure dependency resolution over an explicit manifest universe — no container, no IO.
 */
final class DependencyResolver
{
    /**
     * Dependencies-first topological order for the given aliases, including all
     * transitive dependencies. Throws on cycles, missing deps and semver violations.
     *
     * @param  list<string>  $aliases
     * @param  array<string, ManifestData>  $universe
     * @return list<string>
     *
     * @throws DependencyException
     */
    public function resolveInstallOrder(array $aliases, array $universe): array
    {
        $order = [];
        $visited = [];
        $visiting = [];

        foreach ($aliases as $alias) {
            $this->visit($alias, [], $universe, $order, $visited, $visiting);
        }

        return $order;
    }

    /**
     * Direct dependents of $alias within the universe.
     *
     * @param  array<string, ManifestData>  $universe
     * @return list<string>
     */
    public function dependentsOf(string $alias, array $universe): array
    {
        $dependents = [];

        foreach ($universe as $candidate => $manifest) {
            if (array_key_exists($alias, $manifest->requires)) {
                $dependents[] = $candidate;
            }
        }

        return $dependents;
    }

    /**
     * @param  list<string>  $path
     * @param  array<string, ManifestData>  $universe
     * @param  list<string>  $order
     * @param  array<string, true>  $visited
     * @param  array<string, true>  $visiting
     */
    private function visit(string $alias, array $path, array $universe, array &$order, array &$visited, array &$visiting): void
    {
        if (isset($visited[$alias])) {
            return;
        }

        if (isset($visiting[$alias])) {
            throw DependencyException::cycleDetected([...$path, $alias]);
        }

        $manifest = $universe[$alias]
            ?? throw DependencyException::missing($path === [] ? $alias : (string) end($path), $alias);

        $visiting[$alias] = true;

        foreach ($manifest->requires as $dependency => $constraint) {
            $dependencyManifest = $universe[$dependency]
                ?? throw DependencyException::missing($alias, $dependency);

            if (! Semver::satisfies($dependencyManifest->version, $constraint)) {
                throw DependencyException::unsatisfied($alias, $dependency, $constraint, $dependencyManifest->version);
            }

            $this->visit($dependency, [...$path, $alias], $universe, $order, $visited, $visiting);
        }

        unset($visiting[$alias]);
        $visited[$alias] = true;
        $order[] = $alias;
    }
}

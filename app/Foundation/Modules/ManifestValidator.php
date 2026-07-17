<?php

namespace App\Foundation\Modules;

use App\Foundation\Modules\Exceptions\InvalidManifestException;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Validates a module.json manifest (nwidart fields + the `zenon` block, CLAUDE.md §3).
 *
 * Deliberate deviation from §3's literal "JSON Schema" wording: the schema is enforced
 * with Laravel's validator + composer/semver (which JSON Schema could not express for
 * version-constraint strings anyway). The mechanism is fully encapsulated here — a
 * publishable schema file for third-party authors can replace the internals later
 * without touching any caller.
 */
final class ManifestValidator
{
    private const ZENON_KEYS = [
        'id', 'version', 'core', 'requires', 'provides', 'hooks',
        'permissions', 'frontend', 'platform', 'defaultEnabled',
    ];

    public function __construct(private readonly VersionParser $versionParser = new VersionParser) {}

    /**
     * @param  array<string, mixed>  $manifest  decoded module.json
     *
     * @throws InvalidManifestException
     */
    public function validate(array $manifest, string $modulePath): ManifestData
    {
        $validator = Validator::make($manifest, [
            'name' => ['required', 'string', 'regex:/^[A-Z][A-Za-z0-9]*$/'],
            'alias' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]*$/'],
            'providers' => ['required', 'array', 'min:1'],
            'providers.*' => ['string'],
            'zenon' => ['required', 'array'],
            'zenon.id' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'zenon.version' => ['required', 'string'],
            'zenon.core' => ['sometimes', 'boolean'],
            'zenon.requires' => ['sometimes', 'array'],
            'zenon.requires.*' => ['string'],
            'zenon.provides' => ['sometimes', 'array'],
            'zenon.provides.*' => ['string'],
            'zenon.hooks' => ['sometimes', 'array:emits'],
            'zenon.hooks.emits' => ['sometimes', 'array'],
            'zenon.hooks.emits.*' => ['string'],
            'zenon.permissions' => ['sometimes', 'array'],
            'zenon.permissions.*' => ['string'],
            'zenon.frontend' => ['sometimes', 'array:entry,remote'],
            'zenon.frontend.entry' => ['nullable', 'string'],
            'zenon.frontend.remote' => ['nullable', 'string'],
            'zenon.platform' => ['required', 'string'],
            'zenon.defaultEnabled' => ['sometimes', 'boolean'],
        ]);

        /** @var array<string, list<string>> $errors */
        $errors = $validator->fails() ? $validator->errors()->toArray() : [];

        $errors = $this->validateSemantics($manifest, $errors);

        if ($errors !== []) {
            throw new InvalidManifestException($modulePath, $errors);
        }

        return ManifestData::fromArray($manifest, $modulePath);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function validateSemantics(array $manifest, array $errors): array
    {
        /** @var array<string, mixed> $zenon */
        $zenon = is_array($manifest['zenon'] ?? null) ? $manifest['zenon'] : [];

        foreach (array_diff(array_keys($zenon), self::ZENON_KEYS) as $unknown) {
            $errors["zenon.$unknown"][] = sprintf('Unknown key [%s] in the zenon block.', $unknown);
        }

        if (is_string($zenon['version'] ?? null) && ! $this->isVersion($zenon['version'])) {
            $errors['zenon.version'][] = sprintf('[%s] is not a valid semver version.', $zenon['version']);
        }

        if (is_string($zenon['platform'] ?? null) && ! $this->isConstraint($zenon['platform'])) {
            $errors['zenon.platform'][] = sprintf('[%s] is not a valid version constraint.', $zenon['platform']);
        }

        if (is_array($zenon['requires'] ?? null)) {
            foreach ($zenon['requires'] as $dep => $constraint) {
                if (! is_string($constraint) || ! $this->isConstraint($constraint)) {
                    $errors["zenon.requires.$dep"][] = sprintf(
                        'Constraint [%s] for dependency [%s] is not a valid version constraint.',
                        is_scalar($constraint) ? (string) $constraint : gettype($constraint), (string) $dep,
                    );
                }
            }
        }

        if (is_array($manifest['providers'] ?? null)) {
            foreach ($manifest['providers'] as $i => $provider) {
                if (is_string($provider) && ! class_exists($provider)) {
                    $errors["providers.$i"][] = sprintf('Provider class [%s] does not exist.', $provider);
                }
            }
        }

        $alias = $manifest['alias'] ?? null;
        $id = $zenon['id'] ?? null;
        if (is_string($alias) && is_string($id) && $id !== '' && Str::after($id, '/') !== $alias) {
            $errors['zenon.id'][] = sprintf(
                'Manifest id [%s] must end in the module alias [%s] (one canonical id ↔ alias mapping).',
                $id, $alias,
            );
        }

        return $errors;
    }

    private function isVersion(string $version): bool
    {
        try {
            $this->versionParser->normalize($version);

            return true;
        } catch (UnexpectedValueException) {
            return false;
        }
    }

    private function isConstraint(string $constraint): bool
    {
        try {
            $this->versionParser->parseConstraints($constraint);

            return true;
        } catch (UnexpectedValueException) {
            return false;
        }
    }
}

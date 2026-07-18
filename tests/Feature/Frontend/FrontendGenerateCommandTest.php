<?php

use Illuminate\Support\Facades\File;

/**
 * zenon:frontend:generate reads discovered manifests from disk (the test fixtures)
 * and emits the committed registry artifact — no database involved.
 */
function registryOutputPath(): string
{
    return storage_path('framework/testing/module-registry-output.ts');
}

beforeEach(function (): void {
    File::delete(registryOutputPath());
});

it('generates the registry from discovered modules with frontend entries', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])
        ->assertSuccessful();

    $content = File::get(registryOutputPath());

    expect($content)
        ->toContain("import type { RegistryEntry } from '@zenon/core/moduleTypes';")
        ->toContain('dummy: {')
        ->toContain('dummycore: {')
        ->toContain("load: () => import('@modules/Dummy/resources/js/index'),")
        ->toContain("load: () => import('@modules/DummyCore/resources/js/index'),")
        ->not->toContain('dummydep') // frontend.entry is null → excluded
        ->and($content)->toMatch("/export const registryHash = '[0-9a-f]{40}';/");
});

it('emits aliases in sorted order with LF line endings', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])
        ->assertSuccessful();

    $content = File::get(registryOutputPath());

    expect(strpos($content, 'dummy: {'))->toBeLessThan(strpos($content, 'dummycore: {'))
        ->and($content)->not->toContain("\r\n");
});

it('is deterministic across runs', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])->assertSuccessful();
    $first = File::get(registryOutputPath());

    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])->assertSuccessful();

    expect(File::get(registryOutputPath()))->toBe($first);
});

it('passes --check against a freshly generated file', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])->assertSuccessful();

    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath(), '--check' => true])
        ->assertSuccessful();
});

it('passes --check when the file on disk has CRLF line endings', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])->assertSuccessful();

    File::put(registryOutputPath(), str_replace("\n", "\r\n", File::get(registryOutputPath())));

    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath(), '--check' => true])
        ->assertSuccessful();
});

it('fails --check against a stale file', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath()])->assertSuccessful();

    File::append(registryOutputPath(), "// tampered\n");

    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath(), '--check' => true])
        ->assertFailed();
});

it('fails --check when the file is missing', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath(), '--check' => true])
        ->assertFailed();
});

it('does not write anything in --check mode', function (): void {
    $this->artisan('zenon:frontend:generate', ['--path' => registryOutputPath(), '--check' => true])
        ->assertFailed();

    expect(File::exists(registryOutputPath()))->toBeFalse();
});

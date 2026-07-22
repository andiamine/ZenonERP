<?php

use App\Foundation\Support\ZipBuilder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
 * Phase 8 Task 8: unit coverage for the NEW capability ZipBuilder adds beyond the
 * behavior ModulePackageCommandTest already locks down (basename-only exclusion) —
 * `excludedRelativePaths`, anchored to the addTree() root rather than to the zip
 * entry name, so a prefix or nesting never shifts what "public/hot" means. Fixtures
 * are tiny temp trees under storage/framework/testing/, cleaned up in afterEach().
 * Bound to Tests\TestCase (unlike EnvWriterTest) purely for the storage_path() helper
 * — ZipBuilder itself touches no facades/config, same as ModulePackageCommand's zip
 * step it's extracted from.
 */
uses(TestCase::class);

beforeEach(function () {
    $this->root = storage_path('framework/testing/zip-builder-'.uniqid());
    $this->outDir = storage_path('framework/testing/zip-builder-out-'.uniqid());
    File::ensureDirectoryExists($this->root);
    File::ensureDirectoryExists($this->outDir);
});

afterEach(function () {
    File::deleteDirectory($this->root);
    File::deleteDirectory($this->outDir);
});

/** @return list<string> */
function zipBuilderEntryNames(string $zipPath): array
{
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $names = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    $zip->close();

    return $names;
}

it('excludes a directory by relative path while a same-basename directory elsewhere is kept', function () {
    File::ensureDirectoryExists($this->root.'/sub/skip-me');
    File::ensureDirectoryExists($this->root.'/other/skip-me');
    File::put($this->root.'/sub/skip-me/file.txt', 'excluded');
    File::put($this->root.'/other/skip-me/keep.txt', 'kept');

    $zipPath = $this->outDir.'/relative-path.zip';

    ZipBuilder::create($zipPath)
        ->addTree($this->root, '', [], ['sub/skip-me'])
        ->close();

    $names = zipBuilderEntryNames($zipPath);

    expect($names)->toContain('other/skip-me/keep.txt')
        ->not->toContain('sub/skip-me/file.txt');
});

it('excludes a directory by basename at any depth via excludedDirNames', function () {
    File::ensureDirectoryExists($this->root.'/a/node_modules/lib');
    File::ensureDirectoryExists($this->root.'/b/node_modules/lib');
    File::put($this->root.'/a/node_modules/lib/index.js', 'excluded');
    File::put($this->root.'/b/node_modules/lib/index.js', 'excluded');
    File::put($this->root.'/a/keep.txt', 'kept');

    $zipPath = $this->outDir.'/dir-name.zip';

    ZipBuilder::create($zipPath)
        ->addTree($this->root, '', ['node_modules'])
        ->close();

    $names = zipBuilderEntryNames($zipPath);

    expect($names)->toContain('a/keep.txt');

    foreach ($names as $name) {
        expect($name)->not->toContain('node_modules');
    }
});

it('anchors excludedRelativePaths to the addTree root, not to the zip entry (prefix ignored for exclusion)', function () {
    File::ensureDirectoryExists($this->root.'/sub/skip-me');
    File::put($this->root.'/sub/skip-me/file.txt', 'excluded');
    File::put($this->root.'/sub/keep.txt', 'kept');

    $zipPath = $this->outDir.'/nested-prefix.zip';

    ZipBuilder::create($zipPath)
        ->addTree($this->root, 'wrapper', [], ['sub/skip-me'])
        ->close();

    $names = zipBuilderEntryNames($zipPath);

    expect($names)->toContain('wrapper/sub/keep.txt')
        ->not->toContain('wrapper/sub/skip-me/file.txt');
});

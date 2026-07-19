<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams prebuilt third-party addon dist files (CLAUDE.md §7 remote loading) from
 * config('zenon.thirdparty_path') — never a hard-coded base_path() — so a file lives
 * at {thirdparty_path}/{folder}/dist/{path}. The config indirection is the test seam:
 * tests point it at a temp fixture dir instead of a real addon on disk.
 *
 * Defense in depth beyond the route's where() constraints (bootstrap/app.php): Laravel
 * decodes %2e%2e BEFORE route matching, so the regex alone cannot stop an encoded
 * traversal segment from reaching here. Reject any decoded path containing ".." or a
 * backslash outright, then require the resolved realpath() to stay inside the addon's
 * dist directory. Every failure — missing folder, missing file, non-file, traversal —
 * is a flat 404: a probe for a file outside dist/ must be indistinguishable from a
 * typo.
 */
class ModuleAssetController extends Controller
{
    /** @var array<string, string> */
    private const MIME_TYPES = [
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'css' => 'text/css',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'woff2' => 'font/woff2',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /** Version pointers Vite rewrites in place on every build — never content-hashed. */
    private const VERSION_POINTERS = ['remoteEntry.js', 'mf-manifest.json', 'mf-stats.json'];

    public function __invoke(string $folder, string $path): BinaryFileResponse
    {
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            abort(404);
        }

        $distDir = realpath(config('zenon.thirdparty_path').DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.'dist');

        if ($distDir === false) {
            abort(404);
        }

        $file = realpath($distDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

        if ($file === false || ! is_file($file) || ! str_starts_with($file, $distDir.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return response()->file($file, [
            'Content-Type' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
            'Cache-Control' => in_array(basename($file), self::VERSION_POINTERS, true)
                ? 'no-cache'
                : 'public, max-age=31536000, immutable',
        ]);
    }
}

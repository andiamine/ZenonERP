<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ZenonERP') }}</title>
    <script>
        // Pre-hydration dark mode — applied before paint so there is no flash. This is the
        // Blade-shell equivalent of MUI's InitColorSchemeScript: dark mode is MUI-OWNED
        // (useColorScheme, localStorage 'mui-mode', ThemeProvider defaultMode="system"),
        // and the ThemeProvider re-applies the .light/.dark class on <html> itself after
        // hydration — this script only pre-seeds the same class MUI will assert, using the
        // same storage key and system-preference fallback. Never toggle the class from app
        // code (a parallel toggle fights MUI's mode manager and loses).
        try {
            var mode = localStorage.getItem('mui-mode') || 'system';
            if (mode === 'system') {
                mode = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.classList.add(mode);
        } catch (e) {}
    </script>
    {{-- The manifest-exists guard is load-bearing: the PHP test suite and CI's PHP job run build-less. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @viteReactRefresh
        @vite('resources/js/main.tsx')
    @endif
</head>
<body>
    <div id="root"></div>
</body>
</html>

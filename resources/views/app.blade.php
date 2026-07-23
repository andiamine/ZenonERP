<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ZenonERP') }}</title>
    <script>
        // Pre-hydration dark mode (class strategy) — applied before paint so there is no
        // flash. Pairs with core/store.ts and the MUI theme's cssVariables
        // colorSchemeSelector: 'class' (core/theme.ts): MUI emits its dark-scheme CSS
        // variables under this same .dark class on <html>.
        try {
            var theme = localStorage.getItem('zenon.theme');
            if (theme === 'dark' || (theme === null && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
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

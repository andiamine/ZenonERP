<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ZenonERP') }}</title>
    <script>
        // Pre-hydration dark mode (class strategy, pairs with @custom-variant dark in
        // app.css and core/store.ts) — applied before paint so there is no flash.
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
<body class="bg-background font-sans text-foreground antialiased">
    <div id="root"></div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Pinta la barra del navegador del mismo color que la franja superior:
             el azul marino de la marca en claro, el fondo en oscuro. --}}
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#0a2550">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#071224">

        <title inertia>{{ config('app.name', 'AVIS') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico">

        {{-- Aplica el tema antes de pintar: sin esto, quien eligió oscuro ve un
             destello blanco en cada carga. --}}
        <script>
            (function () {
                try {
                    var modo = localStorage.getItem('appearance') || 'system';
                    var oscuro = modo === 'dark' || (modo === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', oscuro);
                } catch (e) {}
            })();
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|lexend:500,600,700&display=swap" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

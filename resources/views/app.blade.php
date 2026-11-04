<!DOCTYPE html>
<html lang="pt-BR" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Aplica o tema antes da primeira pintura, para não piscar. --}}
        <script>
            (function () {
                var saved = null;

                try {
                    saved = localStorage.getItem('appearance');
                } catch (error) {}

                var appearance = saved || @js($appearance ?? 'system');
                var dark = appearance === 'dark'
                    || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            })();
        </script>

        {{-- O mesmo --background do app.css, antes de o CSS carregar. --}}
        <style>
            html {
                background-color: oklch(0.985 0.002 250);
            }

            html.dark {
                background-color: oklch(0.13 0.004 260);
            }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            {{-- Nome da aba sem espaço nem acento, para achar digitando "meavisaai" no navegador. --}}
            <title>meavisaAi</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

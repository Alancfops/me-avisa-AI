<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') — Me Avisa Aí</title>

        {{-- CSS próprio, sem Vite: a página de erro não pode depender do build. --}}
        <style>
            :root {
                color-scheme: light dark;
                --background: oklch(0.985 0.002 250);
                --foreground: oklch(0.16 0.01 260);
                --muted-foreground: oklch(0.5 0.015 258);
                --primary: oklch(0.56 0.21 258);
                --primary-foreground: oklch(0.99 0 0);
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --background: oklch(0.13 0.004 260);
                    --foreground: oklch(0.96 0.003 250);
                    --muted-foreground: oklch(0.66 0.012 258);
                    --primary: oklch(0.68 0.18 255);
                    --primary-foreground: oklch(0.13 0.004 260);
                }
            }

            body {
                display: grid;
                place-items: center;
                min-height: 100svh;
                margin: 0;
                padding: 1rem;
                box-sizing: border-box;
                background: var(--background);
                color: var(--foreground);
                font-family: ui-sans-serif, system-ui, sans-serif;
            }

            main {
                max-width: 28rem;
                text-align: center;
            }

            h1 {
                margin: 0 0 0.5rem;
                font-size: 1.5rem;
            }

            p {
                margin: 0 0 1.5rem;
                color: var(--muted-foreground);
            }

            a {
                display: inline-block;
                padding: 0.5rem 1rem;
                border-radius: 0.375rem;
                background: var(--primary);
                color: var(--primary-foreground);
                font-weight: 600;
                text-decoration: none;
            }

            a:focus-visible {
                outline: 2px solid var(--primary);
                outline-offset: 2px;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <a href="/">Ir para o painel</a>
        </main>
    </body>
</html>

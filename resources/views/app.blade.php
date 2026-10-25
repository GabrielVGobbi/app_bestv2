<!DOCTYPE html>
@php($branding = app(\App\Services\BrandingService::class))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--brand-hue: {{ $branding->hue() }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.975 0.004 var(--brand-hue, 215));
                color-scheme: light;
            }

            html.dark {
                background-color: oklch(0.19 0.01 var(--brand-hue, 215));
                color-scheme: dark;
            }

            #boot-shell {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: oklch(0.975 0.004 var(--brand-hue, 215));
                color: oklch(0.48 0.11 var(--brand-hue, 215));
            }

            html.dark #boot-shell {
                background: oklch(0.19 0.01 var(--brand-hue, 215));
                color: oklch(0.78 0.1 var(--brand-hue, 215));
            }

            #boot-loading,
            #noscript-notice {
                position: fixed;
                bottom: 16px;
                z-index: 10000;
                padding: 8px 14px;
                border-radius: 8px;
                background: oklch(1 0 0);
                color: oklch(0.46 0.02 var(--brand-hue, 215));
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
                font: 13px/1.4 system-ui, sans-serif;
            }

            #boot-loading { right: 16px; }
            #noscript-notice { left: 16px; }

            html.dark #boot-loading,
            html.dark #noscript-notice {
                background: oklch(0.27 0.012 var(--brand-hue, 215));
                color: oklch(0.72 0.015 var(--brand-hue, 215));
            }

            #boot-error {
                position: fixed;
                right: 12px;
                bottom: 12px;
                left: 12px;
                z-index: 99999;
                max-height: 40vh;
                overflow: auto;
                padding: 10px 14px;
                border: 1px solid oklch(0.38 0.14 25);
                border-radius: 8px;
                background: oklch(0.95 0.03 20);
                color: oklch(0.38 0.14 25);
                font: 12px/1.4 ui-monospace, monospace;
                white-space: pre-wrap;
            }
        </style>

        <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $branding->name() }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="boot-shell" aria-hidden="true">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" role="img" aria-label="{{ $branding->name() }}">
                <rect x="9" y="2" width="13" height="13" rx="3.5" fill="currentColor" opacity="0.55" />
                <rect x="2" y="9" width="13" height="13" rx="3.5" fill="currentColor" />
            </svg>
        </div>

        <div id="boot-loading" role="status">Carregando...</div>

        <noscript>
            <style>
                #boot-loading {
                    display: none;
                }
            </style>
            <div id="noscript-notice">Esta página precisa de JavaScript.</div>
        </noscript>

        <x-inertia::app />

        <script>
            window.addEventListener('error', function (event) {
                var panel = document.getElementById('boot-error');

                if (!panel) {
                    panel = document.createElement('div');
                    panel.id = 'boot-error';
                    document.body.appendChild(panel);
                }

                panel.textContent += (panel.textContent ? '\n' : '') + 'Erro: ' + event.message;
            });
        </script>
    </body>
</html>

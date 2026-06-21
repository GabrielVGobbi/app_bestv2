<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            #boot-shell {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #0d0d0d;
            }

            #boot-loading {
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 10000;
                padding: 8px 14px;
                border-radius: 8px;
                background: #ffffff;
                color: #666666;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
                font: 13px/1.4 -apple-system, BlinkMacSystemFont, sans-serif;
            }

            #noscript-notice {
                position: fixed;
                bottom: 12px;
                left: 12px;
                z-index: 10000;
                padding: 6px 12px;
                border-radius: 6px;
                background: rgba(255, 255, 255, 0.9);
                color: #999999;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
                font: 13px/1.4 -apple-system, BlinkMacSystemFont, sans-serif;
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
                border: 1px solid #5c2b2e;
                border-radius: 8px;
                background: #2a1215;
                color: #ff8a80;
                font: 12px/1.4 ui-monospace, monospace;
                white-space: pre-wrap;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="boot-shell" aria-hidden="true">
            <svg width="240" height="160" viewBox="0 0 240 160" role="img" aria-label="Runey">
                <rect width="240" height="160" fill="#0d0d0d" />
                <circle cx="120" cy="76" r="32" fill="#ededed" />
                <text x="120" y="90" fill="#0d0d0d" font-family="Poppins, -apple-system, BlinkMacSystemFont, sans-serif" font-size="38" font-weight="700" text-anchor="middle">R</text>
            </svg>
        </div>

        <div id="boot-loading" role="status">Rendering...</div>

        <noscript>
            <style>
                #boot-loading {
                    display: none;
                }
            </style>
            <div id="noscript-notice">This page requires JavaScript to display.</div>
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

                panel.textContent += (panel.textContent ? '\n' : '') + 'Error: ' + event.message;
            });
        </script>
    </body>
</html>

import assert from 'node:assert/strict';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, resolve, sep } from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const output = resolve(root, 'output/playwright/design-parity');
mkdirSync(output, { recursive: true });
const manifest = JSON.parse(
    readFileSync(resolve(root, 'public/build/manifest.json'), 'utf8'),
);
// Boot shell styles live inline in the Blade root; reuse them so the probe sees the real first paint.
const bootStyle = readFileSync(
    resolve(root, 'resources/views/app.blade.php'),
    'utf8',
).match(/<style>([\s\S]*?)<\/style>/)[1];
const { chromium } = await import(
    pathToFileURL(process.env.DESIGN_PARITY_PLAYWRIGHT_MODULE).href
);
let origin;
const mime = {
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.woff2': 'font/woff2',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
};
const report = {
    status: 'running',
    checks: [],
    errors: [],
    blocked: [],
    mutations: [],
    syntheticPosts: [],
    assets: 0,
    backendRequests: 0,
    served: [],
};
const check = (name, value) => {
    assert(value, name);
    report.checks.push(name);
};
const item = (title, href, icon) => ({ title, href, icon });
const section = (title, items) => ({ title, type: 'section', items });
const modules = [
    {
        slug: 'gestao',
        label: 'Gestão',
        icon: 'Users',
        default: true,
        routePrefixes: ['/dashboard', '/app'],
        defaultHref: '/dashboard',
        items: [
            section('Plataforma', [
                item('Dashboard', '/dashboard', 'LayoutGrid'),
            ]),
            section('ACL', [
                item('Usuários', '/app/users', 'Users'),
                item('Papéis', '/app/roles', 'Shield'),
                item('Permissões', '/app/permissions', 'KeyRound'),
            ]),
            section('Cadastros', [
                item('Clientes', '/app/clients', 'Table2'),
                item('E-mails', '/app/emails', 'Mail'),
            ]),
            section('Sistema', [
                item('Activity Log', '/app/activity-log', 'History'),
                item('Exportações', '/app/exports', 'Download'),
            ]),
        ],
    },
    {
        slug: 'external',
        label: 'External APIs',
        icon: 'Unplug',
        default: false,
        routePrefixes: ['/external'],
        defaultHref: '/external/diagnostics',
        items: [
            section('Integrações', [
                item('Diagnósticos', '/external/diagnostics', 'Activity'),
            ]),
        ],
    },
    {
        slug: 'dev',
        label: 'Dev',
        icon: 'Zap',
        default: false,
        routePrefixes: ['/admin/dev'],
        defaultHref: '/admin/dev/design-system',
        items: [
            section('Desenvolvimento', [
                item('Design System', '/admin/dev/design-system', 'Palette'),
            ]),
        ],
    },
];
function auditContrast(page) {
    return page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const rgba = (color) => {
            ctx.clearRect(0, 0, 1, 1);
            ctx.fillStyle = color;
            ctx.fillRect(0, 0, 1, 1);

            return Array.from(ctx.getImageData(0, 0, 1, 1).data).map(
                (value) => value / 255,
            );
        };
        const over = (front, back) =>
            front
                .slice(0, 3)
                .map(
                    (channel, index) =>
                        channel * front[3] + back[index] * (1 - front[3]),
                );
        // Decorative layers (absolute, pointer-events: none) painted under a row,
        // such as the Fluid active pill: siblings, not ancestors, of the text.
        const underlay = (element) => {
            const parent = element.parentElement;

            if (!parent) {
                return null;
            }

            const box = element.getBoundingClientRect();
            const x = box.left + box.width / 2;
            const y = box.top + box.height / 2;

            for (const sibling of parent.children) {
                if (sibling === element) {
                    continue;
                }

                const style = getComputedStyle(sibling);
                const color = rgba(style.backgroundColor);
                const rect = sibling.getBoundingClientRect();

                if (
                    style.position === 'absolute' &&
                    style.pointerEvents === 'none' &&
                    color[3] > 0 &&
                    x >= rect.left &&
                    x <= rect.right &&
                    y >= rect.top &&
                    y <= rect.bottom
                ) {
                    return color;
                }
            }

            return null;
        };
        const background = (element) => {
            if (!element) {
                return [1, 1, 1];
            }

            const color = rgba(getComputedStyle(element).backgroundColor);

            if (color[3] === 1) {
                return color.slice(0, 3);
            }

            const layer = underlay(element);
            const below = layer
                ? layer[3] === 1
                    ? layer.slice(0, 3)
                    : over(layer, background(element.parentElement))
                : background(element.parentElement);

            return color[3] === 0 ? below : over(color, below);
        };
        const luminance = (rgb) =>
            rgb
                .map((value) =>
                    value <= 0.04045
                        ? value / 12.92
                        : ((value + 0.055) / 1.055) ** 2.4,
                )
                .reduce(
                    (sum, value, index) =>
                        sum + value * [0.2126, 0.7152, 0.0722][index],
                    0,
                );
        const contrast = (a, b) =>
            (Math.max(luminance(a), luminance(b)) + 0.05) /
            (Math.min(luminance(a), luminance(b)) + 0.05);
        const failures = [];
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
        );

        while (walker.nextNode()) {
            const node = walker.currentNode;
            const element = node.parentElement;

            if (
                !node.textContent.trim() ||
                !element ||
                element.closest(
                    'script, style, [aria-hidden=true], .sr-only, [disabled]',
                )
            ) {
                continue;
            }

            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);

            if (!rect.width || !rect.height || style.visibility === 'hidden') {
                continue;
            }

            const bg = background(element);
            const ratio = contrast(over(rgba(style.color), bg), bg);
            const large =
                parseFloat(style.fontSize) >= 24 ||
                (parseFloat(style.fontSize) >= 18.66 &&
                    Number(style.fontWeight) >= 700);

            if (ratio < (large ? 3 : 4.5)) {
                failures.push({
                    text: node.textContent.trim().slice(0, 60),
                    color: style.color,
                    background: bg,
                    classes: element.className,
                    ratio: Math.round(ratio * 100) / 100,
                });
            }
        }

        const card = document.querySelector('main section');
        const input = document.querySelector(
            '[data-slot=input]:not([disabled])',
        );
        // A borda em repouso (input-soft) é decorativa; a de hover (input) tem de dar 3:1.
        const hoverBorder = input
            ? (() => {
                  const rest = input.style.borderColor;
                  input.style.borderColor = 'var(--input)';
                  const color = getComputedStyle(input).borderColor;
                  input.style.borderColor = rest;
                  return color;
              })()
            : null;

        return {
            failures,
            fieldBorderContrast: input
                ? contrast(rgba(hoverBorder).slice(0, 3), background(input))
                : null,
            placeholderContrast: input
                ? contrast(
                      over(
                          rgba(getComputedStyle(input, '::placeholder').color),
                          background(input),
                      ),
                      background(input),
                  )
                : null,
            elevated:
                luminance(background(card)) >
                luminance(background(document.body)),
            colorScheme: getComputedStyle(document.documentElement).colorScheme,
        };
    });
}
const components = {
    '/admin/dev/design-system': 'admin/dev/design-system',
    '/login': 'auth/login',
    '/app/users': 'app/users/index',
    '/app/users/create': 'app/users/create',
    '/settings/profile': 'settings/profile',
};
function payload(url) {
    const mode = new URL(url).searchParams.get('mode');
    const hue = Number(new URL(url).searchParams.get('hue') ?? 215);

    return {
        component: components[new URL(url).pathname] ?? 'dashboard',
        url: new URL(url).pathname + new URL(url).search,
        version: null,
        clearHistory: false,
        encryptHistory: false,
        props: {
            name: 'Start kit',
            branding: {
                name:
                    mode === 'long'
                        ? 'Plataforma de Gestão Integrada Corporativa Ltda'
                        : 'Start kit',
                shortName: 'SK',
                hue,
                logo: {
                    mark: null,
                    light:
                        mode === 'widelogo' ? '/synthetic/wide-logo.svg' : null,
                    dark: null,
                },
            },
            can: { create: true, update: true, delete: true, view: true },
            mustVerifyEmail: false,
            auth: {
                user: {
                    id: 987654,
                    name: 'Preview Visual',
                    email: 'preview@example.test',
                    email_verified_at: '2026-10-20T00:00:00Z',
                    must_change_password: false,
                    created_at: '2026-01-01',
                    updated_at: '2026-01-01',
                },
            },
            sidebarOpen: true,
            layout: {
                style: mode === 'header' ? 'header-logo' : 'sidebar-logo',
            },
            nav: {
                style: mode === 'flat' ? 'flat' : 'modules',
                modulesMenuPosition: 'header',
                global: [],
                modules: mode === 'empty' ? [] : modules,
                footer: [],
            },
            canResetPassword: true,
            canRegister: true,
            errors:
                mode === 'errors'
                    ? {
                          email: 'Estas credenciais n\u00e3o conferem.',
                          password: 'Informe a senha.',
                      }
                    : {},
            flash: {},
        },
    };
}
function html(url) {
    const page = JSON.stringify(payload(url)).replaceAll('<', '\\u003c');
    const css = manifest['resources/css/app.css'].file;
    const entry = manifest['resources/js/app.tsx'].file;

    const hue = Number(new URL(url).searchParams.get('hue') ?? 215);

    return `<!doctype html><html style="--brand-hue: ${hue}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="synthetic-design-fixture"><style>${bootStyle}</style><link rel="stylesheet" href="/build/${css}"></head><body class="font-sans antialiased"><script type="application/json" data-page="app">${page}</script><div id="app"></div><script type="module" src="/build/${entry}"></script></body></html>`;
}
const server = createServer((request, response) => {
    const url = new URL(request.url, origin);
    report.served.push(request.url);

    // Like a failed Laravel validation: redirect back with errors in the page props.
    if (request.method === 'POST' && url.pathname === '/login') {
        report.syntheticPosts.push(request.url);
        response.writeHead(303, { Location: '/login?mode=errors' });
        response.end();

        return;
    }

    if (request.method !== 'GET') {
        report.mutations.push(request.url);
        response.writeHead(405);
        response.end();

        return;
    }

    if (request.url.startsWith('/api/v1/users')) {
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(
            JSON.stringify({
                data: [1, 2, 3].map((id) => ({
                    id,
                    uuid: `00000000-0000-4000-8000-00000000000${id}`,
                    name: ['Preview Visual', 'Ana Souza', 'Caio Lima'][id - 1],
                    email: `pessoa${id}@example.test`,
                    email_verified_at: id === 3 ? null : '2026-10-20T00:00:00Z',
                    must_change_password: false,
                    created_at: '2026-10-21T15:54:00Z',
                    updated_at: '2026-10-21T15:54:00Z',
                    can: { update: true, delete: id !== 1 },
                })),
                links: { first: null, last: null, prev: null, next: null },
                meta: {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    path: '/api/v1/users',
                    per_page: 30,
                    to: 3,
                    total: 3,
                },
            }),
        );

        return;
    }

    if (['/dashboard', ...Object.keys(components)].includes(url.pathname)) {
        const inertia = request.headers['x-inertia'];
        response.writeHead(200, {
            'Content-Type': inertia
                ? 'application/json'
                : 'text/html; charset=utf-8',
            ...(inertia ? { 'X-Inertia': 'true' } : {}),
        });
        response.end(inertia ? JSON.stringify(payload(url)) : html(url));

        return;
    }

    if (url.pathname === '/synthetic/wide-logo.svg') {
        response.writeHead(200, { 'Content-Type': mime['.svg'] });
        response.end(
            '<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="100" viewBox="0 0 1000 100"><rect width="1000" height="100" fill="#0f766e"/></svg>',
        );

        return;
    }

    if (
        url.pathname === '/api/v1/notifications/unread' ||
        url.pathname === '/api/v1/notifications/unread-count'
    ) {
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ data: [], unread_count: 0 }));

        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/src/') ||
        url.pathname === '/favicon.ico'
    ) {
        const publicRoot = resolve(root, 'public');
        const path = resolve(
            publicRoot,
            `.${decodeURIComponent(url.pathname)}`,
        );

        if (path.startsWith(publicRoot + sep) && existsSync(path)) {
            report.assets++;
            response.writeHead(200, {
                'Content-Type':
                    mime[extname(path)] ?? 'application/octet-stream',
            });
            response.end(readFileSync(path));

            return;
        }
    }

    report.blocked.push(request.url);
    response.writeHead(404);
    response.end();
});
await new Promise((resolveListening) =>
    server.listen(0, '127.0.0.1', resolveListening),
);
origin = `http://www2.start_kit.com.br:${server.address().port}`;
report.origin = origin;
let browser;

try {
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({
        viewport: { width: 1884, height: 1000 },
        serviceWorkers: 'block',
        reducedMotion: 'reduce',
        colorScheme: 'light',
    });
    await context.route('**/*', (route) => {
        if (new URL(route.request().url()).origin === origin) {
            return route.continue();
        }

        report.blocked.push(route.request().url());

        return route.abort();
    });
    await context.routeWebSocket('**/*', (socket) => {
        report.blocked.push(socket.url());
        socket.close();
    });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    page.on('pageerror', (error) => report.errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            report.errors.push(message.text());
        }
    });
    await page.goto(`${origin}/dashboard`);
    await page
        .getByRole('heading', { name: 'Visão geral', exact: true })
        .waitFor();
    await page.evaluate(() => document.fonts.ready);
    const sidebar = page.locator('[data-slot="sidebar"]');
    const sidebarWidth = () =>
        page.evaluate(() =>
            Math.round(
                document
                    .querySelector('[data-slot="sidebar"]')
                    .getBoundingClientRect().width,
            ),
        );
    check(
        'desktop sidebar keeps the 240px default width',
        (await sidebar.boundingBox()).width === 240,
    );
    check(
        'header uses the reference 64px height',
        (await page.locator('header').first().boundingBox()).height === 64,
    );
    check(
        'dashboard has no Laravel placeholder pattern',
        (await page.locator('pattern').count()) === 0,
    );
    check(
        'dashboard resources use only filtered Start Kit navigation',
        (await page
            .getByRole('heading', { name: 'Recursos da plataforma' })
            .count()) === 1 &&
            (await page.getByText('PEDIDOS', { exact: true }).count()) === 0,
    );
    check(
        'desktop dashboard has no horizontal overflow',
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    const cdp = await context.newCDPSession(page);
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    const dom = await cdp.send('DOM.getDocument');
    const node = await cdp.send('DOM.querySelector', {
        nodeId: dom.root.nodeId,
        selector: 'h1',
    });
    const fonts = await cdp.send('CSS.getPlatformFontsForNode', {
        nodeId: node.nodeId,
    });
    check(
        'actual rendered heading uses local Geist',
        fonts.fonts.some(
            (font) =>
                font.familyName === 'Geist' &&
                font.isCustomFont &&
                font.glyphCount > 0,
        ),
    );
    await page.screenshot({
        path: resolve(output, 'desktop-light.png'),
        fullPage: true,
        animations: 'disabled',
    });
    report.lightAudit = await auditContrast(page);
    check(
        'visible light text meets WCAG AA contrast',
        report.lightAudit.failures.length === 0,
    );
    report.bootShell = await page.evaluate(() => ({
        dark: (() => {
            const probe = document.createElement('div');
            probe.id = 'boot-shell';
            document.documentElement.classList.add('dark');
            document.body.appendChild(probe);
            const shell = getComputedStyle(probe).backgroundColor;
            const canvas = getComputedStyle(document.body).backgroundColor;
            probe.remove();
            document.documentElement.classList.remove('dark');

            return shell === canvas;
        })(),
    }));
    check('dark boot shell matches the dark canvas', report.bootShell.dark);
    await page.goto(`${origin}/app/users`);
    await page.getByRole('cell', { name: 'Ana Souza' }).waitFor();
    report.usersAudit = await auditContrast(page);
    check(
        'users list text meets light WCAG AA contrast',
        report.usersAudit.failures.length === 0,
    );
    check(
        'icon-only delete action is neutral at rest',
        await page
            .getByRole('button', { name: 'Excluir' })
            .first()
            .evaluate(
                (el) =>
                    !getComputedStyle(el).backgroundColor.includes(
                        'oklch(0.48',
                    ),
            ),
    );
    await page.screenshot({
        path: resolve(output, 'users-light.png'),
        fullPage: true,
        animations: 'disabled',
    });
    await page.goto(`${origin}/app/users/create`);
    await page.getByRole('heading', { name: 'Novo usuário' }).waitFor();
    report.createAudit = await auditContrast(page);
    check(
        'create form text meets light WCAG AA contrast',
        report.createAudit.failures.length === 0,
    );
    await page.screenshot({
        path: resolve(output, 'users-create-light.png'),
        fullPage: true,
        animations: 'disabled',
    });
    await page.goto(`${origin}/settings/profile`);
    await page.getByRole('heading', { name: 'Configurações' }).waitFor();
    report.profileAudit = await auditContrast(page);
    check(
        'settings text meets light WCAG AA contrast',
        report.profileAudit.failures.length === 0,
    );
    await page.screenshot({
        path: resolve(output, 'settings-light.png'),
        fullPage: true,
        animations: 'disabled',
    });
    await page.goto(`${origin}/login`);
    await page.getByRole('textbox', { name: 'E-mail', exact: true }).waitFor();
    report.loginLightAudit = await auditContrast(page);
    check(
        'login text meets light WCAG AA contrast',
        report.loginLightAudit.failures.length === 0,
    );
    await page.screenshot({
        path: resolve(output, 'login-light.png'),
        animations: 'disabled',
    });
    await page.evaluate(() => {
        document.querySelector('form').noValidate = true;
    });
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await page.getByText('Informe a senha.').waitFor();
    report.loginErrors = await page.evaluate(() =>
        [...document.querySelectorAll('main input, form input')]
            .filter((input) => input.getAttribute('aria-invalid') === 'true')
            .map((input) => ({
                name: input.name,
                message:
                    document.getElementById(
                        input.getAttribute('aria-describedby') ?? '',
                    )?.textContent ?? null,
            })),
    );
    report.loginErrorsAudit = await auditContrast(page);
    check(
        'login errors are tied to their fields',
        JSON.stringify(report.loginErrors) ===
            JSON.stringify([
                {
                    name: 'email',
                    message: 'Estas credenciais n\u00e3o conferem.',
                },
                { name: 'password', message: 'Informe a senha.' },
            ]),
    );
    check(
        'login error text meets light WCAG AA contrast',
        report.loginErrorsAudit.failures.length === 0,
    );
    await page.screenshot({
        path: resolve(output, 'login-errors-light.png'),
        animations: 'disabled',
    });
    await page.goto(`${origin}/dashboard?mode=long`);
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    report.longBrand = {
        fits: await page
            .locator('[data-slot="sidebar"]')
            .first()
            .evaluate((aside) => aside.scrollWidth <= aside.clientWidth),
    };
    check(
        'long brand name does not overflow the sidebar',
        report.longBrand.fits,
    );
    await page.goto(`${origin}/dashboard?mode=widelogo`);
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    await page
        .locator('[data-slot="sidebar"] img[src="/synthetic/wide-logo.svg"]')
        .evaluate((img) => img.decode());
    report.wideLogo = await page
        .locator('[data-slot="sidebar"]')
        .first()
        .evaluate((aside) => {
            const img = aside.querySelector(
                'img[src="/synthetic/wide-logo.svg"]',
            );
            const logo = img.getBoundingClientRect();
            const box = aside.getBoundingClientRect();
            const fit = getComputedStyle(img).objectFit;

            return {
                fits:
                    aside.scrollWidth <= aside.clientWidth &&
                    logo.right <= box.right &&
                    logo.width > 0 &&
                    (['contain', 'scale-down'].includes(fit) ||
                        Math.abs(
                            logo.width / logo.height -
                                img.naturalWidth / img.naturalHeight,
                        ) < 0.3),
                width: logo.width,
                height: logo.height,
                fit,
            };
        });
    check(
        'wide file logo stays inside the sidebar undistorted',
        report.wideLogo.fits,
    );
    await page.goto(`${origin}/dashboard`);
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    await page.getByRole('button', { name: 'Ver todos', exact: true }).click();
    check(
        'resource list expands with the existing resources',
        (await page
            .getByRole('button', { name: 'Ver menos', exact: true })
            .count()) === 1,
    );
    await page.getByRole('button', { name: 'Ver menos', exact: true }).click();
    // Drag the rail 60px outward: the width must survive a reload.
    const rail = await page.locator('[data-sidebar="rail"]').boundingBox();
    const railX = rail.x + rail.width / 2;
    const railY = rail.y + rail.height / 2;
    await page.mouse.move(railX, railY);
    await page.mouse.down();
    await page.mouse.move(railX + 30, railY, { steps: 5 });
    await page.mouse.move(railX + 60, railY, { steps: 5 });
    await page.mouse.up();
    await page.waitForFunction(
        () =>
            localStorage.getItem('sidebar:width') !== null &&
            Math.round(
                document
                    .querySelector('[data-slot="sidebar"]')
                    .getBoundingClientRect().width,
            ) === 300,
    );
    await page.reload();
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    check(
        'resized sidebar width persists in the browser',
        (await sidebarWidth()) === 300 &&
            (await page.evaluate(() =>
                localStorage.getItem('sidebar:width'),
            )) === '300',
    );
    await page.evaluate(() => localStorage.setItem('sidebar:width', '9999'));
    await page.reload();
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    check(
        'out-of-range saved width falls back to the default',
        (await sidebarWidth()) === 240,
    );
    await page.evaluate(() => {
        localStorage.setItem('sidebar:width', '320');
        localStorage.setItem('sidebar:collapsed', 'true');
    });
    await page.reload();
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    await page
        .getByRole('button', { name: 'Abrir menu do usuário', exact: true })
        .click();
    await page
        .getByRole('menuitem', { name: 'Restaurar sidebar', exact: true })
        .click();
    await page.waitForFunction(
        () =>
            Math.round(
                document
                    .querySelector('[data-slot="sidebar"]')
                    .getBoundingClientRect().width,
            ) === 240,
    );
    check(
        'account menu restores the sidebar defaults',
        (await page.evaluate(
            () =>
                localStorage.getItem('sidebar:width') === null &&
                localStorage.getItem('sidebar:collapsed') === 'false',
        )) &&
            (await page
                .getByRole('button', { name: 'Encolher menu', exact: true })
                .count()) === 1,
    );
    const activeModule = page
        .getByRole('navigation', { name: 'Módulos', exact: true })
        .getByRole('link', { name: 'Gestão', exact: true });
    check(
        'active header module is a raised round pill',
        (await activeModule.getAttribute('aria-current')) === 'page' &&
            (await activeModule
                .locator('span[aria-hidden="true"]')
                .evaluate((pill) => {
                    const style = getComputedStyle(pill);

                    return (
                        parseFloat(style.borderTopLeftRadius) >=
                            pill.getBoundingClientRect().height / 2 &&
                        style.backgroundColor !== 'rgba(0, 0, 0, 0)' &&
                        style.boxShadow !== 'none'
                    );
                })),
    );
    await page
        .getByRole('button', { name: 'Encolher menu', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Expandir menu', exact: true })
        .waitFor();
    // Motion applies the width on the next frame, even with reduced motion.
    await page.waitForFunction(
        () =>
            Math.round(
                document
                    .querySelector('[data-slot="sidebar"]')
                    .getBoundingClientRect().width,
            ) === 80,
    );
    check(
        'collapsed sidebar is an 80px icon rail',
        (await sidebarWidth()) === 80,
    );
    check(
        'collapsed sidebar centers the brand mark',
        await page
            .locator(
                '[data-slot="sidebar"] a[aria-label="Ir ao dashboard"] svg',
            )
            .evaluate((svg) => {
                const rail = svg
                    .closest('[data-slot="sidebar"]')
                    .getBoundingClientRect();
                const mark = svg.getBoundingClientRect();

                return (
                    Math.abs(
                        mark.left +
                            mark.width / 2 -
                            (rail.left + rail.width / 2),
                    ) <= 2
                );
            }),
    );
    check(
        'collapsed rail keeps accessible navigation links',
        (await sidebar.getByRole('link').count()) >= 8 &&
            (await sidebar
                .getByRole('link', { name: 'Usuários', exact: true })
                .count()) === 1,
    );
    await sidebar.getByRole('link', { name: 'Usuários', exact: true }).hover();
    await page.getByRole('tooltip', { name: 'Usuários' }).waitFor();
    check(
        'collapsed rail names its icons in a tooltip',
        (await page.getByRole('tooltip', { name: 'Usuários' }).count()) === 1,
    );
    await page.mouse.move(900, 500);
    await page.reload();
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    check(
        'collapse persists across reload',
        (await page
            .getByRole('button', { name: 'Expandir menu', exact: true })
            .count()) === 1,
    );
    await page
        .getByRole('button', { name: 'Expandir menu', exact: true })
        .click();
    await page.getByRole('tab', { name: 'External APIs', exact: true }).click();
    check(
        'module filter tab changes the resource panel',
        (await page
            .getByRole('tab', { name: 'External APIs', exact: true })
            .getAttribute('aria-selected')) === 'true' &&
            (await page
                .getByRole('tabpanel')
                .getByText('Recursos disponíveis', { exact: true })
                .count()) === 1,
    );
    await page
        .getByRole('tab', { name: 'Todos os módulos', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Ativar modo escuro', exact: true })
        .click();
    check(
        'dark theme is applied',
        await page
            .locator('html')
            .evaluate((el) => el.classList.contains('dark')),
    );
    await page.screenshot({
        path: resolve(output, 'desktop-dark.png'),
        fullPage: true,
        animations: 'disabled',
    });
    const darkAudit = await auditContrast(page);
    report.darkAudit = darkAudit;
    const hierarchy = await page.evaluate(() => {
        const panel = document.querySelector('main section');
        const row = [...document.querySelectorAll('main section a')].find(
            (link) => link.textContent.includes('Disponível'),
        );
        // Computed colors may be oklch(); paint them to read sRGB channels.
        const ctx = document
            .createElement('canvas')
            .getContext('2d', { willReadFrequently: true });
        ctx.fillStyle = getComputedStyle(document.body).backgroundColor;
        ctx.fillRect(0, 0, 1, 1);
        const channels = Array.from(ctx.getImageData(0, 0, 1, 1).data).slice(
            0,
            3,
        );

        return {
            canvasChroma: Math.max(...channels) - Math.min(...channels),
            panelBorder: getComputedStyle(panel).borderBottomColor,
            rowBorder: getComputedStyle(row).borderBottomColor,
            rowRadius: getComputedStyle(row).borderBottomLeftRadius,
            metricSurfaces: [...document.querySelectorAll('main section')]
                .filter(
                    (section) =>
                        section.querySelector('h2')?.textContent ===
                            'Módulos' ||
                        (section.querySelector('h2')?.textContent ===
                            'Sua conta' &&
                            section.querySelector('p')?.textContent ===
                                'Verificada'),
                )
                .map((section) => getComputedStyle(section).backgroundColor),
            cardSurface: getComputedStyle(panel).backgroundColor,
        };
    });
    report.darkHierarchy = hierarchy;
    // Spec 2.2: canvas oklch(0.19 0.01 hue) is a brand-tinted neutral (about 8 in sRGB spread).
    check(
        'dark canvas uses restrained neutral color',
        hierarchy.canvasChroma <= 10,
    );
    check(
        'dark list separators are quieter than panel outlines',
        hierarchy.rowBorder !== hierarchy.panelBorder &&
            hierarchy.rowRadius === '0px',
    );
    check(
        'dark metric panels share the card surface',
        hierarchy.metricSurfaces.length === 2 &&
            hierarchy.metricSurfaces.every(
                (surface) => surface === hierarchy.cardSurface,
            ),
    );
    check('dark cards are elevated above the canvas', darkAudit.elevated);
    check(
        'dark native controls use the dark color scheme',
        darkAudit.colorScheme === 'dark',
    );
    check(
        'visible dark text meets WCAG AA contrast',
        darkAudit.failures.length === 0,
    );
    await page
        .getByRole('button', { name: 'Menu da conta', exact: true })
        .click();
    await page.getByRole('menu').waitFor();
    report.darkMenuAudit = await auditContrast(page);
    check(
        'dark account menu text meets WCAG AA contrast',
        report.darkMenuAudit.failures.length === 0,
    );
    await page.keyboard.press('Escape');
    await page.getByRole('menu').waitFor({ state: 'hidden' });
    await page
        .getByRole('button', { name: 'Ativar modo claro', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Menu da conta', exact: true })
        .click();
    check(
        'account menu still opens',
        (await page.getByRole('menu').count()) === 1,
    );
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('menu').waitFor({ state: 'hidden' });
    check(
        'mobile has no horizontal overflow',
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    await page.screenshot({
        path: resolve(output, 'mobile-light.png'),
        fullPage: true,
        animations: 'disabled',
    });
    await page
        .getByRole('button', { name: 'Ativar modo escuro', exact: true })
        .click();
    check(
        'mobile dark theme stays within viewport',
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    await page.screenshot({
        path: resolve(output, 'mobile-dark.png'),
        animations: 'disabled',
    });
    await page
        .getByRole('button', { name: 'Ativar modo claro', exact: true })
        .click();
    await page.getByRole('button', { name: 'Abrir menu', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await dialog.waitFor();
    check(
        'mobile sidebar is a modal navigation sheet',
        (await dialog
            .getByRole('link', { name: 'Usuários', exact: true })
            .count()) === 1,
    );
    await page.screenshot({
        path: resolve(output, 'mobile-menu.png'),
        fullPage: true,
        animations: 'disabled',
    });
    check(
        'mobile navigation has an accessible description',
        await dialog.evaluate((element) => {
            const description = element.getAttribute('aria-describedby');

            return Boolean(
                description &&
                document.getElementById(description)?.textContent.trim(),
            );
        }),
    );
    await page.keyboard.press('Escape');
    await dialog.waitFor({ state: 'hidden' });
    // Radix FocusScope restores focus in a setTimeout after unmounting.
    await page
        .waitForFunction(
            () =>
                document.activeElement?.getAttribute('aria-label') ===
                'Abrir menu',
            undefined,
            { timeout: 2000 },
        )
        .catch(() => {});
    check(
        'mobile navigation restores focus to opener',
        await page
            .getByRole('button', { name: 'Abrir menu', exact: true })
            .evaluate((el) => el === document.activeElement),
    );
    check('Escape closes mobile navigation', (await dialog.count()) === 0);
    await page
        .getByRole('button', { name: 'Trocar módulo: Gestão', exact: true })
        .click();
    check(
        'mobile module dropdown opens',
        (await page
            .getByRole('menuitemradio', { name: 'External APIs', exact: true })
            .count()) === 1 &&
            (await page
                .getByRole('menuitemradio', { name: 'Gestão', exact: true })
                .getAttribute('aria-checked')) === 'true',
    );
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 1440, height: 900 });

    for (const mode of ['header', 'flat', 'empty']) {
        await page.goto(`${origin}/dashboard?mode=${mode}`);
        await page.getByRole('heading', { name: 'Visão geral' }).waitFor();

        if (mode === 'flat') {
            check(
                'flat navigation keeps section labels static',
                (await page
                    .locator('[data-slot="sidebar"]')
                    .getByRole('button', { name: 'Plataforma', exact: true })
                    .count()) === 0,
            );
        }

        check(
            `${mode} layout renders without horizontal overflow`,
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        );
    }

    await page.goto(`${origin}/app/users`);
    await page
        .getByRole('heading', { name: 'Usuários', exact: true })
        .waitFor();
    check(
        'section navigation remains active on resource URLs',
        (await page
            .locator('[data-slot="sidebar"] a[href="/app/users"]')
            .getAttribute('aria-current')) === 'page',
    );
    await page
        .getByRole('button', { name: 'Ativar modo escuro', exact: true })
        .click();
    await page.goto(`${origin}/admin/dev/design-system`);
    await page.locator('main input:not([disabled])').first().waitFor();
    check(
        'dark preference persists on another page',
        await page
            .locator('html')
            .evaluate((el) => el.classList.contains('dark')),
    );
    const componentAudit = await auditContrast(page);
    report.componentAudit = componentAudit;
    check(
        'dark field borders have at least 3:1 contrast on hover',
        componentAudit.fieldBorderContrast >= 3,
    );
    check(
        'dark placeholders have at least 4.5:1 contrast',
        componentAudit.placeholderContrast >= 4.5,
    );
    check(
        'shared component text meets dark WCAG AA contrast',
        componentAudit.failures.length === 0,
    );
    const field = page.locator('main input:not([disabled])').first();
    await field.focus();
    check(
        'dark form control retains visible focus',
        await field.evaluate((el) => getComputedStyle(el).boxShadow !== 'none'),
    );
    await field.scrollIntoViewIfNeeded();
    await page.screenshot({
        path: resolve(output, 'components-dark.png'),
        animations: 'disabled',
    });
    await page.goto(`${origin}/login`);
    await page.getByRole('textbox', { name: 'E-mail', exact: true }).waitFor();
    const loginAudit = await auditContrast(page);
    report.loginAudit = loginAudit;
    check(
        'login text meets dark WCAG AA contrast',
        loginAudit.failures.length === 0,
    );
    check('login uses dark native fields', loginAudit.colorScheme === 'dark');
    await page.screenshot({
        path: resolve(output, 'login-dark.png'),
        animations: 'disabled',
    });
    await page.goto(`${origin}/dashboard`);
    await page.getByRole('heading', { name: 'Vis\u00e3o geral' }).waitFor();
    await page.evaluate(() => localStorage.setItem('appearance', 'system'));
    await page.reload();
    await page.getByRole('heading', { name: 'Vis\u00e3o geral' }).waitFor();
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.waitForFunction(() =>
        document.documentElement.classList.contains('dark'),
    );
    check(
        'system theme updates the toggle label',
        (await page
            .getByRole('button', { name: 'Ativar modo claro', exact: true })
            .count()) === 1,
    );
    await page
        .getByRole('button', { name: 'Ativar modo claro', exact: true })
        .click();
    await page.emulateMedia({ colorScheme: 'light' });
    await page.emulateMedia({ colorScheme: 'dark' });
    check(
        'explicit light preference overrides dark system preference',
        await page
            .locator('html')
            .evaluate((el) => !el.classList.contains('dark')),
    );
    await page.addInitScript(() => {
        Object.defineProperty(Storage.prototype, 'getItem', {
            value: () => {
                throw new Error('Synthetic blocked browser storage');
            },
        });
        Object.defineProperty(Storage.prototype, 'setItem', {
            value: () => {
                throw new Error('Synthetic blocked browser storage');
            },
        });
    });
    await page.goto(`${origin}/dashboard`);
    await page
        .getByRole('button', { name: 'Ativar modo claro', exact: true })
        .waitFor();
    await page
        .getByRole('button', { name: 'Ativar modo claro', exact: true })
        .click();
    check(
        'theme stays usable with blocked browser storage',
        (await page
            .getByRole('button', { name: 'Ativar modo escuro', exact: true })
            .count()) === 1,
    );
    const huePage = await context.newPage();
    huePage.on('pageerror', (error) => report.errors.push(error.message));
    report.hueAudits = [];

    for (const theme of ['light', 'dark']) {
        await huePage.goto(`${origin}/dashboard`);
        await huePage.evaluate(
            (value) => localStorage.setItem('appearance', value),
            theme,
        );

        for (const hue of [30, 150, 280, 355]) {
            for (const [path, ready] of [
                [
                    '/dashboard',
                    (p) =>
                        p.getByRole('heading', {
                            name: 'Visão geral',
                            exact: true,
                        }),
                ],
                [
                    '/app/users',
                    (p) => p.getByRole('cell', { name: 'Ana Souza' }),
                ],
                [
                    '/app/users/create',
                    (p) => p.getByRole('heading', { name: 'Novo usuário' }),
                ],
            ]) {
                await huePage.goto(`${origin}${path}?hue=${hue}`);
                await ready(huePage).waitFor();
                const audit = await auditContrast(huePage);
                report.hueAudits.push({
                    hue,
                    theme,
                    path,
                    applied: await huePage.evaluate(
                        ([value, dark]) =>
                            getComputedStyle(document.documentElement)
                                .getPropertyValue('--brand-hue')
                                .trim() === String(value) &&
                            document.documentElement.classList.contains(
                                'dark',
                            ) === dark,
                        [hue, theme === 'dark'],
                    ),
                    failures: audit.failures,
                    fieldBorderContrast: audit.fieldBorderContrast,
                });
            }
        }
    }

    await huePage.close();
    check(
        'text meets WCAG AA contrast with other brand hues',
        report.hueAudits.every(
            (audit) => audit.applied && audit.failures.length === 0,
        ),
    );
    check(
        'field hover borders keep 3:1 contrast with other brand hues',
        report.hueAudits.every(
            (audit) =>
                audit.fieldBorderContrast === null ||
                audit.fieldBorderContrast >= 3,
        ),
    );
    check('no browser errors', report.errors.length === 0);
    check(
        'all requests handled locally with no backend or external requests',
        report.blocked.length === 0 &&
            report.mutations.length === 0 &&
            report.backendRequests === 0,
    );
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = error.stack;

    throw error;
} finally {
    await browser?.close();
    await new Promise((resolveClosed) => server.close(resolveClosed));
    writeFileSync(
        resolve(output, 'report.json'),
        JSON.stringify(report, null, 2),
    );
    process.stdout.write(JSON.stringify(report));
}

import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const ownership = JSON.parse(
    readFileSync('storage/framework/testing/datatable/http-copy.json', 'utf8'),
);
const source = ownership.path;

for (const file of [
    'datatable-six-pages-server.php',
    'datatable-six-pages-router.php',
]) {
    copyFileSync(
        resolve('tests/Fixtures/Frontend', file),
        resolve(source, 'tests/Fixtures/Frontend', file),
    );
}

const output = resolve('storage/framework/testing/datatable/report/six-pages');
mkdirSync(output, { recursive: true });
const environment = Object.fromEntries(
    Object.entries(process.env).filter(([key]) =>
        [
            'SYSTEMROOT',
            'WINDIR',
            'COMSPEC',
            'PATH',
            'PATHEXT',
            'TEMP',
            'TMP',
        ].includes(key.toUpperCase()),
    ),
);
Object.assign(environment, {
    AUTHORITY_MYSQL_TESTS: '1',
    AUTHORITY_HTTP_TESTS: '1',
    AUTHORITY_HTTP_HOST: 'www2.start_kit.com.br',
    AUTHORITY_MYSQLD_BINARY: process.env.DATATABLE_MYSQLD_BINARY,
    DATATABLE_SIX_BROWSER: '1',
    OPS004_SOURCE_TOKEN: ownership.token,
    PHP_INI_SCAN_DIR: resolve(source, 'tests'),
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: ':memory:',
    DB_URL: '',
    BCRYPT_ROUNDS: '4',
});
const launcher = spawn(
    process.env.DATATABLE_PHP_BINARY,
    ['tests/Fixtures/Frontend/datatable-six-pages-server.php'],
    {
        cwd: source,
        env: environment,
        windowsHide: true,
        stdio: ['pipe', 'pipe', 'pipe'],
    },
);
let stdout = '';
let stderr = '';
let browser;
let page;
launcher.stdout.on('data', (data) => {
    stdout += data;
});
launcher.stderr.on('data', (data) => {
    stderr += data;
});
const report = {
    status: 'running',
    pages: [],
    checks: [],
    errors: [],
    requests: [],
    responses: [],
    forbidden: [],
};
const check = (name, value) => {
    assert(value, name);
    report.checks.push(name);
};
const screens = [
    {
        id: 'users',
        path: '/users',
        api: '/api/v1/users',
        placeholder: 'Buscar',
        filter: 'Nome',
    },
    {
        id: 'clients',
        path: '/clients',
        api: '/api/v1/clients',
        placeholder: 'Buscar clients...',
        filter: 'Name',
    },
    {
        id: 'roles',
        path: '/roles',
        api: '/api/v1/roles',
        placeholder: 'Buscar por nome ou slug...',
    },
    {
        id: 'permissions',
        path: '/permissions',
        api: '/api/v1/permissions',
        placeholder: 'Buscar por nome, slug ou grupo...',
    },
    {
        id: 'activity-log',
        path: '/activity-log',
        api: '/api/v1/activity-log',
        placeholder: 'Buscar por descricao ou evento...',
        filter: 'Causador',
    },
    {
        id: 'dev-inline-cell-edit-users',
        path: '/admin/dev/components/inline-cell-edit',
        api: '/api/v1/users',
        placeholder: 'Buscar usuarios',
    },
];
const dataReady = async () => {
    await page.locator('tbody [data-column-id]').first().waitFor();
    await page.waitForLoadState('networkidle');
};
const screenshot = async (name) => {
    await page.screenshot({
        path: resolve(output, name),
        animations: 'disabled',
        fullPage: true,
    });
};
const setTheme = async (mode) => {
    const desired = mode === 'dark';

    if (
        (await page.evaluate(() =>
            document.documentElement.classList.contains('dark'),
        )) !== desired
    ) {
        const button = page
            .locator('button[aria-label^="Ativar modo"]')
            .first();
        await button.click();
        await page.waitForFunction(
            (dark) =>
                document.documentElement.classList.contains('dark') === dark,
            desired,
            { timeout: 5000 },
        );
    }

    return page.evaluate(() => ({
        dark: document.documentElement.classList.contains('dark'),
        background: getComputedStyle(document.documentElement)
            .getPropertyValue('--background')
            .trim(),
        color: getComputedStyle(document.body).color,
        local: localStorage.getItem('appearance'),
    }));
};

try {
    console.log(JSON.stringify({ phase: 'disposable-runtime-starting' }));
    const ready = await new Promise((done, reject) => {
        const timeout = setTimeout(
            () => reject(new Error('Disposable startup timeout: ' + stderr)),
            180000,
        );
        launcher.on('exit', (code) => {
            clearTimeout(timeout);
            reject(
                new Error('Disposable launcher exited ' + code + ': ' + stderr),
            );
        });
        launcher.stdout.on('data', () => {
            const line = stdout
                .split('\n')
                .find((item) => item.startsWith('{"url"'));

            if (line) {
                clearTimeout(timeout);
                done(JSON.parse(line));
            }
        });
    });
    const origin = ready.url;
    report.origin = origin;
    const { chromium } = await import(
        pathToFileURL(process.env.DATATABLE_PLAYWRIGHT_MODULE).href
    );
    browser = await chromium.launch({
        executablePath: process.env.DATATABLE_BROWSER_BINARY,
        headless: true,
        args: [
            '--no-proxy-server',
            '--host-resolver-rules=MAP www2.start_kit.com.br 127.0.0.1',
        ],
    });
    const context = await browser.newContext({
        serviceWorkers: 'block',
        viewport: { width: 1440, height: 1000 },
    });
    const reads = new Set([
        '/',
        '/login',
        '/dashboard',
        '/api/v1/notifications/unread',
        '/api/v1/notifications/unread-count',
        ...screens.flatMap((screen) => [screen.path, screen.api]),
    ]);
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const allowed =
            url.origin === origin &&
            ((request.method() === 'GET' &&
                (reads.has(url.pathname) ||
                    url.pathname.startsWith('/build/') ||
                    url.pathname.startsWith('/src/') ||
                    url.pathname.startsWith('/favicon'))) ||
                (request.method() === 'POST' &&
                    ['/login', '/logout'].includes(url.pathname)));

        if (allowed) {
            report.requests.push({
                method: request.method(),
                path: url.pathname,
                query: url.search,
            });

            return route.continue();
        }

        report.forbidden.push({ method: request.method(), url: url.href });

        return route.abort();
    });
    page = await context.newPage();
    page.setDefaultTimeout(20000);
    page.on('pageerror', (error) => report.errors.push(error.message));
    page.on('response', (response) => {
        const url = new URL(response.url());

        if (url.pathname.startsWith('/api/')) {
            report.responses.push({
                path: url.pathname,
                status: response.status(),
                query: url.search,
            });
        }
    });
    const loginResponse = await page.goto(`${origin}/login`);
    check('real-login-page-200', loginResponse.status() === 200);
    await page.locator('input[name=email]').fill(ready.actor.email);
    await page.locator('input[name=password]').fill(ready.actor.password);
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.locator('[data-test=login-button]').click(),
    ]);
    check('real-session-authenticated', true);

    for (const screen of screens) {
        console.log(
            JSON.stringify({ phase: 'page-starting', path: screen.path }),
        );
        const result = { id: screen.id, path: screen.path, checks: [] };
        report.pages.push(result);
        const localCheck = (name, value) => {
            check(`${screen.id}:${name}`, value);
            result.checks.push(name);
        };

        try {
            await page.setViewportSize({ width: 1440, height: 1000 });
            const navigation = await page.goto(`${origin}${screen.path}`);
            localCheck('page-200', navigation.status() === 200);
            await dataReady();
            result.rows = await page
                .locator('tbody tr:has([data-column-id])')
                .count();
            result.headers = await page.locator('thead th').allTextContents();
            localCheck(
                'real-json-and-data-rows',
                result.rows > 0 &&
                    report.responses.some(
                        (response) =>
                            response.path === screen.api &&
                            response.status === 200,
                    ),
            );
            localCheck(
                'headers-and-toolbar',
                result.headers.some((label) => label.trim()) &&
                    (await page
                        .getByRole('button', {
                            name: 'Configurar colunas',
                            exact: true,
                        })
                        .count()) === 1,
            );

            result.light = await setTheme('light');
            localCheck(
                'light-theme-rendered',
                result.light.dark === false &&
                    result.light.background !== '#121416',
            );

            await screenshot(`${screen.id}-desktop-light.png`);
            await page
                .getByRole('button', { name: 'Buscar', exact: true })
                .click();
            const search = page.getByRole('textbox', {
                name: screen.placeholder,
                exact: true,
            });
            localCheck(
                'search-accessible-placeholder',
                (await search.getAttribute('placeholder')) ===
                    screen.placeholder,
            );
            const searched = page.waitForResponse((response) => {
                const url = new URL(response.url());

                return (
                    url.pathname === screen.api &&
                    url.searchParams.get('search') === 'QA' &&
                    response.status() === 200
                );
            });
            await search.fill('QA');
            await searched;
            await dataReady();
            localCheck('search-real-api', true);
            const cleared = page.waitForResponse((response) => {
                const url = new URL(response.url());

                return (
                    url.pathname === screen.api &&
                    !url.searchParams.has('search') &&
                    response.status() === 200
                );
            });
            await page
                .getByRole('button', { name: 'Limpar busca', exact: true })
                .click();
            await cleared;
            await dataReady();

            if (screen.filter) {
                await page
                    .getByRole('button', { name: 'Filtros', exact: true })
                    .click();
                const panel = page.getByRole('dialog').last();
                await panel
                    .getByRole('button', { name: screen.filter, exact: true })
                    .click();
                const filterInput = panel.getByRole('textbox', {
                    name: screen.filter,
                    exact: true,
                });
                localCheck(
                    'filter-contextual-placeholder',
                    Boolean(await filterInput.getAttribute('placeholder')),
                );
                await page.keyboard.press('Escape');
            }

            await page.waitForLoadState('networkidle');
            const before = report.requests.filter(
                (request) => request.path === screen.api,
            ).length;
            const resize = page
                .getByRole('separator', { name: /^Largura de/ })
                .first();
            const oldWidth = Number(await resize.getAttribute('aria-valuenow'));
            await resize.focus();
            await page.keyboard.press('ArrowRight');
            await page.waitForFunction(
                ({ label, size }) =>
                    [...document.querySelectorAll('[role=separator]')].some(
                        (element) =>
                            element.getAttribute('aria-label') === label &&
                            Number(element.getAttribute('aria-valuenow')) ===
                                size,
                    ),
                {
                    label: await resize.getAttribute('aria-label'),
                    size: oldWidth + 10,
                },
            );
            localCheck('keyboard-column-resize', true);
            await page
                .getByRole('button', {
                    name: 'Configurar colunas',
                    exact: true,
                })
                .click();
            const dialog = page.getByRole('dialog');
            const rows = dialog.locator('li');
            localCheck(
                'column-controls-and-protection',
                (await rows.count()) > 0 &&
                    (await rows.getByRole('checkbox').count()) ===
                        (await rows.count()),
            );
            const firstLabel = (
                await rows.first().locator('label').textContent()
            ).trim();
            const nextDensity =
                screen.id === 'dev-inline-cell-edit-users'
                    ? 'Confortável'
                    : 'Compacta';
            await dialog
                .getByRole('button', { name: nextDensity, exact: true })
                .click();

            if ((await rows.count()) > 1) {
                await rows
                    .nth(1)
                    .getByRole('button', { name: /^Mover .+ para cima$/ })
                    .click();
            }

            await dialog
                .getByRole('combobox', {
                    name: `Fixação de ${firstLabel}`,
                    exact: true,
                })
                .click();
            await page
                .getByRole('option', { name: 'Fixar à esquerda', exact: true })
                .click();
            await dialog
                .getByRole('button', {
                    name: 'Ajustar todas ao conteúdo',
                    exact: true,
                })
                .click();
            await page.keyboard.press('Escape');
            await page.waitForLoadState('networkidle');
            localCheck(
                'layout-and-autosize-no-get',
                report.requests.filter((request) => request.path === screen.api)
                    .length === before,
            );
            const preferenceKey = `datatable-preferences:v1:${ready.actor.id}:${screen.id}`;
            result.preference = await page.evaluate(
                (key) => JSON.parse(localStorage.getItem(key)),
                preferenceKey,
            );
            localCheck(
                'owned-layout-preference-saved',
                result.preference &&
                    Object.keys(result.preference.columnSizing).length > 0 &&
                    result.preference.columnPinning.left.length > 0,
            );
            await page.reload();
            await dataReady();
            await page
                .getByRole('button', {
                    name: 'Configurar colunas',
                    exact: true,
                })
                .click();
            localCheck(
                'reload-restores-density',
                (await page
                    .getByRole('button', { name: nextDensity, exact: true })
                    .getAttribute('aria-pressed')) === 'true',
            );
            await page.waitForFunction(
                () =>
                    getComputedStyle(document.querySelector('[role="dialog"]'))
                        .opacity === '1',
            );
            await screenshot(`${screen.id}-configure-light.png`);
            await page
                .getByRole('button', { name: 'Restaurar layout', exact: true })
                .click();
            await page.keyboard.press('Escape');

            result.dark = await setTheme('dark');
            localCheck(
                'dark-theme-rendered',
                result.dark.dark === true &&
                    result.dark.background === '#121416' &&
                    result.dark.local === 'dark',
            );

            await screenshot(`${screen.id}-desktop-dark.png`);
            await page.setViewportSize({ width: 390, height: 844 });
            await page.locator('table').scrollIntoViewIfNeeded();
            localCheck(
                'mobile-no-page-overflow',
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <= innerWidth + 1,
                ),
            );
            await screenshot(`${screen.id}-mobile-dark.png`);
            result.status = 'passed';
            console.log(
                JSON.stringify({
                    phase: 'page-passed',
                    path: screen.path,
                    checks: result.checks.length,
                    rows: result.rows,
                }),
            );
        } catch (error) {
            result.status = 'failed';
            result.failure = String(error.stack ?? error);
            result.themeDiagnostics = await page
                .locator('button[aria-label]')
                .evaluateAll((elements) =>
                    elements.map((element) =>
                        element.getAttribute('aria-label'),
                    ),
                );
            await screenshot(`${screen.id}-failure.png`);
            console.log(
                JSON.stringify({
                    phase: 'page-failed',
                    path: screen.path,
                    failure: result.failure,
                }),
            );
        }
    }

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page
        .getByRole('button', { name: 'Menu da conta', exact: true })
        .click();
    await page.locator('[data-test=logout-button]').click();
    await page.waitForURL((url) => ['/', '/login'].includes(url.pathname));
    check('real-logout', true);
    check(
        'only-read-login-logout',
        report.requests.every(
            (request) =>
                request.method === 'GET' ||
                (request.method === 'POST' &&
                    ['/login', '/logout'].includes(request.path)),
        ),
    );
    check('zero-page-errors', report.errors.length === 0);
    check('zero-forbidden-network', report.forbidden.length === 0);
    check(
        'all-real-api-responses-200',
        report.responses.length > 0 &&
            report.responses.every((response) => response.status === 200),
    );
    check(
        'all-six-pages-passed',
        report.pages.length === 6 &&
            report.pages.every((screen) => screen.status === 'passed'),
    );
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = String(error.stack ?? error);

    if (page) {
        await screenshot('failure.png').catch(() => undefined);
    }
} finally {
    await browser?.close();

    if (launcher.exitCode === null) {
        launcher.stdin.write('stop\n');
        await new Promise((done) => launcher.once('exit', done));
    }

    report.launcherExit = launcher.exitCode;
    report.ledger = stdout
        .split('\n')
        .filter((line) => line.includes('business_counts_unchanged'))
        .map((line) => JSON.parse(line));
    report.launcherErrors = stderr;

    if (
        report.status === 'passed' &&
        (launcher.exitCode !== 0 ||
            report.ledger[0]?.business_counts_unchanged !== true)
    ) {
        report.status = 'failed';
        report.failure = 'Disposable ledger or cleanup did not pass.';
    }

    writeFileSync(
        resolve(output, 'browser.json'),
        JSON.stringify(report, null, 2),
    );
}

console.log(
    JSON.stringify({
        status: report.status,
        checks: report.checks.length,
        pages: report.pages.map(({ id, status, rows }) => ({
            id,
            status,
            rows,
        })),
        errors: report.errors,
        forbidden: report.forbidden,
        failure: report.failure,
        launcherExit: report.launcherExit,
        launcherErrors: report.launcherErrors,
        ledger: report.ledger,
    }),
);

if (report.status !== 'passed') {
    process.exitCode = 1;
}

import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const source = JSON.parse(
    readFileSync('storage/framework/testing/datatable/http-copy.json', 'utf8'),
).path;
assert(
    !readFileSync(
        resolve(source, 'tests/authority-http-server.php'),
        'utf8',
    ).includes('datatable-http-browser'),
);
const output = resolve('storage/framework/testing/datatable/report');
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
    PHP_INI_SCAN_DIR: resolve(source, 'tests'),
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: ':memory:',
    DB_URL: '',
});
const launcher = spawn(
    process.env.DATATABLE_PHP_BINARY,
    ['tests/authority-http-server.php'],
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
launcher.stdout.on('data', (data) => {
    stdout += data;
});
launcher.stderr.on('data', (data) => {
    stderr += data;
});
const report = {
    status: 'running',
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

try {
    const ready = await new Promise((done, reject) => {
        const timeout = setTimeout(
            () =>
                reject(
                    new Error('Disposable HTTP startup timed out: ' + stderr),
                ),
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
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const allowedRead =
            request.method() === 'GET' &&
            ([
                '/',
                '/login',
                '/dashboard',
                '/roles',
                '/permissions',
                '/api/v1/roles',
                '/api/v1/permissions',
                '/api/v1/notifications/unread',
                '/api/v1/notifications/unread-count',
            ].includes(url.pathname) ||
                url.pathname.startsWith('/build/') ||
                url.pathname.startsWith('/src/') ||
                url.pathname.startsWith('/favicon'));
        const allowedWrite =
            request.method() === 'POST' &&
            ['/login', '/logout'].includes(url.pathname);

        if (url.origin === origin && (allowedRead || allowedWrite)) {
            report.requests.push({
                method: request.method(),
                path: url.pathname,
            });

            return route.continue();
        }

        report.forbidden.push({ method: request.method(), url: url.href });

        return route.abort();
    });
    const page = await context.newPage();
    page.on('response', (response) => {
        const path = new URL(response.url()).pathname;

        if (path === '/api/v1/roles') {
            report.responses.push({ path, status: response.status() });
        }
    });
    page.on('pageerror', (error) => report.errors.push(error.message));
    const login = async (identity) => {
        await page.goto(`${origin}/login`);
        await page
            .locator('input[name=email]')
            .fill(ready.fixtures[identity].email);
        await page
            .locator('input[name=password]')
            .fill(ready.fixtures[identity].password);
        await Promise.all([
            page.waitForURL('**/dashboard'),
            page.locator('[data-test=login-button]').click(),
        ]);
    };
    const logout = async () => {
        await page
            .getByRole('button', { name: 'Menu da conta', exact: true })
            .click();
        await page.locator('[data-test=logout-button]').click();
        await page.waitForURL((url) => ['/login', '/'].includes(url.pathname));
    };
    await login('admin');
    check('real-admin-login', true);
    await page.goto(`${origin}/roles`);
    await page.locator('tbody [data-column-id]').first().waitFor();
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .waitFor();
    await page
        .getByRole('separator', { name: /^Largura de/ })
        .first()
        .waitFor();
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Configurar colunas', exact: true }).click();
    await page.getByRole('button', { name: 'Compacta', exact: true }).click();
    await page.keyboard.press('Escape');
    const keyA = `datatable-preferences:v1:${ready.fixtures.admin.id}:roles`;
    await page.waitForFunction(
        (key) => JSON.parse(localStorage.getItem(key) ?? '{}').density === 'sm',
        keyA,
    );
    await page.reload();
    await page.locator('tbody [data-column-id]').first().waitFor();
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Configurar colunas', exact: true }).click();
    check(
        'real-reload-restores-layout',
        (await page
            .getByRole('button', { name: 'Compacta', exact: true })
            .getAttribute('aria-pressed')) === 'true',
    );
    await page.keyboard.press('Escape');
    await page
        .getByRole('button', { name: 'Menu da conta', exact: true })
        .click();
    await page.keyboard.press('Escape');
    const separator = page
        .getByRole('separator', { name: /^Largura de/ })
        .first();
    const old = Number(await separator.getAttribute('aria-valuenow'));
    await separator.focus();
    await page.keyboard.press('ArrowRight');
    await logout();
    check(
        'real-logout-retains-completed-resize',
        await page.evaluate(
            ({ key, old }) => {
                const value = JSON.parse(localStorage.getItem(key));

                return (
                    value.density === 'sm' &&
                    Object.values(value.columnSizing).includes(old + 10)
                );
            },
            { key: keyA, old },
        ),
    );
    await login('developer');
    await page.goto(`${origin}/roles`);
    await page.locator('tbody [data-column-id]').first().waitFor();
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Configurar colunas', exact: true }).click();
    check(
        'real-other-account-default-layout',
        (await page
            .getByRole('button', { name: 'Confortável', exact: true })
            .getAttribute('aria-pressed')) === 'true',
    );
    await page.keyboard.press('Escape');
    await logout();
    await login('admin');
    await page.goto(`${origin}/roles`);
    await page.locator('tbody [data-column-id]').first().waitFor();
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Configurar colunas', exact: true }).click();
    check(
        'real-logout-login-account-A-restores',
        (await page
            .getByRole('button', { name: 'Compacta', exact: true })
            .getAttribute('aria-pressed')) === 'true',
    );
    await page.waitForFunction(
        () =>
            getComputedStyle(document.querySelector('[role="dialog"]'))
                .opacity === '1',
    );
    await page.screenshot({
        path: resolve(output, 'real-roles-configure.png'),
        animations: 'disabled',
        fullPage: true,
    });
    check(
        'real-no-errors-or-network-escape',
        report.errors.length === 0 && report.forbidden.length === 0,
    );
    check(
        'real-table-json-requests-succeeded',
        report.responses.length > 0 &&
            report.responses.every((response) => response.status === 200),
    );
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = String(error.stack ?? error);
} finally {
    await browser?.close();
    launcher.stdin.write('stop\n');
    await new Promise((done) => {
        if (launcher.exitCode !== null) {
            done();

            return;
        }

        launcher.once('exit', done);
    });
    report.launcherExit = launcher.exitCode;
    writeFileSync(
        resolve(output, 'http-browser.json'),
        JSON.stringify(report, null, 2),
    );
}

console.log(
    JSON.stringify({
        status: report.status,
        checks: report.checks,
        errors: report.errors,
        forbidden: report.forbidden,
        failure: report.failure,
        launcherExit: report.launcherExit,
    }),
);

if (report.status !== 'passed') {
    process.exitCode = 1;
}

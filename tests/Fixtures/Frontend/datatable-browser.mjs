import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve, extname, sep } from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const root = process.cwd();
const build = resolve(root, 'storage/framework/testing/datatable/build');
const selectionBuild = resolve(
    root,
    'storage/framework/testing/ui-20261019/ui004-build',
);
const output = resolve(root, 'storage/framework/testing/datatable/report');
mkdirSync(output, { recursive: true });
const { chromium } = await import(
    pathToFileURL(process.env.DATATABLE_PLAYWRIGHT_MODULE).href
);
const server = createServer((request, response) => {
    const path = new URL(request.url, 'http://localhost').pathname;
    let file =
        path === '/fixture'
            ? resolve(build, 'tests/Fixtures/Frontend/datatable-browser.html')
            : resolve(build, path.replace(/^\/build\//, ''));

    if (path === '/selection') {
        file = resolve(
            selectionBuild,
            'tests/Fixtures/Frontend/ui-selection-browser.html',
        );
    } else if (!existsSync(file) && path.startsWith('/build/')) {
        file = resolve(selectionBuild, path.replace(/^\/build\//, ''));
    }

    if (
        !file.startsWith(build + sep) &&
        !file.startsWith(selectionBuild + sep)
    ) {
        response.writeHead(403).end();

        return;
    }

    try {
        response.setHeader(
            'Content-Type',
            {
                '.js': 'text/javascript',
                '.css': 'text/css',
                '.html': 'text/html',
                '.woff2': 'font/woff2',
            }[extname(file)] ?? 'application/octet-stream',
        );
        response.end(readFileSync(file));
    } catch {
        response.writeHead(404).end();
    }
});
await new Promise((done) => server.listen(0, '127.0.0.1', done));
const origin = `http://www2.start_kit.com.br:${server.address().port}`;
const report = {
    status: 'running',
    origin,
    checks: [],
    errors: [],
    forbidden: [],
};
const check = (name, value) => {
    assert(value, name);
    report.checks.push(name);
};
let browser;

try {
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
        viewport: { width: 1400, height: 1000 },
    });
    await context.route('**/*', (route) => {
        const url = new URL(route.request().url());

        if (
            url.origin === origin &&
            (url.pathname === '/fixture' ||
                url.pathname === '/selection' ||
                url.pathname.startsWith('/build/'))
        ) {
            return route.continue();
        }

        report.forbidden.push(url.href);

        return route.abort();
    });
    const page = await context.newPage();
    page.on('pageerror', (error) => report.errors.push(error.message));
    await page.addInitScript(() => {
        if (!sessionStorage.getItem('seeded')) {
            localStorage.setItem(
                'datatable-preferences:v1:901:preferences-fixture',
                JSON.stringify({
                    version: 1,
                    search: 'Restaurada',
                    perPage: 50,
                    density: 'sm',
                    sorting: [{ id: 'name', desc: true }],
                    filters: { active: false },
                }),
            );
            sessionStorage.setItem('seeded', '1');
        }
    });
    await page.goto(`${origin}/fixture`, { waitUntil: 'networkidle' });
    await page.getByText('Restaurada 1-1', { exact: true }).waitFor();
    const rowsCalls = () =>
        page.evaluate(() =>
            window.tableProbe.requests.filter(
                (request) => request.path === '/fixture/rows',
            ),
        );
    const initialCalls = await rowsCalls();
    check(
        'restored-before-first-query',
        initialCalls.length > 0 &&
            initialCalls.every(
                (request) =>
                    request.params.search === 'Restaurada' &&
                    request.params.per_page === 50 &&
                    request.params.sort === 'name' &&
                    request.params.direction === 'desc' &&
                    request.params['filter[active]'] === false,
            ),
    );
    check(
        'http-custom-domain-without-randomUUID',
        await page.evaluate(
            () =>
                location.protocol === 'http:' &&
                location.hostname === 'www2.start_kit.com.br' &&
                typeof crypto.randomUUID === 'undefined',
        ),
    );
    await page
        .getByRole('button', {
            name: 'Remover filtro Status: Inativo',
            exact: true,
        })
        .waitFor();
    check(
        'false-chip-name-value',
        (await page
            .getByRole('button', { name: 'Status: Inativo', exact: true })
            .count()) === 1,
    );
    await page
        .getByRole('checkbox', { name: 'Selecionar linha', exact: true })
        .first()
        .click();
    await page.getByText('selecionado', { exact: true }).first().waitFor();
    const separator = page.getByRole('separator', {
        name: 'Largura de Nome',
        exact: true,
    });
    check(
        'actions-and-adjacent-boundary-cannot-resize',
        await page
            .locator('thead th[data-column-id="actions"]')
            .evaluate(
                (element) =>
                    element.getAttribute('draggable') === 'false' &&
                    element.querySelector('[role="separator"]') === null &&
                    element.previousElementSibling.querySelector(
                        '[role="separator"]',
                    ) === null,
            ),
    );
    const oldSize = Number(await separator.getAttribute('aria-valuenow'));
    check(
        'resize-guide-hidden-at-rest',
        await separator.evaluate(
            (element) => getComputedStyle(element, '::after').opacity === '0',
        ),
    );
    await separator.hover();
    await page.waitForFunction(
        () =>
            getComputedStyle(
                document.querySelector('[aria-label="Largura de Nome"]'),
                '::after',
            ).opacity === '1',
    );
    check('resize-guide-visible-on-hover', true);
    await page.mouse.move(0, 0);
    const beforeLayoutCalls = (await rowsCalls()).length;
    await separator.focus();
    await page.keyboard.press('ArrowRight');
    check(
        'keyboard-resize',
        Number(await separator.getAttribute('aria-valuenow')) === oldSize + 10,
    );
    const box = await separator.boundingBox();
    const headerWidth = () =>
        separator.evaluate(
            (element) => element.parentElement.getBoundingClientRect().width,
        );
    const initialWidth = await headerWidth();
    await page.mouse.move(box.x + 2, box.y + 8);
    await page.mouse.down();
    await page.mouse.move(box.x + 72, box.y + 8);
    check(
        'column-resizes-live-without-commit',
        (await separator.evaluate(
            (element) => element.dataset.resizing === 'true',
        )) &&
            Number(await separator.getAttribute('aria-valuenow')) ===
                oldSize + 10,
    );
    await page.waitForFunction(
        (width) =>
            Math.abs(
                document
                    .querySelector('thead [data-column-id="name"]')
                    .getBoundingClientRect().width - width,
            ) < 1,
        initialWidth + 70,
    );
    await page.keyboard.press('Escape');
    await page.locator('h1').click();
    await page
        .locator('table')
        .locator('..')
        .evaluate((element) => {
            element.scrollLeft = 0;
        });
    await page.mouse.up();
    check(
        'escape-cancels-resize',
        Number(await separator.getAttribute('aria-valuenow')) ===
            oldSize + 10 && Math.abs((await headerWidth()) - initialWidth) < 1,
    );
    await page.mouse.move(box.x + 2, box.y + 8);
    await page.mouse.down();
    await page.mouse.move(box.x + 42, box.y + 8);
    await page.mouse.up();
    check(
        'pointer-resize-commits-on-release',
        Number(await separator.getAttribute('aria-valuenow')) > oldSize + 10,
    );
    check(
        'pointer-tracks-exact-width-on-release',
        Math.abs((await headerWidth()) - initialWidth - 40) < 1,
    );
    const committed = Number(await separator.getAttribute('aria-valuenow'));
    await separator.dispatchEvent('pointerdown', {
        clientX: 100,
        pointerId: 1,
    });
    await page.dispatchEvent('body', 'pointermove', {
        clientX: 180,
        pointerId: 1,
    });
    await page.dispatchEvent('body', 'pointercancel', { pointerId: 1 });
    check(
        'pointercancel-preserves-width',
        Number(await separator.getAttribute('aria-valuenow')) === committed,
    );
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page
        .getByRole('menuitem', { name: 'Configurar colunas', exact: true })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog
        .getByRole('button', { name: 'Mover Descrição para cima', exact: true })
        .click();
    await dialog
        .locator('li')
        .filter({ hasText: 'Valor' })
        .dragTo(dialog.locator('li').filter({ hasText: 'Nome' }));
    check(
        'drag-reorders-columns',
        await page.evaluate(() => {
            const saved = JSON.parse(
                localStorage.getItem(
                    'datatable-preferences:v1:901:preferences-fixture',
                ),
            );

            return (
                saved.columnOrder.indexOf('amount') <
                saved.columnOrder.indexOf('name')
            );
        }),
    );
    await dialog.getByRole('checkbox', { name: 'Nome', exact: true }).uncheck();
    await dialog
        .getByRole('checkbox', { name: 'Valor', exact: true })
        .uncheck();
    check(
        'last-data-column-protected',
        await dialog
            .getByRole('checkbox', { name: 'Descrição', exact: true })
            .isDisabled(),
    );
    await dialog.getByRole('checkbox', { name: 'Nome', exact: true }).check();
    await dialog.getByRole('checkbox', { name: 'Valor', exact: true }).check();
    await dialog
        .getByRole('combobox', { name: 'Fixação de Valor', exact: true })
        .click();
    await page
        .getByRole('option', { name: 'Fixar à esquerda', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'Confortável', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'Ajustar todas ao conteúdo', exact: true })
        .click();
    await page.keyboard.press('Escape');
    check(
        'layout-does-not-refetch',
        (await rowsCalls()).length === beforeLayoutCalls,
    );
    check(
        'pin-and-order-persisted',
        await page.evaluate(() => {
            const saved = JSON.parse(
                localStorage.getItem(
                    'datatable-preferences:v1:901:preferences-fixture',
                ),
            );

            return (
                saved.columnPinning.left.includes('amount') &&
                saved.columnOrder.indexOf('email') <
                    saved.columnOrder.indexOf('name') &&
                saved.density === 'md'
            );
        }),
    );
    const widths = await page
        .locator('thead [data-column-id]')
        .evaluateAll((headers) =>
            headers.every((header) => {
                const cell = [
                    ...document.querySelectorAll('tbody [data-column-id]'),
                ].find(
                    (item) => item.dataset.columnId === header.dataset.columnId,
                );

                return (
                    !cell ||
                    Math.abs(
                        cell.getBoundingClientRect().width -
                            header.getBoundingClientRect().width,
                    ) < 1
                );
            }),
        );

    if (!widths) {
        report.geometry = await page
            .locator('[data-column-id]')
            .evaluateAll((elements) =>
                elements.map((element) => ({
                    tag: element.tagName,
                    id: element.dataset.columnId,
                    width: element.getBoundingClientRect().width,
                    style: element.getAttribute('style'),
                })),
            );
        await page.screenshot({
            path: resolve(output, 'geometry-failure.png'),
            fullPage: true,
        });
    }

    check('header-body-geometry', widths);
    await page
        .getByRole('button', { name: 'Expandir texto completo', exact: true })
        .first()
        .focus();
    await page.keyboard.press('Enter');
    await page.getByLabel('Texto completo', { exact: true }).waitFor();
    check(
        'keyboard-full-text-wrap',
        await page
            .getByLabel('Texto completo', { exact: true })
            .evaluate(
                (element) =>
                    getComputedStyle(element).whiteSpace === 'pre-wrap' &&
                    element.getBoundingClientRect().width <= 384 &&
                    element.scrollWidth <= element.clientWidth &&
                    element.textContent.includes('Texto completo longo'),
            ),
    );
    await page.keyboard.press('Escape');
    await page.screenshot({
        path: resolve(output, 'desktop-light.png'),
        fullPage: true,
    });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.screenshot({
        path: resolve(output, 'desktop-dark.png'),
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
        path: resolve(output, 'mobile-dark.png'),
        fullPage: true,
    });
    check(
        'mobile-retains-pin-preferences',
        await page.evaluate(() =>
            JSON.parse(
                localStorage.getItem(
                    'datatable-preferences:v1:901:preferences-fixture',
                ),
            ).columnPinning.left.includes('amount'),
        ),
    );
    await page.setViewportSize({ width: 1400, height: 1000 });
    await page.keyboard.press('Escape');
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page
        .getByRole('menuitem', { name: 'Restaurar layout', exact: true })
        .click();
    check(
        'layout-reset-preserves-search-and-filters',
        await page.evaluate(() => {
            const saved = JSON.parse(
                localStorage.getItem(
                    'datatable-preferences:v1:901:preferences-fixture',
                ),
            );

            return (
                saved.search === 'Restaurada' &&
                saved.filters.active === false &&
                Object.keys(saved.columnSizing).length === 0 &&
                saved.density === 'md'
            );
        }),
    );
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page
        .getByRole('menuitem', { name: 'Restaurar tudo', exact: true })
        .click();
    await page.keyboard.press('Escape');
    await page.getByText('Pessoa 1-1', { exact: true }).waitFor();
    check(
        'reset-all-preserves-selection',
        (await page
            .getByRole('checkbox', { name: 'Selecionar linha', exact: true })
            .first()
            .getAttribute('aria-checked')) === 'true',
    );
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await page
        .getByRole('textbox', { name: 'Buscar...', exact: true })
        .fill('Conta A');
    await page.getByText('Conta A 1-1', { exact: true }).waitFor();
    await page.evaluate(() => {
        localStorage.setItem('private-state:901:synthetic-private', 'private');
        window.tableProbe.logout();
    });
    check(
        'logout-purges-private-retains-preferences',
        await page.evaluate(
            () =>
                localStorage.getItem('private-state:901:synthetic-private') ===
                    null &&
                JSON.parse(
                    localStorage.getItem(
                        'datatable-preferences:v1:901:preferences-fixture',
                    ),
                ).search === 'Conta A',
        ),
    );
    await page.goto(`${origin}/fixture?account=B`, {
        waitUntil: 'networkidle',
    });
    await page.getByText('Pessoa 1-1', { exact: true }).waitFor();
    check(
        'account-B-has-own-default-query',
        (await rowsCalls()).every(
            (request) => request.params.search === undefined,
        ),
    );
    await page.goto(`${origin}/fixture`, { waitUntil: 'networkidle' });
    await page.getByText('Conta A 1-1', { exact: true }).waitFor();
    check(
        'account-A-restores-after-logout-login',
        (await rowsCalls()).every(
            (request) => request.params.search === 'Conta A',
        ),
    );
    const viewport = page.locator('table').locator('..');
    await viewport.evaluate((element) => {
        element.scrollTop = element.scrollHeight;
    });
    await page.getByText('Conta A 2-1', { exact: true }).waitFor();
    check(
        'default-infinite-loads-next-page',
        (await rowsCalls()).some((request) => request.params.page === 2),
    );
    await page
        .getByRole('textbox', { name: 'Buscar...', exact: true })
        .fill('Nova consulta');
    await page.getByText('Nova consulta 1-1', { exact: true }).waitFor();
    check(
        'new-query-does-not-mix-pages',
        (await page.getByText('Conta A 2-1', { exact: true }).count()) === 0,
    );
    await page.getByRole('button', { name: 'Filtros', exact: true }).click();
    const filterPanel = page.getByRole('dialog').last();
    await filterPanel
        .getByRole('button', { name: 'Valor', exact: true })
        .click();
    check(
        'contextual-range-placeholders',
        (await filterPanel
            .getByRole('spinbutton', { name: 'Valor: início', exact: true })
            .getAttribute('placeholder')) === 'Valor inicial' &&
            (await filterPanel
                .getByRole('spinbutton', { name: 'Valor: fim', exact: true })
                .getAttribute('placeholder')) === 'Valor final',
    );
    await filterPanel
        .getByRole('spinbutton', { name: 'Valor: início', exact: true })
        .fill('0');
    await page.keyboard.press('Escape');
    await page
        .getByRole('button', {
            name: 'Remover filtro Valor: A partir de 0',
            exact: true,
        })
        .click();
    check(
        'zero-chip-removal-preserves-search',
        (await page
            .getByRole('textbox', { name: 'Buscar...', exact: true })
            .inputValue()) === 'Nova consulta' &&
            (await page
                .getByRole('button', {
                    name: 'Remover filtro Valor: A partir de 0',
                    exact: true,
                })
                .count()) === 0,
    );
    check(
        'no-browser-errors-or-network-escape',
        report.errors.length === 0 && report.forbidden.length === 0,
    );
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page
        .getByRole('menuitem', { name: 'Restaurar layout', exact: true })
        .click();
    await page.keyboard.press('Escape');
    await page.locator('h1').click();
    await page
        .locator('table')
        .locator('..')
        .evaluate((element) => {
            element.scrollLeft = 0;
        });
    await page.screenshot({
        path: resolve(output, 'final-default-light.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.screenshot({
        path: resolve(output, 'final-default-dark.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page
        .getByRole('button', { name: 'Configurações da tabela', exact: true })
        .click();
    await page
        .getByRole('menuitem', { name: 'Configurar colunas', exact: true })
        .click();
    await page.screenshot({
        path: resolve(output, 'final-configure-dark.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.evaluate(() =>
        document.documentElement.classList.remove('dark'),
    );
    await page.screenshot({
        path: resolve(output, 'final-configure-light.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.goto(`${origin}/selection`);
    await page.locator('#run-contracts').click();
    await page.waitForFunction(
        () => {
            try {
                return ['passed', 'failed'].includes(
                    JSON.parse(
                        document.getElementById('fixture-result').textContent,
                    ).status,
                );
            } catch {
                return false;
            }
        },
        undefined,
        { timeout: 90000 },
    );
    report.selection = JSON.parse(
        await page.locator('#fixture-result').textContent(),
    );
    check(
        'existing-selection-regressions',
        report.selection.status === 'passed',
    );
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = String(error.stack ?? error);
} finally {
    await browser?.close();
    await new Promise((done) => server.close(done));
    writeFileSync(
        resolve(output, 'browser.json'),
        JSON.stringify(report, null, 2),
    );
}

console.log(JSON.stringify(report));

if (report.status !== 'passed') {
    process.exitCode = 1;
}

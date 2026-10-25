import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const [manifestPath, fixturePath, reportPath] = process.argv.slice(2);
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));
const origin = `http://${manifest.host}:${manifest.port}`;
for (const key of Object.keys(process.env)) {
    if (
        ['HOME', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA'].includes(
            key.toUpperCase(),
        )
    ) {
        delete process.env[key];
    }
}
const { chromium } = await import(
    pathToFileURL(process.env.OPS004_PLAYWRIGHT_MODULE).href
);
const report = {
    status: 'running',
    origin,
    checks: [],
    responses: [],
    networkTimings: [],
    diagnostics: [],
    blockedExternal: [],
    allowedHttpRequests: [],
    allowedExternalRequests: 0,
    websockets: [],
    cleanupErrors: [],
};
const check = (name, value) => {
    assert(value, name);
    report.checks.push(name);
};
let browser;
let context;
try {
    const browserKeys = new Set([
        'SYSTEMROOT',
        'WINDIR',
        'COMSPEC',
        'PATH',
        'PATHEXT',
        'TEMP',
        'TMP',
        'TMPDIR',
    ]);
    browser = await chromium.launch({
        executablePath: process.env.OPS004_BROWSER_BINARY,
        headless: true,
        env: Object.fromEntries(
            Object.entries(process.env).filter(([key]) =>
                browserKeys.has(key.toUpperCase()),
            ),
        ),
    });
    context = await browser.newContext({ serviceWorkers: 'block' });
    await context.route('**/*', (route) => {
        const url = new URL(route.request().url());
        if (url.origin === origin || url.protocol === 'data:') {
            report.allowedHttpRequests.push({
                origin: url.origin,
                path: url.pathname,
                protocol: url.protocol,
            });
            return route.continue();
        }
        report.blockedExternal.push({ origin: url.origin, path: url.pathname });
        return route.abort('blockedbyclient');
    });
    const page = await context.newPage();
    page.on('pageerror', (error) =>
        report.diagnostics.push({ type: 'pageerror', text: error.message }),
    );
    page.on('console', (message) =>
        report.diagnostics.push({ type: message.type(), text: message.text() }),
    );
    page.on('websocket', (socket) => report.websockets.push(socket.url()));
    page.on('requestfinished', (request) => {
        const url = new URL(request.url());
        const resourceType = request.resourceType();
        if (
            url.origin !== origin ||
            !['document', 'fetch', 'xhr'].includes(resourceType)
        ) {
            return;
        }
        const timing = request.timing();
        const durationMs = timing.responseEnd;
        const ttfbMs = timing.responseStart - timing.requestStart;
        if (
            [
                timing.requestStart,
                timing.responseStart,
                durationMs,
                ttfbMs,
            ].every(Number.isFinite) &&
            timing.requestStart >= 0 &&
            durationMs >= 0 &&
            ttfbMs >= 0
        ) {
            report.networkTimings.push({
                path: url.pathname,
                method: request.method(),
                resourceType,
                durationMs,
                ttfbMs,
            });
        }
    });
    page.on('response', (response) => {
        const url = new URL(response.url());
        if (url.origin === origin && !url.pathname.startsWith('/build/')) {
            report.responses.push({
                path: url.pathname,
                method: response.request().method(),
                status: response.status(),
                contentType: response.headers()['content-type'] ?? '',
                inertia: response.request().headers()['x-inertia'] ?? null,
            });
        }
    });
    const login = await page.goto(`${origin}/login`);
    assert.equal(login.status(), 200);
    await page.locator('input[name=email]').fill(fixture.actor.email);
    await page.locator('input[name=password]').fill(fixture.actor.password);
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.locator('[data-test=login-button]').click(),
    ]);
    check('real-login-and-session', true);
    assert.equal(await page.evaluate(() => window.Echo ?? null), null);

    for (const resource of ['role', 'permission']) {
        const plural = `${resource}s`;
        const entry = fixture[resource];
        const editUrl = `${origin}/${plural}/${entry.id}/edit`;
        const apiPath = `/api/v1/${plural}/${entry.id}`;
        const edit = await page.goto(editUrl);
        assert.equal(edit.status(), 200);
        await page.waitForFunction(
            () => !document.getElementById('boot-shell'),
        );
        const name = page.locator('form input').first();
        await name.fill('');
        const invalidResponse = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === apiPath &&
                response.request().method() === 'PUT',
        );
        await page
            .getByRole('button', { name: 'Salvar alterações', exact: true })
            .click();
        const invalid = await invalidResponse;
        assert.equal(invalid.status(), 422);
        assert.match(invalid.headers()['content-type'], /application\/json/);
        await page.waitForFunction(
            () =>
                document
                    .querySelector('form input')
                    ?.getAttribute('aria-invalid') === 'true',
        );
        assert.equal(page.url(), editUrl);
        check(
            `${resource}-json-422-renders-field-error-without-navigation`,
            true,
        );

        const updated =
            resource === 'role' ? 'ARC Role Updated' : 'ARC Permission Updated';
        await name.fill(updated);
        const validResponse = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === apiPath &&
                response.request().method() === 'PUT',
        );
        await page
            .getByRole('button', { name: 'Salvar alterações', exact: true })
            .click();
        const valid = await validResponse;
        assert.equal(valid.status(), 200);
        assert.match(valid.headers()['content-type'], /application\/json/);
        const payload = await valid.json();
        assert.equal(payload.data.name, updated);
        assert.equal(payload.data.slug, entry.slug);
        await page.waitForURL(`**/${plural}/${entry.id}`);
        await page.getByText(updated, { exact: true }).first().waitFor();
        check(`${resource}-real-json-update-and-show-navigation`, true);
    }

    for (const resource of ['target_user', 'client']) {
        const plural = resource === 'target_user' ? 'users' : 'clients';
        const path = `/${plural}/${fixture[resource].uuid}`;
        const response = await page.goto(origin + path);
        assert.equal(response.status(), 200);
        await page.waitForFunction(
            () => !document.getElementById('boot-shell'),
        );
        await page
            .getByRole('button', { name: 'Excluir', exact: true })
            .click();
        const dialog = page.getByRole('dialog');
        await dialog.waitFor();
        const deletion = page.waitForResponse(
            (result) =>
                new URL(result.url()).pathname === path &&
                result.request().method() === 'DELETE',
        );
        await dialog
            .getByRole('button', { name: 'Excluir', exact: true })
            .click();
        const removed = await deletion;
        assert.equal(removed.request().headers()['x-inertia'], 'true');
        assert.equal(removed.status(), 303);
        check(`${plural}-confirmation-uses-real-inertia-delete-redirect`, true);
        await page.waitForURL(`**/${plural}`);
        await page.waitForFunction(
            () => !document.getElementById('boot-shell'),
        );
        assert.equal(await page.locator('#boot-error').count(), 0);
        check(`${plural}-redirect-finishes-on-index-without-error-page`, true);
    }
    assert(report.allowedHttpRequests.length > 0);
    report.allowedExternalRequests = report.allowedHttpRequests.filter(
        (request) => request.origin !== origin && request.protocol !== 'data:',
    ).length;
    assert.equal(report.allowedExternalRequests, 0);
    check('external-http-is-blocked', true);
    assert.deepEqual(report.websockets, []);
    check('realtime-disabled-without-websocket', true);
    const unexpected = report.responses.filter(
        (response) =>
            response.status >= 400 &&
            !(response.method === 'PUT' && response.status === 422),
    );
    assert.deepEqual(unexpected, []);
    const errors = report.diagnostics.filter(
        (entry) =>
            entry.type === 'pageerror' ||
            /hydrati|Minified React error/i.test(entry.text),
    );
    assert.deepEqual(errors, []);
    check('no-browser-exceptions-or-unexpected-http-errors', true);
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = {
        name: error.name,
        message: error.message,
        stack: error.stack,
    };
    process.exitCode = 1;
} finally {
    if (context)
        await context
            .close()
            .catch((error) => report.cleanupErrors.push(error.message));
    if (browser)
        await browser
            .close()
            .catch((error) => report.cleanupErrors.push(error.message));
    if (report.cleanupErrors.length) {
        report.status = 'failed';
        report.failure = { message: 'Owned browser cleanup failed.' };
        process.exitCode = 1;
    }
    writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(
        JSON.stringify({
            status: report.status,
            checks: report.checks.length,
            failure: report.failure?.message,
        }),
    );
}

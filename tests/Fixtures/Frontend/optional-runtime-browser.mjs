import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const [manifestPath, statePath, reportPath] = process.argv.slice(2);
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const state = JSON.parse(readFileSync(statePath, 'utf8'));
const origin = `http://${manifest.host}:${manifest.port}`;
const workerEnvironment = { ...process.env };

// Playwright captures Windows profile locations while its module loads.
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
    ssr: state.ssr,
    realtime: state.realtime,
    origin,
    checks: [],
    diagnostics: [],
    websocketEvents: [],
    ownershipViolations: [],
    httpResponses: [],
    initialHtml: [],
    cleanupErrors: [],
};
let browser;
const contexts = [];
const check = (name, value) => {
    assert(value, name);
    report.checks.push(name);
};

async function restrictedContext(options = {}) {
    const context = await browser.newContext(options);
    contexts.push(context);
    await context.route('**/*', (route) => {
        const url = new URL(route.request().url());

        return url.origin === origin || url.protocol === 'data:'
            ? route.continue()
            : route.abort('blockedbyclient');
    });

    return context;
}

async function initialHtml(path, cookies = []) {
    const context = await restrictedContext({ javaScriptEnabled: false });

    if (cookies.length) {
        await context.addCookies(cookies);
    }

    const page = await context.newPage();
    const response = await page.goto(origin + path);
    assert.equal(response.status(), 200);
    const html = await page.locator('#app').innerHTML();
    report.initialHtml.push({
        path,
        markupBytes: Buffer.byteLength(html),
        identities: state.actors.map((actor) => html.includes(actor.name)),
    });
    assert.equal(
        html.length > 0,
        state.ssr,
        `Initial HTML mode mismatch: ${path}`,
    );
    await context.close();

    return html;
}

function publish(actor) {
    const nonce = randomBytes(8).toString('hex');
    const result = spawnSync(
        process.env.OPS004_PHP_BINARY,
        [
            '-d',
            'auto_prepend_file=',
            '-d',
            'auto_append_file=',
            'tests/optional-runtime-worker.php',
            'publish',
            manifestPath,
            statePath,
            String(actor.id),
            nonce,
        ],
        {
            cwd: process.cwd(),
            env: workerEnvironment,
            encoding: 'utf8',
            timeout: 15000,
        },
    );
    assert.equal(result.status, 0, result.stderr);

    return JSON.parse(result.stdout.trim());
}

async function csrfPost(page, path, data) {
    return page.evaluate(
        async ({ path, data }) => {
            const cookie = document.cookie
                .split('; ')
                .find((entry) => entry.startsWith('XSRF-TOKEN='));
            const response = await fetch(path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': cookie
                        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
                        : '',
                },
                body: JSON.stringify(data),
            });

            return response.status;
        },
        { path, data },
    );
}

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
    const browserEnvironment = Object.fromEntries(
        Object.entries(process.env).filter(([key]) =>
            browserKeys.has(key.toUpperCase()),
        ),
    );
    browser = await chromium.launch({
        executablePath: process.env.OPS004_BROWSER_BINARY,
        headless: true,
        env: browserEnvironment,
    });
    await initialHtml('/login');
    check('login-html-before-javascript-matches-mode', true);
    const clients = [];

    for (const [index, actor] of state.actors.entries()) {
        const context = await restrictedContext();
        const page = await context.newPage();
        const client = {
            context,
            page,
            active: 0,
            maximum: 0,
            sockets: 0,
            events: [],
        };
        clients.push(client);
        page.on('console', (message) => {
            report.diagnostics.push({
                actor: index,
                type: message.type(),
                text: message.text(),
            });
        });
        page.on('pageerror', (error) => {
            report.diagnostics.push({
                actor: index,
                type: 'pageerror',
                text: error.message,
            });
        });
        page.on('response', (response) => {
            const url = new URL(response.url());

            if (url.origin === origin && !url.pathname.startsWith('/build/')) {
                report.httpResponses.push({
                    actor: index,
                    path: url.pathname,
                    status: response.status(),
                });
            }
        });
        page.on('websocket', (socket) => {
            if (
                new URL(socket.url()).host !== `127.0.0.1:${state.reverb_port}`
            ) {
                report.ownershipViolations.push({
                    actor: index,
                    type: 'unexpected-websocket-destination',
                    host: new URL(socket.url()).host,
                });
            }

            client.sockets++;
            client.active++;
            client.maximum = Math.max(client.maximum, client.active);
            socket.on('close', () => client.active--);
            socket.on('framereceived', ({ payload }) => {
                let frame;

                try {
                    frame = JSON.parse(String(payload));
                } catch {
                    return;
                }

                if (frame.event === 'notification.created') {
                    const data =
                        typeof frame.data === 'string'
                            ? JSON.parse(frame.data)
                            : frame.data;
                    client.events.push({
                        channel: frame.channel,
                        uuid: data.notification?.uuid,
                    });
                    report.websocketEvents.push({
                        actor: index,
                        channel: frame.channel,
                        uuid: data.notification?.uuid,
                    });

                    if (
                        frame.channel !==
                        `private-user.${actor.id}.notifications`
                    ) {
                        report.ownershipViolations.push({
                            actor: index,
                            channel: frame.channel,
                        });
                    }
                }
            });
        });
        await page.goto(origin + '/login');
        await page.locator('input[name=email]').fill(actor.email);
        await page.locator('input[name=password]').fill(actor.password);
        await Promise.all([
            page.waitForURL('**/dashboard'),
            page.locator('[data-test=login-button]').click(),
        ]);
        await page
            .getByRole('button', { name: 'Notificações', exact: true })
            .click();
        await Promise.all([
            page.waitForURL('**/notifications'),
            page
                .getByRole('link', {
                    name: 'Ver todas as notificações',
                    exact: true,
                })
                .click(),
        ]);
        await page.waitForFunction(
            () => !document.getElementById('boot-shell'),
        );
        check(`account-${index}-login-and-hydration-interaction`, true);

        if (state.realtime) {
            await page.waitForFunction(
                (id) => {
                    const echo = window.Echo;
                    const channel =
                        echo?.connector.channels[
                            `private-user.${id}.notifications`
                        ];

                    return (
                        echo?.connector.pusher.connection.state ===
                            'connected' &&
                        channel?.subscription?.subscribed === true
                    );
                },
                actor.id,
                { timeout: 15000 },
            );
            check(`account-${index}-actual-private-subscription`, true);
        } else {
            assert.equal(await page.evaluate(() => window.Echo ?? null), null);
            assert.equal(client.sockets, 0);
            check(`account-${index}-realtime-disabled-without-socket`, true);
        }

        const text = await initialHtml(
            '/notifications',
            await context.cookies(),
        );

        if (state.ssr) {
            assert(
                text.includes(actor.name),
                'SSR HTML must contain the current account identity.',
            );
            assert(
                !text.includes(state.actors[1 - index].name),
                'SSR HTML leaked another account identity.',
            );
            check(`account-${index}-authenticated-ssr-isolated`, true);
        }
    }

    const published = [];

    if (state.realtime) {
        report.connections = clients.map((client, actor) => ({
            actor,
            opened: client.sockets,
            maximumActive: client.maximum,
        }));

        for (const [index, client] of clients.entries()) {
            const socketId = await client.page.evaluate(() =>
                window.Echo.socketId(),
            );
            const status = await csrfPost(client.page, '/broadcasting/auth', {
                socket_id: socketId,
                channel_name: `private-user.${state.actors[1 - index].id}.notifications`,
            });
            assert.equal(
                status,
                403,
                'Foreign private channel must be refused.',
            );
            check(`account-${index}-foreign-channel-refused`, true);
        }

        for (const [index, actor] of state.actors.entries()) {
            const notification = publish(actor);
            published.push(notification);
            await clients[index].page.waitForFunction(
                (title) => document.body.innerText.includes(title),
                notification.title,
                { timeout: 12000 },
            );
            const deadline = Date.now() + 12000;

            while (
                !clients[index].events.some(
                    (event) => event.uuid === notification.uuid,
                )
            ) {
                if (Date.now() > deadline) {
                    throw new Error(
                        'Real private WebSocket delivery timed out.',
                    );
                }

                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            assert.equal(
                clients[index].events.filter(
                    (event) => event.uuid === notification.uuid,
                ).length,
                1,
            );
            assert(
                !clients[1 - index].events.some(
                    (event) => event.uuid === notification.uuid,
                ),
            );
            const foreign = await clients[1 - index].page.evaluate(async () => {
                const response = await fetch('/api/v1/notifications', {
                    headers: { Accept: 'application/json' },
                });

                return response.json();
            });
            assert(
                !foreign.data.some((entry) => entry.uuid === notification.uuid),
            );
            check(`account-${index}-real-event-exactly-once-and-private`, true);
        }

        for (const [index, client] of clients.entries()) {
            assert.equal(
                client.maximum,
                1,
                'Concurrent duplicate Echo connections.',
            );
            check(`account-${index}-single-active-connection`, true);
        }

        for (const [index, client] of clients.entries()) {
            assert.deepEqual(client.events, [
                {
                    channel: `private-user.${state.actors[index].id}.notifications`,
                    uuid: published[index].uuid,
                },
            ]);
            check(`account-${index}-complete-event-ledger-is-private`, true);
        }
    } else {
        for (const [index, actor] of state.actors.entries()) {
            const notification = publish(actor);
            await clients[index].page.reload();
            await clients[index].page
                .getByText(notification.title, { exact: true })
                .first()
                .waitFor();
            const foreign = await clients[1 - index].page.evaluate(async () => {
                const response = await fetch('/api/v1/notifications', {
                    headers: { Accept: 'application/json' },
                });

                return response.json();
            });
            assert(
                !foreign.data.some((entry) => entry.uuid === notification.uuid),
            );
            assert.equal(clients[index].sockets, 0);
            check(
                `account-${index}-persisted-notification-works-without-realtime`,
                true,
            );
        }
    }

    const hydrationErrors = report.diagnostics.filter(
        (entry) =>
            entry.type === 'pageerror' ||
            /hydrati|hydrated|Minified React error #(418|423|425)/i.test(
                entry.text,
            ),
    );
    assert.deepEqual(
        hydrationErrors,
        [],
        'Hydration or browser runtime failed.',
    );
    check('no-hydration-or-browser-runtime-errors', true);
    assert.deepEqual(report.ownershipViolations, []);
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
    for (const context of contexts) {
        await context
            .close()
            .catch((error) => report.cleanupErrors.push(error.message));
    }

    if (browser) {
        await browser.close();
    }

    if (report.ownershipViolations.length || report.cleanupErrors.length) {
        report.status = 'failed';
        report.failure = {
            message: 'A late WebSocket frame violated account ownership.',
        };
        process.exitCode = 1;
    }

    writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(
        JSON.stringify({
            status: report.status,
            ssr: state.ssr,
            realtime: state.realtime,
            checks: report.checks.length,
            failure: report.failure?.message,
        }),
    );
}

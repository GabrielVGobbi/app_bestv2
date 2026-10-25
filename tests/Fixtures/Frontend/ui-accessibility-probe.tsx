import { createInertiaApp } from '@inertiajs/react';
import type { Editor } from '@tiptap/core';
import type {
    AxiosAdapter,
    AxiosResponse,
    InternalAxiosRequestConfig,
} from 'axios';
import { AxiosError, AxiosHeaders } from 'axios';
import { StrictMode, useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';

import '../../../resources/css/app.css';
import { RichTextEditor } from '@/components/rich-text-editor';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { PrivateStateLayout } from '@/contexts/private-state-context';
import { useClipboard } from '@/hooks/use-clipboard';
import { http } from '@/lib/http';
import { CommentComposer } from '@/modules/timeline/components/comment-composer';
import DesignSystem from '@/pages/admin/dev/design-system';
import EditPermission from '@/pages/app/permissions/edit';
import EditRole from '@/pages/app/roles/edit';
import type { Permission } from '@/types/app/permission';
import type { Role } from '@/types/app/role';

type Scene =
    | 'forms'
    | 'editors'
    | 'readonly'
    | 'composer'
    | 'clipboard'
    | 'design'
    | 'two-factor';
type Check = { name: string; status: 'passed' | 'failed'; error?: string };
type CopyMode = 'missing' | 'invalid' | 'rejected' | 'pending' | 'success';
const result = document.getElementById('fixture-result')!;
const rootElement = document.getElementById('app')!;
const checks: Check[] = [];
const requests: Array<{ method: string; path: string }> = [];
const clipboardWrites: string[] = [];
const copyOutcomes: boolean[] = [];
const expectedRejections: string[] = [];
const originalClipboard = Object.getOwnPropertyDescriptor(
    navigator,
    'clipboard',
);
const originalClipboardAvailable =
    typeof navigator.clipboard?.writeText === 'function';
let forbidden = 0;
let filePickerRequests = 0;
let revision = 0;
let changeScene: ((scene: Scene, revision: number) => void) | null = null;
let resolveCopy: (() => void) | null = null;
let setEditorReadOnly: ((value: boolean) => void) | null = null;
const copyGates: Array<{ resolve: () => void; reject: () => void }> = [];
const role: Role = {
    id: 900009,
    name: 'UI009 papel sintético',
    slug: 'ui009-role',
    description: 'Sem persistência',
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
};
const permission: Permission = {
    id: 900009,
    name: 'UI009 permissão sintética',
    slug: 'ui009-permission',
    group: 'UI009',
    description: 'Sem persistência',
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
};

function denyNetwork(): never {
    forbidden++;

    throw new Error('UI009 forbids native network access.');
}
globalThis.fetch = denyNetwork;
XMLHttpRequest.prototype.open = denyNetwork;
navigator.sendBeacon = () => denyNetwork();

function response(
    config: InternalAxiosRequestConfig,
    data: unknown,
    status = 200,
): AxiosResponse {
    return {
        config,
        data,
        status,
        statusText: status === 200 ? 'OK' : 'Fixture validation',
        headers: new AxiosHeaders(),
    };
}

const adapter: AxiosAdapter = async (config) => {
    const url = new URL(config.url ?? '', location.origin);
    const method = config.method?.toUpperCase() ?? 'GET';
    requests.push({ method, path: url.pathname });

    if (
        method === 'GET' &&
        !url.search &&
        url.pathname === '/api/v1/notifications/unread'
    ) {
        return response(config, { data: [], unread_count: 0 });
    }

    if (
        method === 'GET' &&
        !url.search &&
        url.pathname === '/api/v1/notifications/unread-count'
    ) {
        return response(config, { count: 0 });
    }

    if (
        method === 'PUT' &&
        !url.search &&
        ['/roles/900009', '/permissions/900009'].includes(url.pathname)
    ) {
        const fields = url.pathname.startsWith('/roles')
            ? ['name', 'slug', 'description']
            : ['name', 'slug', 'group', 'description'];
        const errors = Object.fromEntries(
            fields.map((field) => [field, [`UI009 erro ${field}`]]),
        );

        throw new AxiosError(
            'UI009 finite 422',
            AxiosError.ERR_BAD_REQUEST,
            config,
            undefined,
            response(config, { message: 'UI009 validação', errors }, 422),
        );
    }

    forbidden++;

    throw new Error(
        `UI009 unexpected adapter request ${method} ${url.pathname}`,
    );
};
http.defaults.adapter = adapter;

window.addEventListener('unhandledrejection', (event) => {
    if (
        event.reason instanceof Error &&
        event.reason.message === 'UI009 clipboard rejection'
    ) {
        expectedRejections.push(event.reason.message);
    }
});

function installClipboard(mode: CopyMode): void {
    resolveCopy = null;
    Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value:
            mode === 'missing'
                ? undefined
                : mode === 'invalid'
                  ? { writeText: 'not-a-function' }
                  : {
                        writeText(text: string): Promise<void> {
                            clipboardWrites.push(text);

                            if (mode === 'rejected') {
                                return Promise.reject(
                                    new Error('UI009 clipboard rejection'),
                                );
                            }

                            if (mode === 'pending') {
                                return new Promise<void>((resolve, reject) => {
                                    resolveCopy = resolve;
                                    copyGates.push({
                                        resolve,
                                        reject: () =>
                                            reject(
                                                new Error(
                                                    'UI009 clipboard rejection',
                                                ),
                                            ),
                                    });
                                });
                            }

                            return Promise.resolve();
                        },
                    },
    });
}

function restoreClipboard(): void {
    if (originalClipboard) {
        Object.defineProperty(navigator, 'clipboard', originalClipboard);
    } else {
        Reflect.deleteProperty(navigator, 'clipboard');
    }
}

function ClipboardProbe() {
    const [copied, copy] = useClipboard();
    const [outcome, setOutcome] = useState('idle');
    const [identity] = useState(() => revision);

    return (
        <section id="clipboard-scene" data-revision={identity}>
            <button
                type="button"
                onClick={() => {
                    void copy('UI009 texto sintético').then((success) => {
                        copyOutcomes.push(success);

                        if (
                            document.getElementById('clipboard-scene')?.dataset
                                .revision === String(identity)
                        ) {
                            setOutcome(success ? 'success' : 'failure');
                        }
                    });
                }}
            >
                Copiar texto sintético
            </button>
            <output id="copied-value">{copied ?? '(null)'}</output>
            <output id="copy-outcome">{outcome}</output>
        </section>
    );
}

function Editors({ disabled = false }: { disabled?: boolean }) {
    const [first, setFirst] = useState('<p>Editor um</p>');
    const [second, setSecond] = useState('<p>Editor dois</p>');
    const [readOnly, setReadOnly] = useState(disabled);
    useLayoutEffect(() => {
        setEditorReadOnly = setReadOnly;

        return () => {
            setEditorReadOnly = null;
        };
    }, []);

    return (
        <>
            <section id="editor-one">
                <h2>Editor um</h2>
                <RichTextEditor
                    value={first}
                    onChange={setFirst}
                    disabled={readOnly}
                />
            </section>
            <section id="editor-two">
                <h2>Editor dois</h2>
                <RichTextEditor
                    value={second}
                    onChange={setSecond}
                    disabled={readOnly}
                />
            </section>
        </>
    );
}

function SceneBody({ scene }: { scene: Scene }) {
    const [twoFactorOpen, setTwoFactorOpen] = useState(true);

    if (scene === 'forms') {
        return (
            <>
                <section id="role-scene">
                    <EditRole roleData={{ data: role }} />
                </section>
                <section id="permission-scene">
                    <EditPermission permissionData={{ data: permission }} />
                </section>
            </>
        );
    }

    if (scene === 'editors' || scene === 'readonly') {
        return <Editors disabled={scene === 'readonly'} />;
    }

    if (scene === 'composer') {
        return (
            <section id="composer-scene">
                <CommentComposer
                    label="Comentário UI009"
                    initialFormat="html"
                    initialValue="<p>Comentário sintético</p>"
                    onSubmit={() => {
                        throw new Error('UI009 forbids comment submit.');
                    }}
                />
            </section>
        );
    }

    if (scene === 'clipboard') {
        return <ClipboardProbe />;
    }

    if (scene === 'design') {
        return (
            <section id="design-scene">
                <DesignSystem />
            </section>
        );
    }

    return (
        <TwoFactorSetupModal
            isOpen={twoFactorOpen}
            onClose={() => setTwoFactorOpen(false)}
            requiresConfirmation={true}
            twoFactorEnabled={false}
            qrCodeSvg={
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" /></svg>'
            }
            manualSetupKey="UI009SYNTHETICKEY"
            clearSetupData={() => {}}
            fetchSetupData={async () => {
                throw new Error('UI009 forbids 2FA fetch.');
            }}
            errors={[]}
        />
    );
}

function AccessibilityProbe() {
    const [view, setView] = useState<{ scene: Scene; revision: number }>({
        scene: 'editors',
        revision: 0,
    });
    useLayoutEffect(() => {
        changeScene = (scene, nextRevision) =>
            setView({ scene, revision: nextRevision });

        return () => {
            changeScene = null;
        };
    }, []);

    return <SceneBody key={view.revision} scene={view.scene} />;
}

void createInertiaApp({
    page: {
        component: 'Ui009AccessibilityFixture',
        props: {
            errors: {},
            auth: {
                user: {
                    id: 900009,
                    email_verified_at: '2026-01-01',
                    must_change_password: false,
                },
            },
        },
        url: location.pathname + location.search,
        version: null,
        flash: {},
        rememberedState: {},
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: async () =>
        Object.assign(AccessibilityProbe, { layout: [PrivateStateLayout] }),
    strictMode: true,
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('UI009 requires its existing DOM mount element.');
        }

        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
});

function assert(value: unknown, message: string): asserts value {
    if (!value) {
        throw new Error(message);
    }
}
const delay = (milliseconds = 25) =>
    new Promise<void>((resolve) => setTimeout(resolve, milliseconds));
async function until(predicate: () => boolean, message: string): Promise<void> {
    for (let attempt = 0; attempt < 80; attempt++) {
        if (predicate()) {
            return;
        }

        await delay();
    }

    throw new Error(message);
}
function element<T extends HTMLElement = HTMLElement>(
    selector: string,
    container: ParentNode = document,
): T {
    const target = container.querySelector<T>(selector);
    assert(target, `Fixture could not find ${selector}`);

    return target;
}
function button(
    text: string,
    container: ParentNode = document,
): HTMLButtonElement {
    const found = Array.from(
        container.querySelectorAll<HTMLButtonElement>('button'),
    ).find(
        (candidate) =>
            candidate.textContent?.trim() === text || candidate.title === text,
    );
    assert(found, `Fixture could not find button ${text}`);

    return found;
}
async function prepare(scene: Scene): Promise<void> {
    await until(
        () => changeScene !== null,
        'PrivateState/Inertia did not mount.',
    );
    toast.dismiss();
    flushSync(() => changeScene!(scene, ++revision));
    await delay(80);

    if (scene === 'editors' || scene === 'readonly') {
        await until(
            () => rootElement.querySelectorAll('.ProseMirror').length === 2,
            'Two editors did not mount.',
        );
    }

    if (scene === 'composer') {
        await until(
            () => rootElement.querySelector('.ProseMirror') !== null,
            'Rich composer did not mount.',
        );
    }

    if (scene === 'two-factor') {
        await until(
            () =>
                document.querySelector('[role="dialog"] input[readonly]') !==
                null,
            '2FA setup did not mount.',
        );
    }

    await delay(80);
}
function accessibleName(target: HTMLElement): string {
    const labelledBy = target.getAttribute('aria-labelledby');

    if (labelledBy) {
        return labelledBy
            .split(/\s+/)
            .map((id) => document.getElementById(id)?.textContent?.trim() ?? '')
            .join(' ')
            .trim();
    }

    return target.getAttribute('aria-label')?.trim() ?? '';
}
function associatedInputs(container: ParentNode, count: number): void {
    const inputs = Array.from(
        container.querySelectorAll<HTMLInputElement>(
            'input:not([type="hidden"])',
        ),
    );
    assert(
        inputs.length === count,
        `Expected ${count} form inputs, got ${inputs.length}.`,
    );

    for (const input of inputs) {
        assert(
            input.id &&
                input.labels?.length &&
                Array.from(input.labels).some(
                    (label) =>
                        label.control === input && label.textContent?.trim(),
                ),
            `Input ${input.placeholder} lacks an associated label.`,
        );
    }
}
async function submit422(container: HTMLElement, count: number): Promise<void> {
    const before = requests.length;
    element<HTMLFormElement>('form', container).requestSubmit();
    await until(
        () => container.textContent?.includes('UI009 erro') === true,
        'Controlled validation errors were not rendered.',
    );
    assert(
        requests.slice(before).filter((request) => request.method === 'PUT')
            .length === 1,
        'Expected exactly one controlled PUT.',
    );
    const inputs = Array.from(
        container.querySelectorAll<HTMLInputElement>('input'),
    );
    assert(inputs.length === count, 'Field count changed.');
    const fields =
        count === 3
            ? ['name', 'slug', 'description']
            : ['name', 'slug', 'group', 'description'];
    const describedErrors = new Set<string>();

    for (const [index, input] of inputs.entries()) {
        assert(
            input.getAttribute('aria-invalid') === 'true',
            'Invalid field lacks aria-invalid.',
        );
        const ids = input.getAttribute('aria-describedby')?.split(/\s+/) ?? [];
        const ownError = ids.find(
            (id) =>
                document.getElementById(id)?.textContent?.trim() ===
                `UI009 erro ${fields[index]}`,
        );
        assert(
            ownError && !describedErrors.has(ownError),
            'Invalid field is not described by its own distinct validation error.',
        );
        assert(
            Array.from(document.querySelectorAll('[id]')).filter(
                (node) => node.id === ownError,
            ).length === 1,
            'Validation error ID is duplicated.',
        );
        describedErrors.add(ownError);
    }
}
function closeDialog(): void {
    button('Close', element('[role="dialog"]')).click();
}
async function openDialog(index: 1 | 2, title: string): Promise<HTMLElement> {
    button(title, element(index === 1 ? '#editor-one' : '#editor-two')).click();
    await until(
        () => document.querySelector('[role="dialog"]') !== null,
        'Editor dialog did not open.',
    );
    await delay(50);

    return element('[role="dialog"]');
}
function assertDialog(dialog: HTMLElement): HTMLInputElement {
    const input = element<HTMLInputElement>('input', dialog);
    assert(
        input.id &&
            input.labels?.length &&
            Array.from(input.labels).some((label) => label.control === input),
        'Dialog input lacks a label bound to this instance.',
    );
    const descriptions =
        dialog.getAttribute('aria-describedby')?.split(/\s+/) ?? [];
    assert(
        descriptions.some((id) =>
            document.getElementById(id)?.textContent?.trim(),
        ),
        'Dialog has no useful description.',
    );

    return input;
}
function imageZone(dialog: HTMLElement): HTMLElement {
    return element('[class*="min-h-36"]', dialog);
}
function interceptFilePicker(): void {
    for (const input of rootElement.querySelectorAll<HTMLInputElement>(
        'input[type="file"]',
    )) {
        input.click = () => {
            filePickerRequests++;
        };
    }
}
function focusStyle(target: HTMLElement): {
    outline: string;
    shadow: string;
    opacity: string;
} {
    const style = getComputedStyle(target);

    return {
        outline: `${style.outlineStyle}/${style.outlineWidth}/${style.outlineColor}`,
        shadow: style.boxShadow,
        opacity: style.opacity,
    };
}
async function verifyFocus(target: HTMLElement): Promise<void> {
    const initiallyFocused = document.activeElement === target;
    target.blur();
    element<HTMLInputElement>('#keyboard-origin').focus();
    await delay(200);
    const before = focusStyle(target);
    const beforeVisible = target.matches(':focus-visible');
    target.scrollIntoView({ block: 'center' });
    target.focus();
    await until(
        () => getComputedStyle(target).opacity === '1',
        'Keyboard focus did not reveal the control within the bounded wait.',
    );
    const after = focusStyle(target);
    assert(
        document.activeElement === target && target.matches(':focus-visible'),
        'Control did not receive focus-visible.',
    );
    assert(
        after.opacity !== '0' &&
            ((after.shadow !== 'none' && after.shadow !== before.shadow) ||
                (after.outline !== before.outline &&
                    !after.outline.startsWith('none/'))),
        `Keyboard focus has no visible outline or ring. ${JSON.stringify({ initiallyFocused, beforeVisible, connected: target.isConnected, before, after, transition: getComputedStyle(target).transitionProperty })}`,
    );
}
function errorToast(): boolean {
    return Array.from(
        document.querySelectorAll('[data-sonner-toast][data-type="error"]'),
    ).some(
        (node) =>
            node.getAttribute('data-removed') !== 'true' &&
            /cop|clipboard|área de transferência/i.test(
                node.textContent ?? '',
            ) &&
            /manual|selecione|ctrl|command/i.test(node.textContent ?? '') &&
            !node.closest('[aria-hidden="true"]'),
    );
}
function setInput(input: HTMLInputElement, value: string): void {
    Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value',
    )!.set!.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
async function check(name: string, run: () => Promise<void>): Promise<void> {
    try {
        await run();
        checks.push({ name, status: 'passed' });
    } catch (error) {
        checks.push({ name, status: 'failed', error: String(error) });
    } finally {
        for (const gate of copyGates) {
            gate.resolve();
        }

        await delay();
        restoreClipboard();
        resolveCopy = null;
    }

    result.textContent = JSON.stringify({ status: 'running', checks }, null, 2);
}

async function run(): Promise<void> {
    checks.length = 0;
    await check('role-three-labels', async () => {
        await prepare('forms');
        associatedInputs(element('#role-scene'), 3);
    });
    await check('permission-four-labels', async () => {
        await prepare('forms');
        associatedInputs(element('#permission-scene'), 4);
    });
    await check('role-validation-aria', async () => {
        await prepare('forms');
        await submit422(element('#role-scene'), 3);
    });
    await check('permission-validation-aria', async () => {
        await prepare('forms');
        await submit422(element('#permission-scene'), 4);
    });
    await check('editor-textbox-names', async () => {
        await prepare('editors');

        for (const textbox of rootElement.querySelectorAll<HTMLElement>(
            '.ProseMirror',
        )) {
            assert(
                textbox.getAttribute('role') === 'textbox' &&
                    textbox.getAttribute('aria-multiline') === 'true' &&
                    accessibleName(textbox),
                'Editable area lacks a named multiline textbox role.',
            );
        }
    });
    await check('editor-toolbar-names', async () => {
        await prepare('editors');
        const bars =
            rootElement.querySelectorAll<HTMLElement>('[role="toolbar"]');
        assert(
            bars.length === 2 &&
                Array.from(bars).every((bar) => accessibleName(bar)),
            'Editor toolbars lack accessible names.',
        );
    });
    await check('editor-toolbar-pressed-state', async () => {
        await prepare('editors');
        const bold = button('Negrito (Ctrl+B)', element('#editor-one'));
        assert(
            (accessibleName(bold) || bold.title) &&
                bold.getAttribute('aria-pressed') === 'false',
            'Formatting toggle lacks accessible pressed state.',
        );
        bold.click();
        await delay();
        assert(
            bold.getAttribute('aria-pressed') === 'true',
            'Pressed state does not follow the mark.',
        );
    });
    await check('editor-readonly-toolbar', async () => {
        await prepare('readonly');
        assert(
            Array.from(
                rootElement.querySelectorAll<HTMLButtonElement>(
                    'button[title]',
                ),
            ).every((target) => target.disabled),
            'Read-only editor exposes enabled toolbar controls.',
        );
    });
    await check('open-link-dialog-then-readonly-cannot-save', async () => {
        await prepare('editors');
        const editor = element<HTMLElement & { editor: Editor }>(
            '.ProseMirror',
            element('#editor-one'),
        ).editor;
        editor.commands.setTextSelection({
            from: 1,
            to: editor.state.doc.content.size - 1,
        });
        const dialog = await openDialog(1, 'Inserir / editar link');
        const input = element<HTMLInputElement>('input', dialog);
        setInput(input, 'https://ui009.invalid/owned');
        await delay();
        const before = element(
            '.ProseMirror',
            element('#editor-one'),
        ).innerHTML;
        assert(setEditorReadOnly, 'Editor control not mounted.');
        flushSync(() => setEditorReadOnly!(true));
        await delay();

        if (dialog.isConnected) {
            button('Salvar', dialog).click();
        }

        await delay();
        assert(
            element('.ProseMirror', element('#editor-one')).innerHTML ===
                before,
            'A dialog opened before read-only can still modify the document.',
        );
    });

    for (const title of ['Inserir / editar link', 'Inserir imagem']) {
        await check(
            title === 'Inserir imagem'
                ? 'image-dialog-instance-label-description'
                : 'link-dialog-instance-label-description',
            async () => {
                await prepare('editors');
                const first = assertDialog(await openDialog(1, title)).id;
                closeDialog();
                await until(
                    () => !document.querySelector('[role="dialog"]'),
                    'First dialog did not close.',
                );
                const second = assertDialog(await openDialog(2, title)).id;
                assert(
                    first !== second,
                    'Two editor instances reuse a dialog input ID.',
                );
                closeDialog();
            },
        );
    }

    for (const key of ['Enter', ' ']) {
        await check(
            key === 'Enter' ? 'image-zone-enter' : 'image-zone-space',
            async () => {
                await prepare('editors');
                interceptFilePicker();
                const dialog = await openDialog(1, 'Inserir imagem');
                const zone = imageZone(dialog);
                const previous = filePickerRequests;
                assert(
                    zone.tabIndex >= 0 &&
                        (zone.tagName === 'BUTTON' ||
                            zone.getAttribute('role') === 'button') &&
                        (accessibleName(zone) || zone.textContent?.trim()),
                    'Image selection zone is not a named keyboard button.',
                );
                zone.focus();
                zone.dispatchEvent(
                    new KeyboardEvent('keydown', {
                        key,
                        code: key === 'Enter' ? 'Enter' : 'Space',
                        bubbles: true,
                        cancelable: true,
                    }),
                );

                if (zone instanceof HTMLButtonElement) {
                    zone.click();
                }

                assert(
                    filePickerRequests === previous + 1,
                    'Keyboard activation did not request the owned file input.',
                );
                closeDialog();
            },
        );
    }

    await check('rich-composer-label', async () => {
        await prepare('composer');
        const label = element<HTMLLabelElement>(
            'label',
            element('#composer-scene'),
        );
        const textbox = element('.ProseMirror');
        assert(
            label.id &&
                textbox
                    .getAttribute('aria-labelledby')
                    ?.split(/\s+/)
                    .includes(label.id) &&
                accessibleName(textbox) === 'Comentário UI009',
            'Rich composer is not named by its visible label.',
        );
    });

    for (const dark of [false, true]) {
        await check(
            dark
                ? 'toolbar-focus-dark-real-css'
                : 'toolbar-focus-light-real-css',
            async () => {
                await prepare('editors');
                document.documentElement.classList.toggle('dark', dark);
                await delay();
                await verifyFocus(
                    button('Negrito (Ctrl+B)', element('#editor-one')),
                );
            },
        );
    }

    await check('clipboard-missing-visible-feedback', async () => {
        await prepare('clipboard');
        installClipboard('missing');
        button('Copiar texto sintético').click();
        await until(
            () => element('#copy-outcome').textContent === 'failure',
            'Missing API did not return false.',
        );
        assert(
            element('#copied-value').textContent === '(null)' && errorToast(),
            'Missing clipboard has no visible manual-copy feedback.',
        );
    });
    await check('clipboard-rejection-visible-feedback', async () => {
        await prepare('clipboard');
        installClipboard('rejected');
        button('Copiar texto sintético').click();
        await until(
            () => element('#copy-outcome').textContent === 'failure',
            'Rejected clipboard did not return false.',
        );
        assert(
            element('#copied-value').textContent === '(null)' && errorToast(),
            'Rejected clipboard has no visible feedback.',
        );
    });
    await check('clipboard-invalid-method-visible-feedback', async () => {
        await prepare('clipboard');
        installClipboard('invalid');
        button('Copiar texto sintético').click();
        await until(
            () => element('#copy-outcome').textContent === 'failure',
            'Invalid clipboard method did not return false.',
        );
        assert(
            errorToast() &&
                !document
                    .querySelector('[data-sonner-toast]')
                    ?.textContent?.includes('UI009 texto sintético'),
            'Invalid API has no safe feedback or exposes copied text.',
        );
    });
    await check('clipboard-pending-then-success', async () => {
        await prepare('clipboard');
        installClipboard('pending');
        button('Copiar texto sintético').click();
        await until(() => resolveCopy !== null, 'Finite clipboard not called.');
        assert(
            element('#copied-value').textContent === '(null)' &&
                element('#copy-outcome').textContent === 'idle',
            'Hook reported success before write resolved.',
        );
        resolveCopy!();
        await until(
            () => element('#copy-outcome').textContent === 'success',
            'Resolved clipboard did not return true.',
        );
        assert(
            element('#copied-value').textContent === 'UI009 texto sintético',
            'Successful copied value was not retained.',
        );
    });
    await check('clipboard-missing-clears-old-success', async () => {
        await prepare('clipboard');
        installClipboard('success');
        button('Copiar texto sintético').click();
        await until(
            () => element('#copy-outcome').textContent === 'success',
            'Synthetic success did not settle.',
        );
        installClipboard('missing');
        button('Copiar texto sintético').click();
        await until(
            () => element('#copy-outcome').textContent === 'failure',
            'Second copy did not fail.',
        );
        assert(
            element('#copied-value').textContent === '(null)' && errorToast(),
            'Unavailable clipboard retained stale success.',
        );
    });
    await check('clipboard-new-pending-clears-old-success', async () => {
        await prepare('clipboard');
        installClipboard('success');
        button('Copiar texto sintético').click();
        await until(
            () => element('#copy-outcome').textContent === 'success',
            'First success did not settle.',
        );
        installClipboard('pending');
        button('Copiar texto sintético').click();
        await until(() => resolveCopy !== null, 'Second copy did not start.');
        assert(
            element('#copied-value').textContent === '(null)',
            'New pending attempt retained a previous success.',
        );
        resolveCopy!();
        await delay();
    });
    await check('clipboard-out-of-order-result-ignored', async () => {
        await prepare('clipboard');
        installClipboard('pending');
        const before = copyGates.length;
        button('Copiar texto sintético').click();
        button('Copiar texto sintético').click();
        await until(
            () => copyGates.length === before + 2,
            'Two finite copy operations did not start.',
        );
        copyGates[before + 1].reject();
        await until(
            () => element('#copy-outcome').textContent === 'failure',
            'Latest rejected copy did not settle.',
        );
        copyGates[before].resolve();
        await delay(80);
        assert(
            element('#copied-value').textContent === '(null)',
            'Older copy restored a false success after newer failure.',
        );
    });
    await check('clipboard-late-unmount-no-feedback', async () => {
        await prepare('clipboard');
        installClipboard('pending');
        button('Copiar texto sintético').click();
        await until(() => resolveCopy !== null, 'Deferred copy did not start.');
        const gate = resolveCopy!;
        await prepare('editors');
        const previous = document.querySelectorAll(
            '[data-sonner-toast][data-type="error"]',
        ).length;
        const completions = copyOutcomes.length;
        gate();
        await delay(80);
        assert(
            copyOutcomes.length === completions + 1 &&
                copyOutcomes.at(-1) === false &&
                document.querySelectorAll(
                    '[data-sonner-toast][data-type="error"]',
                ).length === previous &&
                !document.querySelector('#clipboard-scene'),
            'Unmounted copy reported late success or feedback.',
        );
    });

    for (const mode of ['pending', 'rejected', 'success'] as const) {
        await check(`actual-design-codeblock-${mode}`, async () => {
            await prepare('design');
            installClipboard(mode);
            const typography = element('#typography');
            button('Código', typography).click();
            await delay();
            const copy = button('Copiar', typography);
            copy.click();
            await delay(80);

            if (mode === 'pending') {
                assert(
                    resolveCopy &&
                        copy.textContent?.includes('Copiar') &&
                        !copy.textContent?.includes('Copiado'),
                    'CodeBlock reports Copiado before clipboard resolves.',
                );
                resolveCopy();
                await until(
                    () => copy.textContent?.includes('Copiado') === true,
                    'CodeBlock did not show confirmed success.',
                );
            } else if (mode === 'rejected') {
                assert(
                    !copy.textContent?.includes('Copiado') && errorToast(),
                    'CodeBlock reports false success or hides failure.',
                );
            } else {
                assert(
                    copy.textContent?.includes('Copiado') &&
                        clipboardWrites.at(-1)?.includes('Heading'),
                    'CodeBlock did not await/copy its real code.',
                );
            }
        });
    }

    await check('actual-design-codeblock-keyboard-visibility', async () => {
        await prepare('design');
        const typography = element('#typography');
        button('Código', typography).click();
        await delay();
        await verifyFocus(button('Copiar', typography));
    });
    await check('two-factor-setup-control-names', async () => {
        await prepare('two-factor');
        const dialog = element('[role="dialog"]');
        const input = element<HTMLInputElement>('input[readonly]', dialog);
        const copy =
            input.parentElement!.querySelector<HTMLButtonElement>('button')!;
        assert(
            accessibleName(input) || input.labels?.length,
            'Manual setup key has no accessible name.',
        );
        assert(
            copy && accessibleName(copy),
            'Setup key copy button has no accessible name.',
        );
    });
    await check('two-factor-copy-failure-feedback', async () => {
        await prepare('two-factor');
        installClipboard('rejected');
        const input = element<HTMLInputElement>(
            '[role="dialog"] input[readonly]',
        );
        const copy = element<HTMLButtonElement>('button', input.parentElement!);
        copy.click();
        await delay(80);
        const notice = document.querySelector<HTMLElement>(
            '[data-sonner-toast][data-type="error"]',
        );
        assert(
            errorToast() &&
                notice &&
                !notice.closest('[aria-hidden="true"]') &&
                !copy.querySelector('.lucide-check'),
            '2FA key copy failure has no accessible feedback or false check icon.',
        );
    });
    await check('two-factor-key-and-copy-focus', async () => {
        await prepare('two-factor');
        const input = element<HTMLInputElement>(
            '[role="dialog"] input[readonly]',
        );
        await verifyFocus(input);
        await verifyFocus(
            element<HTMLButtonElement>('button', input.parentElement!),
        );
    });
    await check('two-factor-verification-code-name', async () => {
        await prepare('two-factor');
        button('Continue', element('[role="dialog"]')).click();
        await until(
            () =>
                document.querySelector('[role="dialog"] input[name="code"]') !==
                null,
            'Verification input did not mount.',
        );
        const input = element<HTMLInputElement>(
            '[role="dialog"] input[name="code"]',
        );
        assert(
            accessibleName(input) || input.labels?.length,
            'Actual verification code input has no accessible name.',
        );
        assert(
            !requests.some((request) => request.path.includes('two-factor')),
            'Fixture called 2FA server.',
        );
    });
    await check('no-unhandled-clipboard-rejections', async () => {
        await delay(80);
        assert(
            expectedRejections.length === 0,
            'Clipboard consumer left an unhandled rejected Promise.',
        );
    });
    document.documentElement.classList.remove('dark');
    await prepare('editors');
    result.textContent = JSON.stringify(
        {
            status:
                checks.every((item) => item.status === 'passed') &&
                forbidden === 0
                    ? 'passed'
                    : 'failed',
            checks,
            requests,
            clipboardWrites,
            expectedRejections,
            forbiddenNetwork: forbidden,
            originalClipboardAvailable,
            secureContext: window.isSecureContext,
            nativeClipboardWrites: 0,
            filePickerRequests,
        },
        null,
        2,
    );
}

document.getElementById('run-contracts')!.addEventListener('click', () => {
    const trigger = element<HTMLButtonElement>('#run-contracts');
    trigger.disabled = true;
    void run()
        .catch((error) => {
            result.textContent = JSON.stringify({
                status: 'fixture-error',
                error: String(error),
                checks,
                forbidden,
            });
        })
        .finally(() => {
            restoreClipboard();
            trigger.disabled = false;
        });
});
document.getElementById('prepare-manual')!.addEventListener('click', () => {
    installClipboard('rejected');
    const scene = element<HTMLSelectElement>('#manual-scene').value as Scene;
    void prepare(scene).then(() => {
        interceptFilePicker();
        result.textContent = `Cena ${scene} pronta. Clipboard finito rejeitado. File picker interceptado. Use Tab/Enter/Espaço reais.`;
    });
});
document
    .getElementById('toggle-theme')!
    .addEventListener('click', () =>
        document.documentElement.classList.toggle('dark'),
    );
document.addEventListener('focusin', (event) => {
    if (event.target instanceof HTMLElement) {
        const target = event.target;
        document.getElementById('focus-audit')!.textContent = JSON.stringify(
            {
                tag: target.tagName,
                role: target.getAttribute('role'),
                id: target.id,
                name: accessibleName(target),
                title: target.title,
                text: target.textContent?.trim().slice(0, 100),
                focusVisible: target.matches(':focus-visible'),
                style: focusStyle(target),
                filePickerRequests,
            },
            null,
            2,
        );
    }
});
window.addEventListener('pagehide', restoreClipboard);
result.textContent = 'Pronta para executar os contratos UI-009.';

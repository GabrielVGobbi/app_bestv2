import type { Editor, TiptapEditorHTMLElement } from '@tiptap/core';
import { closeHistory } from '@tiptap/pm/history';
import { StrictMode, useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';

import { RichTextEditor } from '@/components/rich-text-editor';
import type { CommentPayload } from '@/modules/timeline/api/types';
import { CommentComposer } from '@/modules/timeline/components/comment-composer';

type Upload = 'none' | 'immediate' | 'deferred';
type View = {
    id: number;
    kind: 'editor' | 'composer';
    value: string;
    format: 'plain' | 'html';
    upload: Upload;
};
type Gate = { file: File; settled: boolean; resolve: (url: string) => void };
type Check = { name: string; status: 'passed' | 'failed'; error?: string };
type Bridge = {
    mount: (view: View) => void;
    unmount: () => void;
    external: (value: string) => void;
    echo: () => void;
    disable: (disabled: boolean) => void;
};

const app = document.getElementById('app')!;
const result = document.getElementById('fixture-result')!;
const runButton = document.getElementById('run-contracts') as HTMLButtonElement;
const imageUrl = document.querySelector<HTMLMetaElement>(
    'meta[name="ui007-owned-image"]',
)!.content;
const png = Uint8Array.from(
    atob(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=',
    ),
    (char) => char.charCodeAt(0),
);
const emissions: string[] = [];
const allEmissions: string[] = [];
const submissions: CommentPayload[] = [];
const created: string[] = [];
const revoked: string[] = [];
const live = new Set<string>();
const gates: Gate[] = [];
const checks: Check[] = [];
let bridge: Bridge | null = null;
let revision = 0;
let forbiddenNetwork = 0;
let forcedCleanup = 0;
let uploads = 0;

const nativeCreate = URL.createObjectURL.bind(URL);
const nativeRevoke = URL.revokeObjectURL.bind(URL);
URL.createObjectURL = (blob) => {
    const url = nativeCreate(blob);
    created.push(url);
    live.add(url);

    return url;
};
URL.revokeObjectURL = (url) => {
    revoked.push(url);
    live.delete(url);
    nativeRevoke(url);
};
function denyNetwork(): never {
    ++forbiddenNetwork;

    throw new Error(
        'UI007 forbids real API, upload and external network calls.',
    );
}
globalThis.fetch = denyNetwork;
XMLHttpRequest.prototype.open = denyNetwork;

if (navigator.sendBeacon) {
    navigator.sendBeacon = denyNetwork;
}

function assert(condition: unknown, message: string): asserts condition {
    if (!condition) {
        throw new Error(message);
    }
}
const wait = (milliseconds = 25) =>
    new Promise<void>((resolve) => setTimeout(resolve, milliseconds));
async function until(
    condition: () => boolean,
    message: string,
    timeout = 1400,
) {
    const end = performance.now() + timeout;

    while (performance.now() < end) {
        if (condition()) {
            return;
        }

        await wait();
    }

    assert(condition(), message);
}
async function settle() {
    await wait();
    await wait();
}

function uploadImmediate(): Promise<string> {
    ++uploads;

    return Promise.resolve(imageUrl);
}
function uploadDeferred(file: File): Promise<string> {
    ++uploads;

    return new Promise((resolve) =>
        gates.push({ file, settled: false, resolve: (url) => resolve(url) }),
    );
}
function EditorProbe({ view }: { view: View }) {
    const [value, setValue] = useState(view.value);
    const [disabled, setDisabled] = useState(false);
    const [, setEcho] = useState(0);
    useLayoutEffect(() => {
        if (bridge) {
            bridge.external = setValue;
            bridge.disable = setDisabled;
            bridge.echo = () => setEcho((old) => old + 1);
        }
    }, []);

    return (
        <RichTextEditor
            value={value}
            disabled={disabled}
            onChange={(html) => {
                emissions.push(html);
                allEmissions.push(html);
                setValue(html);
            }}
            onImageUpload={
                view.upload === 'immediate'
                    ? uploadImmediate
                    : view.upload === 'deferred'
                      ? uploadDeferred
                      : undefined
            }
        />
    );
}
function Harness() {
    const [view, setView] = useState<View | null>(null);
    useLayoutEffect(() => {
        bridge = {
            mount: setView,
            unmount: () => setView(null),
            external: () => {
                throw new Error('Editor is not mounted.');
            },
            echo: () => {},
            disable: () => {},
        };

        return () => {
            bridge = null;
        };
    }, []);

    if (!view) {
        return null;
    }

    return view.kind === 'editor' ? (
        <EditorProbe key={view.id} view={view} />
    ) : (
        <CommentComposer
            key={view.id}
            initialValue={view.value}
            initialFormat={view.format}
            onSubmit={(payload) => {
                submissions.push(payload);
            }}
        />
    );
}

createRoot(app).render(
    <StrictMode>
        <Harness />
    </StrictMode>,
);

function editor(): Editor {
    const instance =
        app.querySelector<TiptapEditorHTMLElement>('.ProseMirror')?.editor;
    assert(
        instance && !instance.isDestroyed,
        'The real Tiptap editor is not mounted.',
    );

    return instance;
}
function editorElement(): HTMLElement {
    return editor().view.dom;
}
function button(text: string, root: ParentNode = document): HTMLButtonElement {
    const found = Array.from(
        root.querySelectorAll<HTMLButtonElement>('button'),
    ).find((item) => item.textContent?.trim() === text);
    assert(found, `Missing button: ${text}`);

    return found;
}
function toolbar(title: string): HTMLButtonElement {
    const found = Array.from(
        app.querySelectorAll<HTMLButtonElement>('button'),
    ).find((item) => item.title === title);
    assert(found, `Missing toolbar action: ${title}`);

    return found;
}
function dialog(): HTMLElement {
    const found = document.querySelector<HTMLElement>('[role="dialog"]');
    assert(found, 'The real image dialog is not open.');

    return found;
}
function imageUrlInput(): HTMLInputElement {
    const label = Array.from(dialog().querySelectorAll('label')).find(
        (item) => item.textContent?.trim() === 'URL da imagem',
    );
    const control = label?.control;
    assert(
        control instanceof HTMLInputElement,
        'The image URL label is not associated with its input.',
    );

    return control;
}
function setInput(
    input: HTMLInputElement | HTMLTextAreaElement,
    value: string,
) {
    const prototype =
        input instanceof HTMLTextAreaElement
            ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value')!.set!.call(
        input,
        value,
    );
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
function imageFile(name = 'owned-image.png'): File {
    return new File([png], name, { type: 'image/png' });
}
function choose(file = imageFile()) {
    const input = app.querySelector<HTMLInputElement>('input[type="file"]');
    assert(input, 'The real file input is missing.');
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
function paste(file = imageFile()) {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    editorElement().dispatchEvent(
        new ClipboardEvent('paste', {
            bubbles: true,
            cancelable: true,
            clipboardData: transfer,
        }),
    );
}
function drop(file = imageFile()) {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    const target = editorElement();
    const rect = target.getBoundingClientRect();
    target.dispatchEvent(
        new DragEvent('drop', {
            bubbles: true,
            cancelable: true,
            dataTransfer: transfer,
            clientX: rect.left + 12,
            clientY: rect.top + 12,
        }),
    );
}
function gate(): Gate {
    const pending = gates.find((item) => !item.settled);
    assert(pending, 'The finite image callback has not started.');

    return pending;
}
function resolveGate(url = imageUrl) {
    const pending = gate();
    pending.settled = true;
    pending.resolve(url);
}
async function mount(
    kind: View['kind'] = 'editor',
    value = '<p>Texto inicial</p>',
    upload: Upload = 'none',
    format: View['format'] = 'plain',
) {
    assert(bridge, 'The React fixture is not ready.');
    flushSync(() => bridge!.unmount());
    await settle();
    emissions.length = 0;
    submissions.length = 0;
    gates.length = 0;
    const view = { id: ++revision, kind, value, upload, format };
    flushSync(() => bridge!.mount(view));
    await until(
        () =>
            kind === 'composer' && format === 'plain'
                ? Boolean(app.querySelector('textarea'))
                : Boolean(app.querySelector('.ProseMirror')),
        'Component mount did not finish.',
    );
    await settle();
}
async function openImage() {
    toolbar('Inserir imagem').click();
    await until(
        () => Boolean(document.querySelector('[role="dialog"]')),
        'Image dialog did not mount.',
    );
}
async function choosePreview(name = 'first.png'): Promise<string> {
    choose(imageFile(name));
    await until(
        () =>
            Boolean(
                document.querySelector('[role="dialog"] img[src^="blob:"]'),
            ),
        'Local image preview did not appear.',
    );

    return dialog().querySelector<HTMLImageElement>('img')!.src;
}
async function localDocumentImage(): Promise<string> {
    editor().commands.focus('end');
    paste();
    await until(
        () => Boolean(app.querySelector('.ProseMirror img[src^="blob:"]')),
        'The local document preview was not inserted.',
    );
    await settle();

    return app.querySelector<HTMLImageElement>('.ProseMirror img')!.src;
}
function assertNoBlobEmission() {
    assert(
        emissions.every((html) => !/blob:/i.test(html)),
        'A temporary blob URL escaped through onChange.',
    );
}
async function runCase(name: string, action: () => Promise<void>) {
    const firstUrl = created.length;

    try {
        await action();
        checks.push({ name, status: 'passed' });
    } catch (error) {
        checks.push({
            name,
            status: 'failed',
            error: error instanceof Error ? error.message : String(error),
        });
    } finally {
        if (bridge) {
            flushSync(() => bridge!.unmount());
        }

        for (const pending of gates.filter((item) => !item.settled)) {
            pending.settled = true;
            pending.resolve(imageUrl);
        }

        await settle();

        // A failing baseline must not leave the browser fixture retaining blobs.
        // This direct native cleanup is deliberately NOT a product revoke receipt.
        for (const url of created.slice(firstUrl)) {
            if (live.delete(url)) {
                nativeRevoke(url);
                ++forcedCleanup;
            }
        }

        document.documentElement.classList.remove('dark');
    }
}

async function run() {
    runButton.disabled = true;
    checks.length = 0;
    allEmissions.length = 0;
    await until(() => Boolean(bridge), 'React fixture did not initialize.');
    const proofImage = new Image();
    proofImage.src = imageUrl;
    await until(
        () => proofImage.complete && proofImage.naturalWidth === 1,
        'The owned emitted PNG is not a valid served image.',
    );

    await runCase(
        'focused-external-reset-is-authoritative-without-onChange-echo',
        async () => {
            await mount();
            const current = editor();
            current.commands.focus('end');
            current.commands.insertContent(' editado');
            await until(
                () => current.isFocused,
                'Editor focus was not established.',
            );
            await settle();
            const count = emissions.length;
            flushSync(() => bridge!.external('<p>Reiniciado externamente</p>'));
            await until(
                () => current.getText() === 'Reiniciado externamente',
                'A focused editor ignored the external reset.',
            );
            assert(
                emissions.length === count,
                'Applying an external value emitted a feedback update.',
            );
        },
    );
    await runCase('controlled-echo-preserves-selection-and-undo', async () => {
        await mount('editor', '<p>Alpha</p>');
        const current = editor();
        current.commands.focus('end');
        current.view.dispatch(closeHistory(current.state.tr));
        current.commands.insertContent(' Beta');
        await settle();
        const position = current.state.selection.from;
        const count = emissions.length;
        flushSync(() => bridge!.echo());
        await settle();
        assert(
            current.getText() === 'Alpha Beta' &&
                current.state.selection.from === position,
            'An ordinary controlled echo replaced the document or selection.',
        );
        assert(
            emissions.length === count,
            'A parent echo emitted another update.',
        );
        current.commands.undo();
        await settle();
        assert(
            current.getText() === 'Alpha',
            'A controlled echo destroyed undo history.',
        );
    });
    await runCase(
        'plain-literal-unicode-and-newlines-convert-to-HTML-and-back',
        async () => {
            const original = 'Olá <tag> & "ação"\nsegunda linha\n\nfim 😀';
            await mount('composer', original);
            button('Texto', app).click();
            await until(
                () => Boolean(app.querySelector('.ProseMirror')),
                'Rich composer did not load.',
            );
            assert(
                editor().getText({ blockSeparator: '\n' }) === original,
                'Plain-to-HTML conversion interpreted literal markup or lost text/newlines.',
            );
            button('Rich text', app).click();
            await until(
                () => Boolean(app.querySelector('textarea')),
                'Plain composer did not return.',
            );
            assert(
                app.querySelector('textarea')!.value === original,
                'The plain/HTML round trip changed literal text.',
            );
            button('Texto', app).click();
            await until(
                () => Boolean(app.querySelector('.ProseMirror')),
                'Rich composer did not reload.',
            );
            button('Enviar', app).click();
            await settle();
            assert(
                submissions.length === 1 &&
                    submissions[0].body_format === 'html' &&
                    submissions[0].body.includes('&lt;tag&gt;'),
                'The submitted payload did not preserve escaped literal text as HTML.',
            );
        },
    );
    await runCase(
        'HTML-blocks-br-lists-and-entities-convert-to-plain',
        async () => {
            await mount(
                'composer',
                '<p>Um &amp; dois<br>continua</p><p>Próximo 😀</p><ul><li><p>item A</p></li><li><p>item B</p></li></ul>',
                'none',
                'html',
            );
            button('Rich text', app).click();
            await until(
                () => Boolean(app.querySelector('textarea')),
                'The plain composer is missing.',
            );
            assert(
                app.querySelector('textarea')!.value ===
                    'Um & dois\ncontinua\nPróximo 😀\nitem A\nitem B',
                'HTML-to-plain did not retain block and line boundaries or decode entities.',
            );
            button('Enviar', app).click();
            await settle();
            assert(
                submissions[0]?.body_format === 'plain' &&
                    !submissions[0].body.includes('<p>'),
                'The plain submission retained HTML tags.',
            );
        },
    );
    await runCase(
        'dialog-replacement-revokes-only-previous-preview',
        async () => {
            await mount();
            await openImage();
            const first = await choosePreview();
            choose(imageFile('second.png'));
            await settle();
            const second = dialog().querySelector<HTMLImageElement>('img')!.src;
            assert(
                first !== second && revoked.includes(first),
                'Replacing a dialog file retained its old object URL.',
            );
            assert(
                !revoked.includes(second),
                'The replacement preview was revoked while still visible.',
            );
        },
    );
    await runCase('dialog-cancel-releases-preview', async () => {
        await mount();
        await openImage();
        const url = await choosePreview();
        button('Close', dialog()).click();
        await settle();
        assert(
            revoked.includes(url),
            'Closing the image dialog retained its preview URL.',
        );
    });
    await runCase('dialog-switch-to-URL-releases-file-preview', async () => {
        await mount();
        await openImage();
        const url = await choosePreview();
        setInput(imageUrlInput(), imageUrl);
        await settle();
        assert(
            revoked.includes(url) &&
                !document.querySelector('[role="dialog"] img'),
            'Switching to a URL retained the local file preview.',
        );
    });
    await runCase('unmount-releases-dialog-preview', async () => {
        await mount();
        await openImage();
        const url = await choosePreview();
        flushSync(() => bridge!.unmount());
        await settle();
        assert(
            revoked.includes(url),
            'Unmounting the editor retained a dialog preview URL.',
        );
    });

    for (const input of ['paste', 'drop', 'input', 'dialog'] as const) {
        await runCase(
            `local-${input}-preview-never-emits-a-persistible-blob`,
            async () => {
                await mount();
                editor().commands.focus('end');

                if (input === 'paste') {
                    paste();
                } else if (input === 'drop') {
                    drop();
                } else if (input === 'input') {
                    choose();
                } else {
                    await openImage();
                    await choosePreview();
                    button('Inserir', dialog()).click();
                }

                await until(
                    () =>
                        Boolean(
                            app.querySelector('.ProseMirror img[src^="blob:"]'),
                        ),
                    'The local image preview was not preserved.',
                );
                await settle();
                assertNoBlobEmission();
            },
        );
    }

    await runCase('document-removal-releases-owned-blob', async () => {
        await mount();
        const url = await localDocumentImage();
        editor().commands.setContent('<p>Substituição do documento</p>');
        await settle();
        assert(
            revoked.includes(url),
            'A removed document image retained its object URL.',
        );
        assertNoBlobEmission();
    });
    await runCase(
        'undo-advances-text-history-without-resurrecting-revoked-image',
        async () => {
            await mount('editor', '<p>Texto preservado</p>');
            const current = editor();
            current.commands.focus('end');
            current.view.dispatch(closeHistory(current.state.tr));
            const url = await localDocumentImage();
            current.view.dispatch(closeHistory(current.state.tr));
            current.commands.setContent('<p>Texto substituído</p>');
            await settle();
            current.commands.undo();
            await settle();
            assert(
                current.getText().includes('Texto preservado'),
                'Undo was rejected instead of advancing text history.',
            );
            assert(
                !Array.from(
                    app.querySelectorAll<HTMLImageElement>('.ProseMirror img'),
                ).some((image) => image.src === url),
                'Undo resurrected a disposed local image.',
            );
            assert(
                revoked.includes(url),
                'Undo safety retained the removed URL instead of releasing it.',
            );
            assertNoBlobEmission();
        },
    );
    await runCase('unmount-releases-inline-preview', async () => {
        await mount();
        const url = await localDocumentImage();
        flushSync(() => bridge!.unmount());
        await settle();
        assert(
            revoked.includes(url),
            'Unmounting the editor retained an inline preview URL.',
        );
    });
    await runCase(
        'persistent-upload-URL-is-emitted-and-never-revoked',
        async () => {
            await mount('editor', '<p>Conteúdo</p>', 'immediate');
            paste();
            await until(
                () =>
                    Boolean(
                        app.querySelector(
                            `.ProseMirror img[src="${imageUrl}"]`,
                        ),
                    ),
                'The completed upload URL was not inserted.',
            );
            assert(
                emissions.some((html) => html.includes(imageUrl)),
                'A persistent upload URL was removed from the emitted HTML.',
            );
            editor().commands.setContent('<p>Sem imagem</p>');
            flushSync(() => bridge!.unmount());
            await settle();
            assert(
                !revoked.some(
                    (url) =>
                        url === imageUrl ||
                        url === new URL(imageUrl, location.href).href,
                ),
                'A callback-owned persistent URL was revoked by the editor.',
            );
        },
    );

    for (const input of ['paste', 'drop', 'input', 'dialog'] as const) {
        await runCase(
            `late-${input}-upload-after-reset-does-not-modify-new-document`,
            async () => {
                await mount('editor', '<p>Antes</p>', 'deferred');

                if (input === 'dialog') {
                    await openImage();
                    await choosePreview();
                    button('Inserir', dialog()).click();
                } else if (input === 'paste') {
                    paste();
                } else if (input === 'drop') {
                    drop();
                } else {
                    choose();
                }

                await until(
                    () => gates.some((item) => !item.settled),
                    'The controlled image callback did not start.',
                );
                flushSync(() => bridge!.external('<p>Depois do reset</p>'));
                await settle();
                resolveGate();
                await settle();
                assert(
                    editor().getText() === 'Depois do reset' &&
                        !app.querySelector('.ProseMirror img'),
                    'An obsolete upload replaced or inserted into the reset document.',
                );
            },
        );
    }

    await runCase(
        'late-dialog-upload-after-cancel-does-not-insert',
        async () => {
            await mount('editor', '<p>Antes</p>', 'deferred');
            await openImage();
            await choosePreview();
            button('Inserir', dialog()).click();
            await until(
                () => gates.some((item) => !item.settled),
                'The dialog upload did not start.',
            );
            button('Close', dialog()).click();
            await settle();
            resolveGate();
            await settle();
            assert(
                !app.querySelector('.ProseMirror img'),
                'A canceled dialog upload inserted an image later.',
            );
        },
    );
    await runCase(
        'late-dialog-upload-after-file-replacement-keeps-new-preview',
        async () => {
            await mount('editor', '<p>Antes</p>', 'deferred');
            await openImage();
            await choosePreview();
            button('Inserir', dialog()).click();
            await until(
                () => gates.some((item) => !item.settled),
                'The first dialog upload did not start.',
            );
            choose(imageFile('replacement.png'));
            await settle();
            const second = dialog().querySelector<HTMLImageElement>('img')!.src;
            resolveGate();
            await settle();
            assert(
                !app.querySelector('.ProseMirror img') &&
                    document.querySelector<HTMLImageElement>(
                        '[role="dialog"] img',
                    )?.src === second,
                'A replaced upload inserted its image or cleared the replacement preview.',
            );
        },
    );
    await runCase(
        'late-upload-after-unmount-has-no-emission-or-persistent-revoke',
        async () => {
            await mount('editor', '<p>Antes</p>', 'deferred');
            paste();
            await until(
                () => gates.some((item) => !item.settled),
                'The controlled upload did not start.',
            );
            flushSync(() => bridge!.unmount());
            await settle();
            const count = emissions.length;
            resolveGate();
            await settle();
            assert(
                emissions.length === count &&
                    !app.querySelector('.ProseMirror'),
                'An unmounted upload emitted content or recreated the editor.',
            );
            assert(
                !revoked.includes(imageUrl),
                'An unmounted upload revoked a URL that it did not create.',
            );
        },
    );

    for (const input of ['callback', 'URL'] as const) {
        await runCase(
            `foreign-blob-from-${input}-is-never-emitted-or-revoked`,
            async () => {
                const foreign = nativeCreate(imageFile('foreign.png'));

                try {
                    await mount(
                        'editor',
                        '<p>Antes</p>',
                        input === 'callback' ? 'deferred' : 'none',
                    );

                    if (input === 'callback') {
                        paste();
                        await until(
                            () => gates.some((item) => !item.settled),
                            'The foreign callback did not start.',
                        );
                        resolveGate(foreign);
                    } else {
                        await openImage();
                        setInput(imageUrlInput(), foreign);
                        await settle();
                        button('Inserir', dialog()).click();
                    }

                    await settle();
                    assertNoBlobEmission();
                    flushSync(() => bridge!.unmount());
                    await settle();
                    assert(
                        !revoked.includes(foreign),
                        'The editor revoked a foreign temporary URL.',
                    );
                } finally {
                    nativeRevoke(foreign);
                }
            },
        );
    }

    await runCase('external-reset-releases-inserted-preview', async () => {
        await mount();
        const url = await localDocumentImage();
        flushSync(() => bridge!.external('<p>Novo documento</p>'));
        await settle();
        assert(
            editor().getText() === 'Novo documento' && revoked.includes(url),
            'External reset retained the previous owned image or its URL.',
        );
    });
    await runCase(
        'text-edits-after-preview-still-emit-text-without-blob',
        async () => {
            await mount();
            await localDocumentImage();
            editor().commands.focus('end');
            editor().commands.insertContent('Texto posterior');
            await settle();
            assert(
                emissions.some((html) => html.includes('Texto posterior')),
                'Preview handling silenced later text changes.',
            );
            assertNoBlobEmission();
        },
    );
    await runCase(
        'foreign-blob-in-pasted-HTML-is-never-emitted-or-revoked',
        async () => {
            const foreign = nativeCreate(imageFile('foreign-paste.png'));

            try {
                await mount();
                const transfer = new DataTransfer();
                transfer.setData(
                    'text/html',
                    `<p>Texto colado</p><img src="${foreign}">`,
                );
                editorElement().dispatchEvent(
                    new ClipboardEvent('paste', {
                        bubbles: true,
                        cancelable: true,
                        clipboardData: transfer,
                    }),
                );
                await settle();
                assert(
                    emissions.some((html) => html.includes('Texto colado')),
                    'Pasted text was discarded with its temporary image.',
                );
                assertNoBlobEmission();
                flushSync(() => bridge!.unmount());
                await settle();
                assert(
                    !revoked.includes(foreign),
                    'The editor revoked a pasted URL it did not create.',
                );
            } finally {
                nativeRevoke(foreign);
            }
        },
    );
    await runCase(
        'late-upload-after-disable-does-not-insert-or-emit',
        async () => {
            await mount('editor', '<p>Antes</p>', 'deferred');
            paste();
            await until(
                () => gates.some((item) => !item.settled),
                'Deferred upload did not start.',
            );
            flushSync(() => bridge!.disable(true));
            await settle();
            const count = emissions.length;
            resolveGate();
            await settle();
            assert(
                !editor().isEditable &&
                    !app.querySelector('.ProseMirror img') &&
                    emissions.length === count,
                'An upload modified a disabled editor.',
            );
        },
    );

    for (const dark of [false, true]) {
        await runCase(
            `real-${dark ? 'dark' : 'light'}-OKLCH-tokens-style-editor`,
            async () => {
                document.documentElement.classList.toggle('dark', dark);
                await mount(
                    'editor',
                    `<blockquote><p>Citação</p></blockquote><p><code>inline</code></p><pre><code>bloco</code></pre><hr><p><a href="/owned-link">Link</a></p><img src="${imageUrl}">`,
                );
                const reference = document.createElement('span');
                document.body.append(reference);

                try {
                    const expected = (property: string, token: string) => {
                        reference.style.cssText = `${property}:var(${token})`;

                        return getComputedStyle(reference).getPropertyValue(
                            property,
                        );
                    };
                    const root = editorElement();
                    const quote = root.querySelector('blockquote')!;
                    assert(
                        getComputedStyle(quote).borderLeftColor ===
                            expected('border-left-color', '--border'),
                        'Blockquote border did not use the real border token.',
                    );
                    assert(
                        getComputedStyle(quote).color ===
                            expected('color', '--muted-foreground'),
                        'Blockquote color did not use the real muted foreground.',
                    );
                    assert(
                        getComputedStyle(root.querySelector('code')!)
                            .backgroundColor ===
                            expected('background-color', '--muted'),
                        'Inline code background did not use the real muted token.',
                    );
                    assert(
                        getComputedStyle(root.querySelector('pre')!)
                            .backgroundColor ===
                            expected('background-color', '--muted'),
                        'Code block background did not use the real muted token.',
                    );
                    assert(
                        getComputedStyle(root.querySelector('hr')!)
                            .borderTopColor ===
                            expected('border-top-color', '--border'),
                        'Horizontal rule did not use the real border token.',
                    );
                    assert(
                        getComputedStyle(root.querySelector('a')!).color ===
                            expected('color', '--primary'),
                        'Link color did not use the real primary token.',
                    );
                    let position = -1;
                    editor().state.doc.descendants((node, pos) => {
                        if (node.type.name === 'image') {
                            position = pos;
                        }
                    });
                    assert(
                        position >= 0,
                        'The image selection fixture is missing.',
                    );
                    editor().commands.setNodeSelection(position);
                    await settle();
                    assert(
                        getComputedStyle(root.querySelector('img')!)
                            .outlineColor ===
                            expected('outline-color', '--primary'),
                        'Selected image did not use the real primary token.',
                    );
                } finally {
                    reference.remove();
                }
            },
        );
    }

    await runCase(
        'all-owned-URLs-released-without-fixture-cleanup',
        async () => {
            assert(
                forcedCleanup === 0 && live.size === 0,
                'The product left owned blob URLs for fixture cleanup.',
            );
        },
    );
    assert(
        forbiddenNetwork === 0,
        'The fixture attempted a real API/upload request.',
    );
    result.textContent = JSON.stringify(
        {
            fixture: 'UI007',
            checks,
            total: checks.length,
            passed: checks.filter((item) => item.status === 'passed').length,
            failed: checks.filter((item) => item.status === 'failed').length,
            image: imageUrl,
            uploads,
            created: created.length,
            productRevokes: revoked.length,
            forcedCleanup,
            forbiddenNetwork,
            emissions: allEmissions.length,
            blobEmissions: allEmissions.filter((html) => /blob:/i.test(html))
                .length,
            context: {
                protocol: location.protocol,
                host: location.hostname,
                randomUUIDAvailable:
                    typeof globalThis.crypto?.randomUUID === 'function',
            },
        },
        null,
        2,
    );
    runButton.disabled = false;
}

runButton.addEventListener(
    'click',
    () =>
        void run().catch((error: unknown) => {
            result.textContent = JSON.stringify(
                {
                    setupError:
                        error instanceof Error ? error.message : String(error),
                    checks,
                    forbiddenNetwork,
                },
                null,
                2,
            );
            runButton.disabled = false;
        }),
);
result.textContent =
    'Pronto. Componentes reais com React Compiler + StrictMode; nenhum endpoint de upload.';

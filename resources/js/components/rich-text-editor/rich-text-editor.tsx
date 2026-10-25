import { Extension } from '@tiptap/core';
import type { Editor } from '@tiptap/core';
import ImageExt from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { Plugin } from '@tiptap/pm/state';
import type { SelectionBookmark, Transaction } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Code2,
    Heading1,
    Heading2,
    Heading3,
    ImageIcon,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo,
    Strikethrough,
    Underline as UnderlineIcon,
    Undo,
    Upload,
    Trash2,
    Save,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface RichTextEditorProps extends Pick<
    React.AriaAttributes,
    'aria-label' | 'aria-labelledby'
> {
    id?: string;
    value: string | null;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    toolbarPreset?: 'full' | 'timeline';
    allowImages?: boolean;
    /** Altura mínima da área de edição em px. Padrão: 200 */
    minHeight?: number;
    /**
     * Callback para upload de imagem (drag-and-drop, paste e seleção de arquivo).
     * Deve retornar a URL pública da imagem.
     * Se omitido, imagens têm preview local; URLs temporárias não entram no onChange.
     */
    onImageUpload?: (file: File) => Promise<string>;
}

const EDITOR_CSS_ID = 'rte-global-styles';

const EDITOR_STYLES = `
.ProseMirror { outline: none; word-wrap: break-word; white-space: pre-wrap; }
.ProseMirror:focus-visible { outline: 2px solid var(--ring, #6b7280); outline-offset: -2px; }
.ProseMirror p.is-editor-empty:first-child::before {
    color: var(--muted-foreground, #9ca3af);
    content: attr(data-placeholder);
    float: left; height: 0; pointer-events: none;
}
.ProseMirror h1 { font-size: 1.75rem; font-weight: 700; margin: 1rem 0 0.5rem; line-height: 1.2; }
.ProseMirror h2 { font-size: 1.4rem; font-weight: 600; margin: 0.875rem 0 0.5rem; line-height: 1.3; }
.ProseMirror h3 { font-size: 1.15rem; font-weight: 600; margin: 0.75rem 0 0.4rem; line-height: 1.4; }
.ProseMirror p { margin: 0.35rem 0; }
.ProseMirror ul, .ProseMirror ol { padding-left: 1.5rem; margin: 0.4rem 0; }
.ProseMirror ul { list-style-type: disc; }
.ProseMirror ol { list-style-type: decimal; }
.ProseMirror li > p { margin: 0; }
.ProseMirror blockquote {
    border-left: 3px solid var(--border, #d1d5db);
    padding-left: 1rem; margin: 0.75rem 0;
    color: var(--muted-foreground, #6b7280);
    font-style: italic;
}
.ProseMirror code {
    background: var(--muted, #f3f4f6);
    border-radius: 0.25rem; padding: 0.1rem 0.35rem;
    font-size: 0.85em; font-family: ui-monospace, monospace;
}
.ProseMirror pre {
    background: var(--muted, #f3f4f6);
    border-radius: 0.5rem; padding: 0.75rem 1rem;
    overflow-x: auto; margin: 0.75rem 0;
}
.ProseMirror pre code { background: none; padding: 0; font-size: 0.85em; }
.ProseMirror hr { border: none; border-top: 1px solid var(--border, #d1d5db); margin: 1rem 0; }
.ProseMirror img { max-width: 100%; height: auto; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0.5rem 0; }
.ProseMirror img.ProseMirror-selectednode { outline: 2px solid var(--primary, #2563eb); outline-offset: 2px; }
.ProseMirror a { color: var(--primary, #2563eb); text-decoration: underline; cursor: pointer; }
.ProseMirror strong { font-weight: 700; }
.ProseMirror em { font-style: italic; }
.ProseMirror s { text-decoration: line-through; }
.ProseMirror u { text-decoration: underline; }
`;

function injectEditorStyles(): void {
    if (
        typeof document === 'undefined' ||
        document.getElementById(EDITOR_CSS_ID)
    ) {
        return;
    }

    const style = document.createElement('style');
    style.id = EDITOR_CSS_ID;
    style.textContent = EDITOR_STYLES;
    document.head.appendChild(style);
}

function ToolbarBtn({
    onClick,
    active,
    disabled,
    title,
    children,
}: {
    onClick: () => void;
    active?: boolean;
    disabled?: boolean;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            title={title}
            aria-label={title}
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex h-7 w-7 items-center justify-center rounded text-sm transition-colors',
                'hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40',
                active
                    ? 'bg-muted text-foreground'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function ToolbarDivider() {
    return <div className="mx-0.5 h-4 w-px shrink-0 bg-border" />;
}

function isTemporaryUrl(src: unknown): src is string {
    return typeof src === 'string' && /^blob:/i.test(src.trim());
}

function serializedContent(editor: Editor): string {
    const template = document.createElement('template');
    template.innerHTML = editor.getHTML();

    for (const image of template.content.querySelectorAll('img')) {
        if (isTemporaryUrl(image.getAttribute('src'))) {
            image.remove();
        }
    }

    const html = template.innerHTML;

    return html === '<p></p>' ? '' : html;
}

interface PendingImage {
    view: EditorView;
    lifetime: number;
    document: number;
    dialog: number | null;
    position: number | null;
    bookmark: SelectionBookmark | null;
}

function createImageOwner() {
    const ownedUrls = new Set<string>();
    const pendingImages = new Set<PendingImage>();
    let documentUrls = new Set<string>();
    let previewUrl: string | null = null;

    function revoke(url: string): void {
        if (ownedUrls.delete(url)) {
            URL.revokeObjectURL(url);
        }
    }

    return {
        owns: (url: string): boolean => ownedUrls.has(url),
        retain: (url: string): void => {
            ownedUrls.add(url);
        },
        preview: (): string | null => previewUrl,
        setPreview: (url: string): void => {
            previewUrl = url;
        },
        releasePreview(): void {
            const previous = previewUrl;
            previewUrl = null;

            if (previous && !documentUrls.has(previous)) {
                revoke(previous);
            }
        },
        releaseUnused(url: string): void {
            if (!documentUrls.has(url) && previewUrl !== url) {
                revoke(url);
            }
        },
        track: (operation: PendingImage): void => {
            pendingImages.add(operation);
        },
        untrack: (operation: PendingImage): void => {
            pendingImages.delete(operation);
        },
        hasPending: (
            predicate: (operation: PendingImage) => boolean,
        ): boolean => Array.from(pendingImages).some(predicate),
        reconcile(editor: Editor, transactions: readonly Transaction[]): void {
            for (const pending of pendingImages) {
                for (const transaction of transactions) {
                    if (pending.position !== null) {
                        pending.position = transaction.mapping.map(
                            pending.position,
                        );
                    }

                    if (pending.bookmark) {
                        pending.bookmark = pending.bookmark.map(
                            transaction.mapping,
                        );
                    }
                }
            }

            const used = new Set<string>();
            editor.state.doc.descendants((node) => {
                if (
                    node.type.name === 'image' &&
                    isTemporaryUrl(node.attrs.src)
                ) {
                    used.add(node.attrs.src);
                }
            });
            documentUrls = used;

            for (const url of ownedUrls) {
                if (!used.has(url) && previewUrl !== url) {
                    revoke(url);
                }
            }
        },
        dispose(): void {
            for (const url of ownedUrls) {
                URL.revokeObjectURL(url);
            }

            ownedUrls.clear();
            documentUrls.clear();
            previewUrl = null;
            pendingImages.clear();
        },
    };
}

function createTemporaryImageGuard(owner: ReturnType<typeof createImageOwner>) {
    return Extension.create({
        name: 'temporaryImageLifecycle',
        addProseMirrorPlugins() {
            return [
                new Plugin({
                    appendTransaction(_transactions, _oldState, state) {
                        const removals: Array<{ from: number; to: number }> =
                            [];
                        state.doc.descendants((node, position) => {
                            if (
                                node.type.name === 'image' &&
                                isTemporaryUrl(node.attrs.src) &&
                                !owner.owns(node.attrs.src)
                            ) {
                                removals.push({
                                    from: position,
                                    to: position + node.nodeSize,
                                });
                            }
                        });

                        if (!removals.length) {
                            return null;
                        }

                        const transaction = state.tr;

                        for (const range of removals.reverse()) {
                            transaction.delete(range.from, range.to);
                        }

                        return transaction.setMeta('addToHistory', false);
                    },
                }),
            ];
        },
    });
}

export function RichTextEditor({
    id,
    'aria-label': ariaLabel,
    'aria-labelledby': ariaLabelledBy,
    value,
    onChange,
    placeholder = 'Escreva algo...',
    disabled = false,
    className,
    toolbarPreset = 'full',
    allowImages = true,
    minHeight = 200,
    onImageUpload,
}: RichTextEditorProps) {
    const instanceId = useId();
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [linkUrl, setLinkUrl] = useState('');

    const [imageDialogOpen, setImageDialogOpen] = useState(false);
    const [imageUrl, setImageUrl] = useState('');
    const [imageUploading, setImageUploading] = useState(false);
    const [dialogDragging, setDialogDragging] = useState(false);
    const [dialogPreview, setDialogPreview] = useState<string | null>(null);
    const [hasDialogFile, setHasDialogFile] = useState(false);
    const dialogFileRef = useRef<File | null>(null);

    const [editorDragging, setEditorDragging] = useState(false);
    const dragCounter = useRef(0);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const isTimelinePreset = toolbarPreset === 'timeline';
    const headingLevels = (isTimelinePreset ? [3] : [1, 2, 3]) as [1, 2, 3];
    const lastKnownValueRef = useRef(value || '');
    const [imageOwner] = useState(createImageOwner);
    const lifecycle = useRef({
        mounted: false,
        lifetime: 0,
        document: 0,
        dialog: 0,
    });
    const currentEditor = useRef<Editor | null>(null);
    const readOnly = useRef(disabled);
    const uploadRef = useRef(onImageUpload);

    const releasePreview = useCallback(() => {
        imageOwner.releasePreview();
    }, [imageOwner]);

    const clearImageDialog = useCallback(() => {
        ++lifecycle.current.dialog;
        releasePreview();
        dialogFileRef.current = null;
        setDialogPreview(null);
        setHasDialogFile(false);
        setImageUrl('');
        setImageUploading(false);
        setImageDialogOpen(false);
    }, [releasePreview]);

    const chooseDialogFile = useCallback(
        (file: File) => {
            ++lifecycle.current.dialog;
            releasePreview();
            const url = URL.createObjectURL(file);
            imageOwner.retain(url);
            imageOwner.setPreview(url);
            dialogFileRef.current = file;
            setDialogPreview(url);
            setHasDialogFile(true);
            setImageUrl('');
            setImageUploading(false);
        },
        [releasePreview, imageOwner],
    );

    const imageIsCurrent = useCallback((operation: PendingImage): boolean => {
        const active = lifecycle.current;

        return (
            active.mounted &&
            active.lifetime === operation.lifetime &&
            active.document === operation.document &&
            (operation.dialog === null || operation.dialog === active.dialog) &&
            !readOnly.current &&
            !operation.view.isDestroyed &&
            currentEditor.current?.view === operation.view &&
            currentEditor.current.isEditable
        );
    }, []);

    const insertFile = useCallback(
        async (
            file: File,
            view: EditorView,
            position: number | null = null,
            fromDialog = false,
        ): Promise<boolean> => {
            const operation: PendingImage = {
                view,
                lifetime: lifecycle.current.lifetime,
                document: lifecycle.current.document,
                dialog: fromDialog ? lifecycle.current.dialog : null,
                position,
                bookmark:
                    position === null
                        ? view.state.selection.getBookmark()
                        : null,
            };

            if (!imageIsCurrent(operation)) {
                return false;
            }

            imageOwner.track(operation);
            setImageUploading(true);
            let createdUrl: string | null = null;

            try {
                const upload = uploadRef.current;
                const preview = imageOwner.preview();
                let src: string;

                if (upload) {
                    src = await upload(file);
                } else if (fromDialog && preview) {
                    src = preview;
                } else {
                    createdUrl = URL.createObjectURL(file);
                    imageOwner.retain(createdUrl);
                    src = createdUrl;
                }

                if (!imageIsCurrent(operation)) {
                    return false;
                }

                src = src.trim();

                if (!src || (isTemporaryUrl(src) && !imageOwner.owns(src))) {
                    return false;
                }

                const node = view.state.schema.nodes.image?.create({ src });

                if (!node) {
                    return false;
                }

                let transaction = view.state.tr;

                if (operation.position !== null) {
                    transaction = transaction.insert(
                        Math.min(
                            Math.max(0, operation.position),
                            view.state.doc.content.size,
                        ),
                        node,
                    );
                } else if (operation.bookmark) {
                    transaction = transaction
                        .setSelection(
                            operation.bookmark.resolve(view.state.doc),
                        )
                        .replaceSelectionWith(node);
                }

                view.dispatch(transaction);
                view.focus();

                return true;
            } catch {
                // The upload callback remains responsible for its transport feedback.
                return false;
            } finally {
                imageOwner.untrack(operation);

                if (createdUrl) {
                    imageOwner.releaseUnused(createdUrl);
                }

                if (imageIsCurrent(operation)) {
                    setImageUploading(imageOwner.hasPending(imageIsCurrent));
                }
            }
        },
        [imageIsCurrent, imageOwner],
    );

    const temporaryImageGuard = useMemo(
        () => createTemporaryImageGuard(imageOwner),
        [imageOwner],
    );

    // Ref para o callback de upload: evita recriar o editor quando a prop muda
    useEffect(() => {
        uploadRef.current = onImageUpload;
    }, [onImageUpload]);

    useEffect(() => {
        injectEditorStyles();
    }, []);

    useEffect(() => {
        const active = lifecycle.current;
        active.mounted = true;
        ++active.lifetime;

        return () => {
            active.mounted = false;
            ++active.lifetime;
            ++active.document;
            ++active.dialog;

            imageOwner.dispose();
        };
    }, [imageOwner]);

    const editor = useEditor({
        extensions: [
            temporaryImageGuard,
            StarterKit.configure({
                heading: { levels: headingLevels },
                link: {
                    openOnClick: false,
                    HTMLAttributes: {
                        target: '_blank',
                        rel: 'noopener noreferrer',
                    },
                },
            }),
            ...(allowImages
                ? [ImageExt.configure({ inline: false, allowBase64: true })]
                : []),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder }),
        ],
        content: value || '',
        parseOptions: { preserveWhitespace: 'full' },
        editable: !disabled,
        onTransaction: ({
            editor: activeEditor,
            transaction,
            appendedTransactions,
        }) => {
            imageOwner.reconcile(activeEditor, [
                transaction,
                ...appendedTransactions,
            ]);
        },
        onUpdate: ({ editor: e }) => {
            if (e.isDestroyed) {
                return;
            }

            const normalizedHtml = serializedContent(e);

            lastKnownValueRef.current = normalizedHtml;
            onChange(normalizedHtml);
        },
        editorProps: {
            attributes: {
                id: id ?? `${instanceId}-content`,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-readonly': String(disabled),
                ...(ariaLabelledBy
                    ? { 'aria-labelledby': ariaLabelledBy }
                    : { 'aria-label': ariaLabel ?? 'Editor de texto' }),
                class: 'px-3 py-2 text-sm leading-relaxed focus:outline-none',
            },
            // ── Drag-and-drop de arquivo no corpo do editor ──────────────
            handleDrop(view, event, _slice, moved) {
                if (
                    moved ||
                    !allowImages ||
                    !currentEditor.current?.isEditable ||
                    readOnly.current
                ) {
                    return false;
                }

                const file = Array.from(event.dataTransfer?.files ?? []).find(
                    (f) => f.type.startsWith('image/'),
                );

                if (!file) {
                    return false;
                }

                event.preventDefault();

                const coords = { left: event.clientX, top: event.clientY };
                const resolved = view.posAtCoords(coords);

                void insertFile(
                    file,
                    view,
                    resolved?.pos ?? view.state.doc.content.size,
                );

                return true;
            },
            // ── Paste de imagem (Ctrl+V / colar print de tela) ───────────
            handlePaste(view, event) {
                if (
                    !allowImages ||
                    !currentEditor.current?.isEditable ||
                    readOnly.current
                ) {
                    return false;
                }

                const items = Array.from(event.clipboardData?.items ?? []);
                const imageItem = items.find((i) =>
                    i.type.startsWith('image/'),
                );

                if (!imageItem) {
                    return false;
                }

                const file = imageItem.getAsFile();

                if (!file) {
                    return false;
                }

                event.preventDefault();

                void insertFile(file, view);

                return true;
            },
        },
    });

    const toolbarState = useEditorState({
        editor,
        selector: ({ editor: active }) =>
            active
                ? {
                      bold: active.isActive('bold'),
                      italic: active.isActive('italic'),
                      underline: active.isActive('underline'),
                      strike: active.isActive('strike'),
                      code: active.isActive('code'),
                      heading1: active.isActive('heading', { level: 1 }),
                      heading2: active.isActive('heading', { level: 2 }),
                      heading3: active.isActive('heading', { level: 3 }),
                      bulletList: active.isActive('bulletList'),
                      orderedList: active.isActive('orderedList'),
                      blockquote: active.isActive('blockquote'),
                      codeBlock: active.isActive('codeBlock'),
                      left: active.isActive({ textAlign: 'left' }),
                      center: active.isActive({ textAlign: 'center' }),
                      right: active.isActive({ textAlign: 'right' }),
                      justify: active.isActive({ textAlign: 'justify' }),
                      canUndo: active.can().undo(),
                      canRedo: active.can().redo(),
                  }
                : null,
    });

    useEffect(() => {
        currentEditor.current = editor;

        return () => {
            if (currentEditor.current === editor) {
                currentEditor.current = null;
            }
        };
    }, [editor]);

    // Sync value from outside (ex: reset de formulário)
    useEffect(() => {
        if (!editor || editor.isDestroyed) {
            return;
        }

        const next = value || '';

        if (lastKnownValueRef.current !== next) {
            ++lifecycle.current.document;
            ++lifecycle.current.dialog;
            const generation = lifecycle.current.document;
            const dialogGeneration = lifecycle.current.dialog;
            releasePreview();
            editor.commands.setContent(next, {
                emitUpdate: false,
                parseOptions: { preserveWhitespace: 'full' },
            });
            lastKnownValueRef.current = next;
            queueMicrotask(() => {
                if (
                    lifecycle.current.mounted &&
                    lifecycle.current.document === generation &&
                    lifecycle.current.dialog === dialogGeneration
                ) {
                    clearImageDialog();
                }
            });
        }
    }, [value, editor, releasePreview, clearImageDialog]);

    useEffect(() => {
        readOnly.current = disabled;

        if (!editor || editor.isDestroyed) {
            return;
        }

        editor.setEditable(!disabled);

        if (disabled) {
            ++lifecycle.current.document;
            ++lifecycle.current.dialog;
            const generation = lifecycle.current.document;
            queueMicrotask(() => {
                if (
                    lifecycle.current.mounted &&
                    lifecycle.current.document === generation
                ) {
                    clearImageDialog();
                    setLinkDialogOpen(false);
                    setLinkUrl('');
                }
            });
        }
    }, [disabled, editor, clearImageDialog]);

    // ── Link ──────────────────────────────────────────────────────────────────

    const openLinkDialog = useCallback(() => {
        if (!editor || !editor.isEditable || readOnly.current) {
            return;
        }

        setLinkUrl(editor.getAttributes('link').href ?? '');
        setLinkDialogOpen(true);
    }, [editor]);

    const saveLinkUrl = useCallback(() => {
        if (!editor || !editor.isEditable || readOnly.current) {
            return;
        }

        if (linkUrl.trim()) {
            editor.chain().focus().setLink({ href: linkUrl.trim() }).run();
        } else {
            editor.chain().focus().unsetLink().run();
        }

        setLinkDialogOpen(false);
        setLinkUrl('');
    }, [editor, linkUrl]);

    // ── Imagem: dialog ───────────────────────────────────────────────────────

    const openImageDialog = useCallback(() => {
        if (readOnly.current || !currentEditor.current?.isEditable) {
            return;
        }

        clearImageDialog();
        setImageDialogOpen(true);
    }, [clearImageDialog]);

    const insertImageByUrl = useCallback(() => {
        if (
            !editor ||
            !editor.isEditable ||
            !imageUrl.trim() ||
            readOnly.current
        ) {
            return;
        }

        const src = imageUrl.trim();

        if (!isTemporaryUrl(src)) {
            editor.chain().focus().setImage({ src }).run();
        }

        clearImageDialog();
    }, [editor, imageUrl, clearImageDialog]);

    const insertDialogFile = useCallback(async () => {
        if (!editor || !dialogFileRef.current) {
            return;
        }

        const generation = lifecycle.current.dialog;
        const inserted = await insertFile(
            dialogFileRef.current,
            editor.view,
            null,
            true,
        );

        if (
            inserted &&
            lifecycle.current.mounted &&
            lifecycle.current.dialog === generation
        ) {
            clearImageDialog();
        }
    }, [editor, insertFile, clearImageDialog]);

    const handleFileInput = useCallback(
        async (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];

            if (!file || !editor || !editor.isEditable || readOnly.current) {
                return;
            }

            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }

            // Se o dialog estiver aberto, mostra preview; senão insere direto
            if (imageDialogOpen) {
                chooseDialogFile(file);
            } else {
                await insertFile(file, editor.view);
            }
        },
        [editor, imageDialogOpen, chooseDialogFile, insertFile],
    );

    // ── Dialog: drag-and-drop na área do dialog ──────────────────────────────

    const onDialogDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setDialogDragging(true);
    };

    const onDialogDragLeave = (e: React.DragEvent) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node)) {
            setDialogDragging(false);
        }
    };

    const onDialogDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            setDialogDragging(false);
            const file = Array.from(e.dataTransfer.files).find((f) =>
                f.type.startsWith('image/'),
            );

            if (
                !file ||
                readOnly.current ||
                !currentEditor.current?.isEditable
            ) {
                return;
            }

            chooseDialogFile(file);
        },
        [chooseDialogFile],
    );

    // ── Editor wrapper: drag visual feedback ────────────────────────────────

    const onEditorDragEnter = (e: React.DragEvent) => {
        if (!e.dataTransfer.types.includes('Files')) {
            return;
        }

        dragCounter.current += 1;
        setEditorDragging(true);
    };

    const onEditorDragLeave = () => {
        dragCounter.current -= 1;

        if (dragCounter.current <= 0) {
            dragCounter.current = 0;
            setEditorDragging(false);
        }
    };

    const onEditorDrop = () => {
        dragCounter.current = 0;
        setEditorDragging(false);
    };

    if (!editor) {
        return null;
    }

    return (
        <>
            {/* ── Container ───────────────────────────────────────────────── */}
            <div
                className={cn(
                    'relative overflow-hidden rounded-md border bg-background text-sm text-foreground transition-colors',
                    allowImages &&
                        editorDragging &&
                        'border-primary ring-2 ring-primary/20',
                    disabled && 'cursor-not-allowed opacity-60',
                    className,
                )}
                onDragEnter={onEditorDragEnter}
                onDragLeave={onEditorDragLeave}
                onDragOver={(e) => e.preventDefault()}
                onDrop={onEditorDrop}
            >
                {/* ── Overlay de drag ─────────────────────────────────────── */}
                {allowImages && editorDragging && (
                    <div className="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-primary bg-primary/5">
                        <ImageIcon className="size-10 text-primary/70" />
                        <span className="text-sm font-medium text-primary">
                            Solte para inserir a imagem
                        </span>
                    </div>
                )}

                {/* ── Toolbar ─────────────────────────────────────────────── */}
                <div
                    role="toolbar"
                    aria-label="Formatação de texto"
                    className="flex flex-wrap items-center gap-0.5 border-b bg-muted/30 px-2 py-1.5"
                >
                    <ToolbarBtn
                        title="Desfazer (Ctrl+Z)"
                        onClick={() => editor.chain().focus().undo().run()}
                        disabled={disabled || !toolbarState?.canUndo}
                    >
                        <Undo className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Refazer (Ctrl+Y)"
                        onClick={() => editor.chain().focus().redo().run()}
                        disabled={disabled || !toolbarState?.canRedo}
                    >
                        <Redo className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn
                        title="Negrito (Ctrl+B)"
                        disabled={disabled}
                        active={toolbarState?.bold ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    >
                        <Bold className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Itálico (Ctrl+I)"
                        disabled={disabled}
                        active={toolbarState?.italic ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                    >
                        <Italic className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Sublinhado (Ctrl+U)"
                        disabled={disabled}
                        active={toolbarState?.underline ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleUnderline().run()
                        }
                    >
                        <UnderlineIcon className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Tachado"
                        disabled={disabled}
                        active={toolbarState?.strike ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleStrike().run()
                        }
                    >
                        <Strikethrough className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Código inline"
                        disabled={disabled}
                        active={toolbarState?.code ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleCode().run()
                        }
                    >
                        <Code className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn
                        title="Título 1"
                        disabled={disabled}
                        active={toolbarState?.heading1 ?? false}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 1 })
                                .run()
                        }
                    >
                        <Heading1 className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Título 2"
                        disabled={disabled}
                        active={toolbarState?.heading2 ?? false}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run()
                        }
                    >
                        <Heading2 className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Título 3"
                        disabled={disabled}
                        active={toolbarState?.heading3 ?? false}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run()
                        }
                    >
                        <Heading3 className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn
                        title="Lista com marcadores"
                        disabled={disabled}
                        active={toolbarState?.bulletList ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleBulletList().run()
                        }
                    >
                        <List className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Lista numerada"
                        disabled={disabled}
                        active={toolbarState?.orderedList ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleOrderedList().run()
                        }
                    >
                        <ListOrdered className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn
                        title="Citação"
                        disabled={disabled}
                        active={toolbarState?.blockquote ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleBlockquote().run()
                        }
                    >
                        <Quote className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Bloco de código"
                        disabled={disabled}
                        active={toolbarState?.codeBlock ?? false}
                        onClick={() =>
                            editor.chain().focus().toggleCodeBlock().run()
                        }
                    >
                        <Code2 className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Separador horizontal"
                        disabled={disabled}
                        onClick={() =>
                            editor.chain().focus().setHorizontalRule().run()
                        }
                    >
                        <Minus className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn
                        title="Alinhar à esquerda"
                        disabled={disabled}
                        active={toolbarState?.left ?? false}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('left').run()
                        }
                    >
                        <AlignLeft className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Centralizar"
                        disabled={disabled}
                        active={toolbarState?.center ?? false}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('center').run()
                        }
                    >
                        <AlignCenter className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Alinhar à direita"
                        disabled={disabled}
                        active={toolbarState?.right ?? false}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('right').run()
                        }
                    >
                        <AlignRight className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Justificar"
                        disabled={disabled}
                        active={toolbarState?.justify ?? false}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('justify').run()
                        }
                    >
                        <AlignJustify className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn
                        title="Inserir / editar link"
                        disabled={disabled}
                        onClick={openLinkDialog}
                    >
                        <LinkIcon className="size-3.5" />
                    </ToolbarBtn>

                    {allowImages && (
                        <ToolbarBtn
                            title="Inserir imagem"
                            disabled={disabled}
                            onClick={openImageDialog}
                        >
                            <ImageIcon className="size-3.5" />
                        </ToolbarBtn>
                    )}

                    {allowImages && (
                        <input
                            ref={fileInputRef}
                            type="file"
                            disabled={disabled}
                            accept="image/*"
                            className="hidden"
                            onChange={handleFileInput}
                        />
                    )}
                </div>

                {/* ── Editor content ──────────────────────────────────────── */}
                <EditorContent
                    editor={editor}
                    style={{ minHeight }}
                    className="[&_.ProseMirror]:min-h-[inherit]"
                />
            </div>

            {/* ── Link dialog ─────────────────────────────────────────────── */}
            <Dialog open={linkDialogOpen} onOpenChange={setLinkDialogOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Inserir link</DialogTitle>
                        <DialogDescription>
                            Informe o endereço de destino ou remova o link da
                            seleção.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor={`${instanceId}-link-url`}>URL</Label>
                        <Input
                            id={`${instanceId}-link-url`}
                            disabled={disabled}
                            placeholder="https://exemplo.com"
                            value={linkUrl}
                            onChange={(e) => setLinkUrl(e.target.value)}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && saveLinkUrl()
                            }
                            autoFocus
                        />
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={disabled}
                            onClick={() => {
                                if (!editor.isEditable || readOnly.current) {
                                    return;
                                }

                                editor.chain().focus().unsetLink().run();
                                setLinkDialogOpen(false);
                            }}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            Remover link
                        </Button>
                        <Button
                            type="button"
                            onClick={saveLinkUrl}
                            disabled={disabled}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Salvar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ── Image dialog ────────────────────────────────────────────── */}
            <Dialog
                open={imageDialogOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setImageDialogOpen(true);
                    } else {
                        clearImageDialog();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Inserir imagem</DialogTitle>
                        <DialogDescription>
                            Selecione um arquivo de imagem ou informe sua URL.
                            Arquivos sem upload ficam apenas como prévia local.
                        </DialogDescription>
                    </DialogHeader>

                    {/* Drop zone */}
                    <button
                        type="button"
                        aria-label="Selecionar arquivo de imagem"
                        disabled={disabled}
                        className={cn(
                            'relative flex min-h-36 w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40',
                            dialogDragging
                                ? 'border-primary bg-primary/5'
                                : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                        )}
                        onDragOver={onDialogDragOver}
                        onDragLeave={onDialogDragLeave}
                        onDrop={onDialogDrop}
                        onClick={() => {
                            if (!readOnly.current && editor.isEditable) {
                                fileInputRef.current?.click();
                            }
                        }}
                    >
                        {dialogPreview ? (
                            <img
                                src={dialogPreview}
                                alt="preview"
                                className="max-h-52 max-w-full rounded object-contain"
                            />
                        ) : (
                            <>
                                <Upload className="size-8 text-muted-foreground/50" />
                                <span className="text-center">
                                    <span className="block text-sm font-medium">
                                        Arraste uma imagem aqui
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        ou clique para selecionar arquivo
                                    </span>
                                </span>
                            </>
                        )}
                    </button>

                    {/* URL input */}
                    <div className="relative flex items-center gap-3">
                        <div className="h-px flex-1 bg-border" />
                        <span className="text-xs text-muted-foreground">
                            ou cole uma URL
                        </span>
                        <div className="h-px flex-1 bg-border" />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor={`${instanceId}-image-url`}>
                            URL da imagem
                        </Label>
                        <Input
                            id={`${instanceId}-image-url`}
                            disabled={disabled}
                            placeholder="https://exemplo.com/imagem.png"
                            value={imageUrl}
                            onChange={(e) => {
                                setImageUrl(e.target.value);

                                if (e.target.value) {
                                    ++lifecycle.current.dialog;
                                    releasePreview();
                                    setDialogPreview(null);
                                    dialogFileRef.current = null;
                                    setHasDialogFile(false);
                                    setImageUploading(false);
                                }
                            }}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && insertImageByUrl()
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={() => {
                                if (hasDialogFile) {
                                    void insertDialogFile();
                                } else {
                                    insertImageByUrl();
                                }
                            }}
                            disabled={
                                disabled ||
                                imageUploading ||
                                (!imageUrl.trim() && !hasDialogFile)
                            }
                        >
                            {imageUploading ? 'Enviando...' : 'Inserir'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

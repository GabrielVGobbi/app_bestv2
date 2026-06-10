import ImageExt from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, useEditor } from '@tiptap/react';
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
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface RichTextEditorProps {
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
     * Se omitido, imagens são inseridas como blob: URL temporária (só para preview).
     */
    onImageUpload?: (file: File) => Promise<string>;
}

const EDITOR_CSS_ID = 'rte-global-styles';

const EDITOR_STYLES = `
.ProseMirror { outline: none; word-wrap: break-word; white-space: pre-wrap; }
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
    border-left: 3px solid hsl(var(--border, 220 13% 85%));
    padding-left: 1rem; margin: 0.75rem 0;
    color: hsl(var(--muted-foreground, 220 9% 46%));
    font-style: italic;
}
.ProseMirror code {
    background: hsl(var(--muted, 220 14% 96%));
    border-radius: 0.25rem; padding: 0.1rem 0.35rem;
    font-size: 0.85em; font-family: ui-monospace, monospace;
}
.ProseMirror pre {
    background: hsl(var(--muted, 220 14% 96%));
    border-radius: 0.5rem; padding: 0.75rem 1rem;
    overflow-x: auto; margin: 0.75rem 0;
}
.ProseMirror pre code { background: none; padding: 0; font-size: 0.85em; }
.ProseMirror hr { border: none; border-top: 1px solid hsl(var(--border, 220 13% 85%)); margin: 1rem 0; }
.ProseMirror img { max-width: 100%; height: auto; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0.5rem 0; }
.ProseMirror img.ProseMirror-selectednode { outline: 2px solid hsl(var(--primary, 221 83% 53%)); outline-offset: 2px; }
.ProseMirror a { color: hsl(var(--primary, 221 83% 53%)); text-decoration: underline; cursor: pointer; }
.ProseMirror strong { font-weight: 700; }
.ProseMirror em { font-style: italic; }
.ProseMirror s { text-decoration: line-through; }
.ProseMirror u { text-decoration: underline; }
`;

function injectEditorStyles(): void {
    if (typeof document === 'undefined' || document.getElementById(EDITOR_CSS_ID)) {
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
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex h-7 w-7 items-center justify-center rounded text-sm transition-colors',
                'hover:bg-muted focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40',
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

/** Resolve uma imagem (File) para URL — usa upload se disponível, senão blob: temporário */
async function resolveImageSrc(
    file: File,
    uploadFn?: (f: File) => Promise<string>,
): Promise<string> {
    if (uploadFn) {
        return uploadFn(file);
    }

    return URL.createObjectURL(file);
}

export function RichTextEditor({
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

    // Ref para o callback de upload — evita recriar o editor quando a prop muda
    const uploadRef = useRef(onImageUpload);
    useEffect(() => {
 uploadRef.current = onImageUpload; 
}, [onImageUpload]);

    useEffect(() => {
        injectEditorStyles();
    }, []);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: headingLevels },
                link: {
                    openOnClick: false,
                    HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' },
                },
            }),
            ...(allowImages ? [ImageExt.configure({ inline: false, allowBase64: true })] : []),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder }),
        ],
        content: value || '',
        editable: !disabled,
        onUpdate: ({ editor: e }) => {
            if (e.isDestroyed) {
                return;
            }

            const html = e.getHTML();
            const normalizedHtml = html === '<p></p>' ? '' : html;

            lastKnownValueRef.current = normalizedHtml;
            onChange(normalizedHtml);
        },
        editorProps: {
            attributes: {
                class: 'px-3 py-2 text-sm leading-relaxed focus:outline-none',
            },
            // ── Drag-and-drop de arquivo no corpo do editor ──────────────
            handleDrop(view, event, _slice, moved) {
                if (moved || !allowImages) {
 return false; 
}

                const file = Array.from(event.dataTransfer?.files ?? []).find((f) =>
                    f.type.startsWith('image/'),
                );

                if (!file) {
 return false; 
}

                event.preventDefault();

                const coords = { left: event.clientX, top: event.clientY };
                const resolved = view.posAtCoords(coords);

                void (async () => {
                    try {
                        const src = await resolveImageSrc(file, uploadRef.current);
                        const { schema, tr } = view.state;
                        const node = schema.nodes.image?.create({ src });

                        if (!node) {
 return; 
}

                        const insertPos = resolved?.pos ?? view.state.doc.content.size;
                        view.dispatch(tr.insert(insertPos, node));
                    } catch { /* upload error — caller deve mostrar toast */ }
                })();

                return true;
            },
            // ── Paste de imagem (Ctrl+V / colar print de tela) ───────────
            handlePaste(view, event) {
                if (!allowImages) {
 return false; 
}

                const items = Array.from(event.clipboardData?.items ?? []);
                const imageItem = items.find((i) => i.type.startsWith('image/'));

                if (!imageItem) {
 return false; 
}

                const file = imageItem.getAsFile();

                if (!file) {
 return false; 
}

                event.preventDefault();

                void (async () => {
                    try {
                        const src = await resolveImageSrc(file, uploadRef.current);
                        view.dispatch(
                            view.state.tr.replaceSelectionWith(
                                view.state.schema.nodes.image!.create({ src }),
                            ),
                        );
                    } catch { /* upload error */ }
                })();

                return true;
            },
        },
    });

    // Sync value from outside (ex: reset de formulário)
    useEffect(() => {
        if (!editor || editor.isDestroyed || editor.isFocused) {
            return;
        }

        const next = value || '';

        if (lastKnownValueRef.current !== next) {
            editor.commands.setContent(next, { emitUpdate: false });
            lastKnownValueRef.current = next;
        }
    }, [value, editor]);

    useEffect(() => {
        if (!editor || editor.isDestroyed) {
            return;
        }

        editor.setEditable(!disabled);
    }, [disabled, editor]);

    // ── Link ──────────────────────────────────────────────────────────────────

    const openLinkDialog = useCallback(() => {
        if (!editor) {
 return; 
}

        setLinkUrl(editor.getAttributes('link').href ?? '');
        setLinkDialogOpen(true);
    }, [editor]);

    const saveLinkUrl = useCallback(() => {
        if (!editor) {
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

    // ── Imagem — dialog ───────────────────────────────────────────────────────

    const openImageDialog = useCallback(() => {
        setImageUrl('');
        setDialogPreview(null);
        dialogFileRef.current = null;
        setHasDialogFile(false);
        setImageDialogOpen(true);
    }, []);

    const insertImageByUrl = useCallback(() => {
        if (!editor || !imageUrl.trim()) {
 return; 
}

        editor.chain().focus().setImage({ src: imageUrl.trim() }).run();
        setImageDialogOpen(false);
        setImageUrl('');
    }, [editor, imageUrl]);

    const insertDialogFile = useCallback(async () => {
        if (!editor || !dialogFileRef.current) {
 return; 
}

        setImageUploading(true);

        try {
            const src = await resolveImageSrc(dialogFileRef.current, uploadRef.current);
            editor.chain().focus().setImage({ src }).run();
            setImageDialogOpen(false);
            setDialogPreview(null);
            dialogFileRef.current = null;
            setHasDialogFile(false);
        } catch {
            /* caller shows toast */
        } finally {
            setImageUploading(false);
        }
    }, [editor]);

    const handleFileInput = useCallback(
        async (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];

            if (!file || !editor) {
 return; 
}

            // Se o dialog estiver aberto, mostra preview; senão insere direto
            if (imageDialogOpen) {
                dialogFileRef.current = file;
                setHasDialogFile(true);
                setDialogPreview(URL.createObjectURL(file));
            } else {
                setImageUploading(true);

                try {
                    const src = await resolveImageSrc(file, uploadRef.current);
                    editor.chain().focus().setImage({ src }).run();
                } catch { /* */ } finally {
                    setImageUploading(false);
                }
            }

            if (fileInputRef.current) {
 fileInputRef.current.value = ''; 
}
        },
        [editor, imageDialogOpen],
    );

    // ── Dialog — drag-and-drop na área do dialog ──────────────────────────────

    const onDialogDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setDialogDragging(true);
    };

    const onDialogDragLeave = (e: React.DragEvent) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node)) {
            setDialogDragging(false);
        }
    };

    const onDialogDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setDialogDragging(false);
        const file = Array.from(e.dataTransfer.files).find((f) =>
            f.type.startsWith('image/'),
        );

        if (!file) {
 return; 
}

        dialogFileRef.current = file;
        setHasDialogFile(true);
        setDialogPreview(URL.createObjectURL(file));
        setImageUrl('');
    }, []);

    // ── Editor wrapper — drag visual feedback ────────────────────────────────

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
                    allowImages && editorDragging && 'border-primary ring-2 ring-primary/20',
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
                    <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-primary bg-primary/5 pointer-events-none">
                        <ImageIcon className="size-10 text-primary/70" />
                        <span className="text-sm font-medium text-primary">
                            Solte para inserir a imagem
                        </span>
                    </div>
                )}

                {/* ── Toolbar ─────────────────────────────────────────────── */}
                <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/30 px-2 py-1.5">
                    <ToolbarBtn
                        title="Desfazer (Ctrl+Z)"
                        onClick={() => editor.chain().focus().undo().run()}
                        disabled={!editor.can().undo()}
                    >
                        <Undo className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn
                        title="Refazer (Ctrl+Y)"
                        onClick={() => editor.chain().focus().redo().run()}
                        disabled={!editor.can().redo()}
                    >
                        <Redo className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn title="Negrito (Ctrl+B)" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}>
                        <Bold className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Itálico (Ctrl+I)" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}>
                        <Italic className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Sublinhado (Ctrl+U)" active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()}>
                        <UnderlineIcon className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Tachado" active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()}>
                        <Strikethrough className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Código inline" active={editor.isActive('code')} onClick={() => editor.chain().focus().toggleCode().run()}>
                        <Code className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn title="Título 1" active={editor.isActive('heading', { level: 1 })} onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}>
                        <Heading1 className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Título 2" active={editor.isActive('heading', { level: 2 })} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>
                        <Heading2 className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Título 3" active={editor.isActive('heading', { level: 3 })} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}>
                        <Heading3 className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn title="Lista com marcadores" active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()}>
                        <List className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Lista numerada" active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()}>
                        <ListOrdered className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn title="Citação" active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()}>
                        <Quote className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Bloco de código" active={editor.isActive('codeBlock')} onClick={() => editor.chain().focus().toggleCodeBlock().run()}>
                        <Code2 className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Separador horizontal" onClick={() => editor.chain().focus().setHorizontalRule().run()}>
                        <Minus className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn title="Alinhar à esquerda" active={editor.isActive({ textAlign: 'left' })} onClick={() => editor.chain().focus().setTextAlign('left').run()}>
                        <AlignLeft className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Centralizar" active={editor.isActive({ textAlign: 'center' })} onClick={() => editor.chain().focus().setTextAlign('center').run()}>
                        <AlignCenter className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Alinhar à direita" active={editor.isActive({ textAlign: 'right' })} onClick={() => editor.chain().focus().setTextAlign('right').run()}>
                        <AlignRight className="size-3.5" />
                    </ToolbarBtn>
                    <ToolbarBtn title="Justificar" active={editor.isActive({ textAlign: 'justify' })} onClick={() => editor.chain().focus().setTextAlign('justify').run()}>
                        <AlignJustify className="size-3.5" />
                    </ToolbarBtn>

                    <ToolbarDivider />

                    <ToolbarBtn title="Inserir / editar link" active={editor.isActive('link')} onClick={openLinkDialog}>
                        <LinkIcon className="size-3.5" />
                    </ToolbarBtn>

                    {allowImages && (
                        <ToolbarBtn title="Inserir imagem" onClick={openImageDialog}>
                            <ImageIcon className="size-3.5" />
                        </ToolbarBtn>
                    )}

                    {allowImages && (
                        <input
                            ref={fileInputRef}
                            type="file"
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
                    </DialogHeader>
                    <div className="space-y-1.5">
                        <Label htmlFor="rte-link-url">URL</Label>
                        <Input
                            id="rte-link-url"
                            placeholder="https://exemplo.com"
                            value={linkUrl}
                            onChange={(e) => setLinkUrl(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && saveLinkUrl()}
                            autoFocus
                        />
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                editor.chain().focus().unsetLink().run();
                                setLinkDialogOpen(false);
                            }}
                        >
                            Remover link
                        </Button>
                        <Button type="button" onClick={saveLinkUrl}>
                            Salvar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ── Image dialog ────────────────────────────────────────────── */}
            <Dialog
                open={imageDialogOpen}
                onOpenChange={(open) => {
                    setImageDialogOpen(open);

                    if (!open) {
                        setDialogPreview(null);
                        dialogFileRef.current = null;
                        setHasDialogFile(false);
                        setImageUrl('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Inserir imagem</DialogTitle>
                    </DialogHeader>

                    {/* Drop zone */}
                    <div
                        className={cn(
                            'relative flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed transition-colors',
                            dialogDragging
                                ? 'border-primary bg-primary/5'
                                : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                        )}
                        onDragOver={onDialogDragOver}
                        onDragLeave={onDialogDragLeave}
                        onDrop={onDialogDrop}
                        onClick={() => fileInputRef.current?.click()}
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
                                <div className="text-center">
                                    <p className="text-sm font-medium">
                                        Arraste uma imagem aqui
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        ou clique para selecionar arquivo
                                    </p>
                                </div>
                            </>
                        )}
                    </div>

                    {/* URL input */}
                    <div className="relative flex items-center gap-3">
                        <div className="h-px flex-1 bg-border" />
                        <span className="text-xs text-muted-foreground">ou cole uma URL</span>
                        <div className="h-px flex-1 bg-border" />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="rte-image-url">URL da imagem</Label>
                        <Input
                            id="rte-image-url"
                            placeholder="https://exemplo.com/imagem.png"
                            value={imageUrl}
                            onChange={(e) => {
                                setImageUrl(e.target.value);

                                if (e.target.value) {
                                    setDialogPreview(null);
                                    dialogFileRef.current = null;
                                    setHasDialogFile(false);
                                }
                            }}
                            onKeyDown={(e) => e.key === 'Enter' && insertImageByUrl()}
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

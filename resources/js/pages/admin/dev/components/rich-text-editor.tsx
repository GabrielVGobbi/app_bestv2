import { Head, setLayoutProps } from '@inertiajs/react';
import {
    BookOpen,
    Code2,
    Database,
    FileText,
    Layers,
    Server,
    Type,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

import {
    components,
    richTextEditor,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import { storeImage } from '@/actions/App/Http/Controllers/Api/UploadController';
import Header from '@/components/dashboard/header';
import { RichTextEditor } from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

// ── Code snippets ─────────────────────────────────────────────────────────────

const basicUsageCode = `import { RichTextEditor } from '@/components/rich-text-editor'

const [body, setBody] = useState<string>('')

<RichTextEditor
    value={body}
    onChange={setBody}
    placeholder="Escreva o conteúdo..."
/>`;

const inertiaFormCode = `import { useForm } from '@inertiajs/react'
import { RichTextEditor } from '@/components/rich-text-editor'

const form = useForm({ body: '' })

<RichTextEditor
    value={form.data.body}
    onChange={(value) => form.setData('body', value)}
    disabled={form.processing}
    placeholder="Descreva o post..."
/>`;

const withImageUploadCode = `import { storeImage } from '@/actions/App/Http/Controllers/Api/UploadController'
import { RichTextEditor } from '@/components/rich-text-editor'

async function uploadImage(file: File): Promise<string> {
    const fd = new FormData()
    fd.append('image', file)
    const res = await fetch(storeImage.url(), {
        method: 'POST',
        body: fd,
        headers: {
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    })
    const data = await res.json()
    return data.url  // URL pública salva em storage/public
}

<RichTextEditor
    value={body}
    onChange={setBody}
    onImageUpload={uploadImage}
/>`;

const displayCode = `// Exibir conteúdo salvo (nunca renderize HTML sem sanitização!)
import DOMPurify from 'dompurify'

function ArticleBody({ html }: { html: string }) {
    return (
        <div
            className="prose max-w-none"
            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(html) }}
        />
    )
}`;

const multipleFieldsCode = `// Model com dois campos ricos independentes
class Article extends Model {
    use HasRichText;
}

// Salvar body e summary separadamente
$article->setRichText($request->body, field: 'body');
$article->setRichText($request->summary, field: 'summary');

// Ler sob demanda (lazy, não carregado na listagem)
$article->getRichText('body')?->toHtml();

// Eager-load para evitar N+1 em show pages
Article::with('richTexts')->find($id);`;

const sqlSchemaCode = `-- Tabela polimórfica para conteúdo rico
-- Separada para não poluir SELECTs de listagem
CREATE TABLE rich_texts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    richable_type VARCHAR(255)  NOT NULL,          -- App\\Models\\Post
    richable_id   BIGINT UNSIGNED NOT NULL,        -- id do post
    field         VARCHAR(100)  NOT NULL DEFAULT 'body',
    format        ENUM('html', 'tiptap_json') NOT NULL DEFAULT 'html',
    body          LONGTEXT      NOT NULL,           -- conteúdo principal
    body_html     LONGTEXT      NULL,               -- cache HTML para JSON
    created_at    TIMESTAMP     NULL,
    updated_at    TIMESTAMP     NULL,

    PRIMARY KEY (id),
    -- índice do morphs() gerado pelo Laravel
    INDEX idx_richable (richable_type, richable_id),
    -- garante um campo por entidade
    UNIQUE KEY rich_texts_unique (richable_type, richable_id, field)
);

-- Exemplo: posts sem conteúdo carregado na listagem
SELECT id, title, published_at FROM posts LIMIT 20;

-- Carrega conteúdo APENAS quando necessário (show page)
SELECT r.body
FROM   rich_texts r
WHERE  r.richable_type = 'App\\Models\\Post'
  AND  r.richable_id   = 42
  AND  r.field         = 'body';`;

const traitUsageCode = `// app/Models/Post.php
use App\\Concerns\\HasRichText;

class Post extends Model
{
    use HasRichText;

    // rich_texts NÃO fica em $with, só carregado quando explicitado
}

// Controller: listagem (sem body)
public function index(): Response
{
    return Inertia::render('posts/index', [
        'posts' => Post::select('id', 'title', 'published_at')
                       ->paginate(20),
    ]);
}

// Controller: show page (com body)
public function show(Post $post): Response
{
    return Inertia::render('posts/show', [
        'post' => $post,
        'body' => $post->getRichText('body')?->toHtml(),
    ]);
}

// Controller: store
public function store(StorePostRequest $request): RedirectResponse
{
    $post = Post::create($request->safe()->except('body'));
    $post->setRichText($request->validated('body'));

    return redirect()->route('posts.show', $post);
}`;

const imageStorageCode = `// ❌ NUNCA salve base64 no banco: payload gigante, sem CDN, sem resize
// <img src="data:image/png;base64,iVBORw0KGgo..." />

// ✅ Upload para S3 / storage local e salve só a URL
public function uploadImage(Request $request): JsonResponse
{
    $request->validate(['image' => ['required', 'image', 'max:5120']]);

    $path = $request->file('image')->store('rich-text/images', 's3');
    $url  = Storage::disk('s3')->url($path);

    return response()->json(['url' => $url]);
}`;

// ── Helpers ───────────────────────────────────────────────────────────────────

function CodeBlock({ code }: { code: string }) {
    return (
        <pre className="overflow-x-auto rounded-lg border bg-muted/50 p-4 text-xs leading-relaxed">
            <code>{code}</code>
        </pre>
    );
}

function FeatureCard({
    icon: Icon,
    title,
    description,
}: {
    icon: React.ElementType;
    title: string;
    description: string;
}) {
    return (
        <Card className="rounded-lg">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Icon className="size-4" />
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
        </Card>
    );
}

function PropRow({
    name,
    type,
    description,
    required = false,
}: {
    name: string;
    type: string;
    description: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-1 border-b py-3 last:border-b-0">
            <div className="flex flex-wrap items-center gap-2">
                <code className="text-sm font-semibold text-primary">
                    {name}
                </code>
                {required && <Badge variant="outline">obrigatório</Badge>}
            </div>
            <code className="w-fit rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                {type}
            </code>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function RichTextEditorPage() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Rich Text Editor', href: richTextEditor.url() },
        ],
    });

    const [body, setBody] = useState('');
    const [disabled, setDisabled] = useState(false);
    const [minHeight, setMinHeight] = useState<200 | 300 | 400>(200);

    async function uploadImage(file: File): Promise<string> {
        const fd = new FormData();
        fd.append('image', file);
        const res = await fetch(storeImage.url(), {
            method: 'POST',
            body: fd,
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
        });
        const data = (await res.json()) as { url: string };

        return data.url;
    }

    return (
        <>
            <Head title="Rich Text Editor" />

            <Header
                title="Rich Text Editor"
                subtitle="Editor WYSIWYG headless baseado em TipTap/ProseMirror. Toolbar completa, upload de imagem, link dialog e saída em HTML."
            >
                <Badge variant="secondary" className="w-fit">
                    resources/js/components/rich-text-editor
                </Badge>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    {/* Header */}

                    {/* Feature cards */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <FeatureCard
                            icon={Type}
                            title="Toolbar completa"
                            description="Negrito, itálico, sublinhado, tachado, títulos, listas, alinhamento, citação, código, HR e link."
                        />
                        <FeatureCard
                            icon={Layers}
                            title="Controlled component"
                            description="value + onChange. Compatível com useForm do Inertia e qualquer biblioteca de formulários."
                        />
                        <FeatureCard
                            icon={Database}
                            title="Banco de dados performático"
                            description="Tabela rich_texts polimórfica separada. Conteúdo não é carregado em listagens, apenas em show pages."
                        />
                    </div>

                    {/* Playground */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Zap className="size-5" />
                                        Playground
                                    </CardTitle>
                                    <CardDescription className="mt-1.5">
                                        Editor ao vivo. Veja o HTML gerado no
                                        painel abaixo.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            disabled ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => setDisabled((d) => !d)}
                                    >
                                        Disabled: {disabled ? 'sim' : 'não'}
                                    </Button>
                                    {([200, 300, 400] as const).map((h) => (
                                        <Button
                                            key={h}
                                            type="button"
                                            variant={
                                                minHeight === h
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() => setMinHeight(h)}
                                        >
                                            {h}px
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <RichTextEditor
                                value={body}
                                onChange={setBody}
                                disabled={disabled}
                                minHeight={minHeight}
                                onImageUpload={uploadImage}
                                placeholder="Escreva aqui... tente negrito, títulos, listas e links. Arraste uma imagem!"
                            />

                            <div className="rounded-lg border bg-muted/30 p-3">
                                <div className="mb-1 text-xs font-medium text-muted-foreground">
                                    HTML gerado (value)
                                </div>
                                <code className="text-xs break-all">
                                    {body || (
                                        <span className="text-muted-foreground">
                                            vazio
                                        </span>
                                    )}
                                </code>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Code examples: 2 columns */}
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Code2 className="size-5" />
                                    Uso básico
                                </CardTitle>
                                <CardDescription>
                                    useState e Inertia useForm.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={basicUsageCode} />
                                <CodeBlock code={inertiaFormCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-5" />
                                    Upload de imagem e exibição
                                </CardTitle>
                                <CardDescription>
                                    onImageUpload + dangerouslySetInnerHTML.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <CodeBlock code={withImageUploadCode} />
                                <CodeBlock code={displayCode} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Database section */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="size-5" />
                                Arquitetura de banco de dados
                            </CardTitle>
                            <CardDescription>
                                Tabela polimórfica separada para evitar
                                carregamento desnecessário em listagens.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 rounded-lg border bg-muted/20 p-4 text-sm">
                                <p className="font-medium">
                                    Por que uma tabela separada?
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                                    <li>
                                        Um campo <code>LONGTEXT</code> com
                                        HTML/JSON pesado é transmitido em{' '}
                                        <strong>todo SELECT</strong> mesmo que
                                        você nunca use o conteúdo na listagem.
                                    </li>
                                    <li>
                                        Com a tabela separada, listagens de
                                        posts/artigos ficam leves. O conteúdo é
                                        carregado{' '}
                                        <strong>apenas na show page</strong> com
                                        uma query extra.
                                    </li>
                                    <li>
                                        A relação polimórfica permite reutilizar
                                        em <code>Post</code>, <code>Task</code>,{' '}
                                        <code>Comment</code> ou qualquer outro
                                        model sem criar novas tabelas.
                                    </li>
                                    <li>
                                        O campo <code>field</code> permite
                                        múltiplos blocos ricos por entidade (ex:{' '}
                                        <em>body</em>, <em>summary</em>,{' '}
                                        <em>notes</em>).
                                    </li>
                                </ul>
                            </div>

                            <div className="grid gap-4 xl:grid-cols-2">
                                <div className="space-y-4">
                                    <p className="text-sm font-medium">
                                        Schema SQL
                                    </p>
                                    <CodeBlock code={sqlSchemaCode} />
                                </div>
                                <div className="space-y-4">
                                    <p className="text-sm font-medium">
                                        Trait + Controller
                                    </p>
                                    <CodeBlock code={traitUsageCode} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Multiple fields + images */}
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Server className="size-5" />
                                    Múltiplos campos por entidade
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CodeBlock code={multipleFieldsCode} />
                            </CardContent>
                        </Card>

                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-5" />
                                    Imagens: S3 vs base64
                                </CardTitle>
                                <CardDescription>
                                    Nunca salve base64 no banco. Sempre faça
                                    upload e salve a URL.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm">
                                    <p className="font-medium text-destructive">
                                        base64 no banco, problemas:
                                    </p>
                                    <ul className="mt-1 list-disc space-y-0.5 pl-4 text-muted-foreground">
                                        <li>
                                            Tamanho 33% maior que o arquivo
                                            original
                                        </li>
                                        <li>
                                            Sem CDN, sem cache de borda, sem
                                            resize automático
                                        </li>
                                        <li>
                                            Tabela cresce até GBs rapidamente
                                        </li>
                                        <li>Backup lento, dump pesado</li>
                                    </ul>
                                </div>
                                <div className="rounded-lg border border-success-soft-foreground/20 bg-success-soft p-3 text-sm">
                                    <p className="font-medium text-success-soft-foreground">
                                        S3 / storage + URL, recomendado:
                                    </p>
                                    <ul className="mt-1 list-disc space-y-0.5 pl-4 text-muted-foreground">
                                        <li>
                                            Upload via endpoint dedicado,
                                            retorna URL pública
                                        </li>
                                        <li>
                                            CDN entrega direto ao cliente (sem
                                            passar pelo PHP)
                                        </li>
                                        <li>
                                            Resize e otimização via Imgproxy /
                                            Cloudflare Images
                                        </li>
                                        <li>
                                            Banco armazena apenas uma string
                                            pequena
                                        </li>
                                    </ul>
                                </div>
                                <CodeBlock code={imageStorageCode} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Props API */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BookOpen className="size-5" />
                                API do componente
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PropRow
                                name="value"
                                type="string | null"
                                required
                                description="Conteúdo HTML controlado. Passe string vazia ou null para editor vazio."
                            />
                            <PropRow
                                name="onChange"
                                type="(value: string) => void"
                                required
                                description="Callback disparado a cada alteração. Recebe HTML vazio ('') quando o editor está vazio."
                            />
                            <PropRow
                                name="placeholder"
                                type="string"
                                description="Texto exibido quando o editor está vazio. Padrão: 'Escreva algo...'"
                            />
                            <PropRow
                                name="disabled"
                                type="boolean"
                                description="Desativa o editor e a toolbar. Use durante form.processing para evitar edições concorrentes."
                            />
                            <PropRow
                                name="minHeight"
                                type="number"
                                description="Altura mínima da área de edição em px. Padrão: 200."
                            />
                            <PropRow
                                name="className"
                                type="string"
                                description="Classes extras aplicadas ao container externo do editor."
                            />
                            <PropRow
                                name="onImageUpload"
                                type="(file: File) => Promise<string>"
                                description="Callback para upload de imagem. Se omitido, o botão de imagem não aparece na toolbar. Deve retornar a URL pública da imagem."
                            />
                        </CardContent>
                    </Card>

                    {/* Editor comparison */}
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Layers className="size-5" />
                                WYSIWYG vs Editor de Blocos: quando usar cada um
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-lg border p-4">
                                    <p className="mb-2 font-semibold">
                                        WYSIWYG (este componente, TipTap)
                                    </p>
                                    <ul className="list-disc space-y-1 pl-4 text-sm text-muted-foreground">
                                        <li>
                                            Ideal para: posts, artigos,
                                            descrições, e-mails
                                        </li>
                                        <li>
                                            Output: HTML limpo, fácil de exibir
                                            e indexar
                                        </li>
                                        <li>
                                            Curva de aprendizado: zero para o
                                            usuário final
                                        </li>
                                        <li>
                                            Bibliotecas: <strong>TipTap</strong>{' '}
                                            (headless, extensível) →{' '}
                                            <strong>Quill 2</strong> (simples,
                                            maduro) →{' '}
                                            <strong>CKEditor 5</strong>{' '}
                                            (enterprise)
                                        </li>
                                    </ul>
                                </div>
                                <div className="rounded-lg border p-4">
                                    <p className="mb-2 font-semibold">
                                        Editor de Blocos (Notion-like)
                                    </p>
                                    <ul className="list-disc space-y-1 pl-4 text-sm text-muted-foreground">
                                        <li>
                                            Ideal para: wikis, bases de
                                            conhecimento, construtores de
                                            páginas, docs internos
                                        </li>
                                        <li>
                                            Output: JSON estruturado por bloco
                                        </li>
                                        <li>
                                            Mais flexível para reordenar e
                                            transformar blocos
                                        </li>
                                        <li>
                                            Bibliotecas:{' '}
                                            <strong>BlockNote</strong> (TipTap
                                            por baixo, pronto) →{' '}
                                            <strong>Editor.js</strong> (leve,
                                            modular) → <strong>Plate</strong>{' '}
                                            (Slate, altamente customizável)
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div className="mt-4 rounded-lg border bg-muted/20 p-3 text-sm text-muted-foreground">
                                <strong>Dica de arquitetura:</strong> a tabela{' '}
                                <code>rich_texts</code> suporta ambos via o
                                campo <code>format</code> (<code>html</code>{' '}
                                para WYSIWYG, <code>tiptap_json</code> para
                                blocos). Se migrar para blocos no futuro, só
                                muda o frontend, o banco continua o mesmo.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

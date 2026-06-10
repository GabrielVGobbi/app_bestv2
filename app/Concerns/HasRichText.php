<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\RichText;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * Adiciona suporte a conteúdo rico polimórfico em qualquer Model.
 *
 * Uso:
 *   class Post extends Model {
 *       use HasRichText;
 *   }
 *
 *   // Salvar (move imagens de temp/ para permanente automaticamente)
 *   $post->setRichText($request->validated('body'));
 *   $post->setRichText($request->validated('summary'), field: 'summary');
 *
 *   // Ler (lazy — não carregado na listagem)
 *   $post->getRichText()?->toHtml();
 *
 *   // Eager load para evitar N+1 em show pages
 *   Post::with('richTexts')->find($id);
 */
trait HasRichText
{
    public function richTexts(): MorphMany
    {
        return $this->morphMany(RichText::class, 'richable');
    }

    /**
     * Retorna o RichText de um campo específico (não carregado automaticamente).
     */
    public function getRichText(string $field = 'body'): ?RichText
    {
        if ($this->relationLoaded('richTexts')) {
            return $this->richTexts->firstWhere('field', $field);
        }

        return $this->richTexts()->where('field', $field)->first();
    }

    /**
     * Cria ou atualiza o conteúdo rico de um campo.
     * Move automaticamente imagens de /temp para /permanent antes de persistir.
     *
     * @param  string  $body  HTML ou JSON serializado
     * @param  string  $field  Nome do campo (body, summary, notes…)
     * @param  string  $format  html | tiptap_json
     */
    public function setRichText(
        string $body,
        string $field = 'body',
        string $format = 'html',
        ?string $bodyHtml = null,
    ): RichText {
        $body = $this->promoteRichTextImages($body);

        return $this->richTexts()->updateOrCreate(
            ['field' => $field],
            [
                'body' => $body,
                'format' => $format,
                'body_html' => $bodyHtml,
            ],
        );
    }

    /**
     * Remove o conteúdo rico de um campo específico.
     */
    public function deleteRichText(string $field = 'body'): void
    {
        $this->richTexts()->where('field', $field)->delete();
    }

    /**
     * Move imagens de uploads/rich-text/temp/ para uploads/rich-text/
     * e atualiza as URLs no HTML retornado.
     *
     * Imagens que não estão em /temp/ (ex: URLs externas, já permanentes)
     * são ignoradas e mantidas como estão.
     */
    private function promoteRichTextImages(string $html): string
    {
        $disk = Storage::disk('public');

        // Captura todas as src que apontam para nossa pasta temp
        preg_match_all('/src="([^"]*uploads\/rich-text\/temp\/[^"]+)"/', $html, $matches);

        foreach ($matches[1] as $tempUrl) {
            $tempPath = $this->urlToStoragePath($tempUrl, $disk);

            if ($tempPath === null || ! $disk->exists($tempPath)) {
                continue;
            }

            $filename = basename($tempPath);
            $permPath = 'uploads/rich-text/'.$filename;

            $disk->move($tempPath, $permPath);

            $permUrl = $disk->url($permPath);
            $html = str_replace($tempUrl, $permUrl, $html);
        }

        return $html;
    }

    /**
     * Converte uma URL pública do storage de volta para o path relativo.
     * Suporta storage local (public disk).
     */
    private function urlToStoragePath(string $url, Filesystem $disk): ?string
    {
        // Extrai o segmento após /storage/ na URL
        if (preg_match('#/storage/(.+)$#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}

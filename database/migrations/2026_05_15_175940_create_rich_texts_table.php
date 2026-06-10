<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela polimórfica para conteúdo rico (HTML/JSON).
     *
     * Separada das entidades principais para evitar o carregamento desnecessário
     * do payload pesado em listagens. O conteúdo é carregado apenas sob demanda
     * via relacionamento Eloquent ou by explicit eager-load.
     */
    public function up(): void
    {
        Schema::create('rich_texts', function (Blueprint $table): void {
            $table->id();

            // Relação polimórfica: aponta para qualquer model (Post, Article, Task…)
            $table->morphs('richable'); // richable_type + richable_id + índice composto

            // Permite múltiplos campos ricos por entidade (body, summary, notes…)
            $table->string('field', 100)->default('body');

            // Formato do conteúdo armazenado
            $table->enum('format', ['html', 'tiptap_json'])->default('html');

            // Conteúdo principal (HTML ou JSON do TipTap)
            $table->longText('body');

            // HTML pré-renderizado para formatos JSON (cache de leitura, nullable)
            $table->longText('body_html')->nullable();

            $table->timestamps();

            // Garante um único registro por (entidade + campo)
            $table->unique(['richable_type', 'richable_id', 'field'], 'rich_texts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rich_texts');
    }
};

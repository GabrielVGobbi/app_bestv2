<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Faz upload de uma imagem do Rich Text Editor e retorna a URL pública.
     *
     * POST /api/v1/uploads/image
     * Body: multipart/form-data { image: File }
     * Response: { "url": "https://..." }
     */
    public function storeImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:5120'],
        ]);

        // Salva em /temp — será movido para permanente ao salvar o formulário
        // Arquivos em /temp não referenciados são removidos pelo job diário
        $path = $request->file('image')->store('uploads/rich-text/temp', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path, // útil para debug/log, não expõe nada sensível
        ]);
    }
}

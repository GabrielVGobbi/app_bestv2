<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExportResource;
use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $exports = Export::where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('app/exports/index', [
            'exports' => ExportResource::collection($exports)->resolve(),
        ]);
    }

    public function download(Export $export, Request $request): StreamedResponse
    {
        abort_if(! $export->isOwnedBy($request->user()), 403);
        abort_if($export->status !== Export::STATUS_COMPLETED, 404);
        abort_if($export->isExpired(), 410);

        $disk = config('exports.disk', 'local');

        abort_if(! Storage::disk($disk)->exists($export->file_path), 404);

        return Storage::disk($disk)->download($export->file_path, $export->file_name);
    }
}

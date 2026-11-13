<?php

namespace App\Http\Controllers;

use App\Services\PublicMediaService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function show(string $path): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $path = PublicMediaService::normalize($path);
        if ($path === null || !PublicMediaService::isManaged($path)) {
            abort(404);
        }

        if (PublicMediaService::usesObjectStorage()) {
            return redirect()->away(Storage::disk('public')->url($path), 301);
        }

        $full = PublicMediaService::localFullPath($path);
        if ($full === null) {
            abort(404);
        }

        $response = response()->file($full, [
            'Cache-Control' => PublicMediaService::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setAutoEtag(true);

        return $response;
    }
}

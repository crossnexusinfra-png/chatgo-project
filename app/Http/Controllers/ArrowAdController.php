<?php

namespace App\Http\Controllers;

use App\Services\AdNetworkService;
use Illuminate\Http\Response;

class ArrowAdController extends Controller
{
    /**
     * 公式タグは document.write で枠を出すため、親ページではなくこの文書の中で実行する。
     */
    public function show(string $slot, AdNetworkService $ads): Response
    {
        $spec = $ads->arrowSlotSpec($slot);
        if ($spec === null) {
            abort(404);
        }

        $id = $spec['id'];
        $width = $spec['width'];
        $height = $spec['height'];
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
            .'<body style="margin:0">'
            .'<script>'
            .'ARROW_adspotId = '.$id.';'
            .' ARROW_css = "https://ad.ad-arrow.com/static/css/banner.css";'
            .' ARROW_ifw = "'.$width.'px";'
            .' ARROW_ifh = "'.$height.'px";'
            .' ARROW_ifb = "none";'
            .' ARROW_iftr = 1;'
            .'</script>'
            .'<script src="https://ad.ad-arrow.com/static/banner.js"></script>'
            .'</body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => $ads->arrowDocumentCsp(),
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }
}

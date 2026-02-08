<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CspMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // TelescopeのパスではCSPを適用しない（Telescopeのアセットが正しく読み込まれるように）
        $telescopePath = config('telescope.path', 'telescope');
        if ($request->is($telescopePath . '*')) {
            return $next($request);
        }
        
        // nonceを生成（リクエストごとに生成）
        $nonce = base64_encode(Str::random(32));
        
        // セッションが利用可能な場合のみセッションに保存
        if ($request->hasSession()) {
            $request->session()->put('csp_nonce', $nonce);
        }
        
        // ビューで使用できるように共有
        view()->share('csp_nonce', $nonce);
        
        $response = $next($request);

        // Clickjacking対策: X-Frame-Optionsヘッダーを設定
        // 古いブラウザとの互換性のため、CSPのframe-ancestorsと併用
        $response->headers->set('X-Frame-Options', 'DENY');

        // CSPが有効な場合のみヘッダーを追加
        if (config('csp.enabled', true)) {
            $policies = config('csp.policies', []);
            $cspHeader = $this->buildCspHeader($policies, $nonce);
            
            $headerName = config('csp.report_only', false) 
                ? 'Content-Security-Policy-Report-Only' 
                : 'Content-Security-Policy';
            
            $response->headers->set($headerName, $cspHeader);
            
            // レポートURIが設定されている場合は追加
            if ($reportUri = config('csp.report_uri')) {
                $response->headers->set($headerName, $cspHeader . '; report-uri ' . $reportUri);
            }
        }

        return $response;
    }

    /**
     * CSPヘッダーを構築
     */
    private function buildCspHeader(array $policies, string $nonce): string
    {
        $directives = [];
        
        foreach ($policies as $directive => $sources) {
            $sourceList = is_array($sources) ? $sources : [$sources];
            
            // script-srcとstyle-srcにnonceを追加
            if (in_array($directive, ['script-src', 'style-src'])) {
                $sourceList[] = "'nonce-{$nonce}'";
            }
            
            $directives[] = $directive . ' ' . implode(' ', $sourceList);
        }
        
        return implode('; ', $directives);
    }
}


@php
    $seoService = $seoService ?? app(\App\Services\SeoService::class);
    $robotsContent = trim($__env->yieldContent('robots'));
    if ($robotsContent === '') {
        $robotsContent = $seoRobots ?? $seoService->robotsMeta(
            $thread ?? null,
            isset($isR18Thread) ? (bool) $isR18Thread : null,
        );
    }
    $canonicalHref = $seoCanonical ?? $seoService->canonicalUrl();
@endphp
<meta name="robots" content="{{ $robotsContent }}">
<link rel="canonical" href="{{ $canonicalHref }}">
@foreach ($seoService->hreflangLinks() as $hreflangLink)
<link rel="alternate" hreflang="{{ $hreflangLink['hreflang'] }}" href="{{ $hreflangLink['href'] }}">
@endforeach

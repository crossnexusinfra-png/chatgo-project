<?php

namespace App\Services;

class AdNetworkService
{
    /**
     * 一般向けページで左右レール（GAM）を出すルート。
     *
     * @var list<string>
     */
    private const GAM_RAIL_ROUTES = [
        'threads.show',
        'legal.guide',
        'legal.faq',
        'legal.articles',
        'legal.articles.show',
    ];

    /**
     * 一般向けページでインライン枠（GAM）を出すルート。
     *
     * @var list<string>
     */
    private const GAM_INLINE_ROUTES = [
        'threads.index',
        'threads.category',
        'threads.tag',
        'threads.search',
        'threads.show',
    ];

    /**
     * コイン獲得のリワード広告を出すルート。R18 文脈では出さない。
     *
     * @var list<string>
     */
    private const GAM_REWARDED_ROUTES = [
        'threads.index',
        'threads.show',
    ];

    public function isAdultContext(bool $isR18Room, bool $searchIncludesR18): bool
    {
        return $isR18Room || $searchIncludesR18;
    }

    public function gamConfigured(): bool
    {
        if (! (bool) config('ads.gam.enabled')) {
            return false;
        }

        if ($this->trimmed('ads.gam.network_code') === '') {
            return false;
        }

        return $this->gamSlotPath('rail') !== null
            || $this->hasGamInlineUnit()
            || $this->gamSlotPath('interstitial') !== null
            || $this->gamSlotPath('rewarded') !== null;
    }

    /**
     * バナー S・M・L のパス。列幅に合う1つだけを GPT が要求する。
     *
     * @return array{l?: string, m?: string, s?: string}
     */
    public function gamBannerPaths(): array
    {
        $paths = [];
        foreach (['l' => 'banner_l', 'm' => 'banner_m', 's' => 'banner_s'] as $size => $key) {
            $path = $this->gamSlotPath($key);
            if ($path !== null) {
                $paths[$size] = $path;
            }
        }

        return $paths;
    }

    public function hasGamInlineUnit(): bool
    {
        return $this->gamBannerPaths() !== []
            || $this->gamSlotPath('inline') !== null;
    }

    public function gamSlotPath(string $slotKey): ?string
    {
        $network = $this->trimmed('ads.gam.network_code');
        $unit = $this->trimmed('ads.gam.slots.'.$slotKey);
        if ($network === '' || $unit === '') {
            return null;
        }

        if (str_starts_with($unit, '/')) {
            return $unit;
        }

        return '/'.$network.'/'.$unit;
    }

    public function shouldLoadGpt(?string $routeName, bool $isR18Room, bool $searchIncludesR18): bool
    {
        if (! $this->gamConfigured()) {
            return false;
        }

        if ($this->isAdultContext($isR18Room, $searchIncludesR18)) {
            return false;
        }

        if ($routeName !== null && str_starts_with($routeName, 'admin.')) {
            return false;
        }

        if ($this->gamSlotPath('interstitial') !== null) {
            return true;
        }

        if ($this->gamSlotPath('rail') !== null && in_array($routeName, self::GAM_RAIL_ROUTES, true)) {
            return true;
        }

        if ($this->shouldServeGamRewarded($isR18Room, $searchIncludesR18)
            && in_array($routeName, self::GAM_REWARDED_ROUTES, true)) {
            return true;
        }

        return $this->hasGamInlineUnit()
            && in_array($routeName, self::GAM_INLINE_ROUTES, true);
    }

    public function shouldServeGamRewarded(bool $isR18Room, bool $searchIncludesR18): bool
    {
        if ($this->isAdultContext($isR18Room, $searchIncludesR18)) {
            return false;
        }

        if (! (bool) config('ads.enabled') || ! (bool) config('ads.gam.enabled')) {
            return false;
        }

        return $this->gamSlotPath('rewarded') !== null;
    }

    public function shouldServeGamInline(bool $isR18Room, bool $searchIncludesR18): bool
    {
        if ($this->isAdultContext($isR18Room, $searchIncludesR18)) {
            return false;
        }

        if (! (bool) config('ads.gam.enabled')) {
            return false;
        }

        return $this->hasGamInlineUnit();
    }

    public function shouldServeGamRail(string $side, bool $isR18Room, bool $searchIncludesR18): bool
    {
        if ($this->isAdultContext($isR18Room, $searchIncludesR18)) {
            return false;
        }

        if (! (bool) config('ads.gam.enabled')) {
            return false;
        }

        return $this->gamSlotPath('rail') !== null;
    }

    /**
     * ID が入っているネットワークの CSP 追加オリジン。審査中（空 ID）は空。
     *
     * @return array<string, list<string>>
     */
    public function extraCspSources(): array
    {
        $extra = [
            'script-src' => [],
            'img-src' => [],
            'connect-src' => [],
            'frame-src' => [],
        ];

        if ($this->gamConfigured()) {
            $extra['script-src'][] = 'https://securepubads.g.doubleclick.net';
            $extra['script-src'][] = 'https://www.googletagservices.com';
            $extra['connect-src'][] = 'https://securepubads.g.doubleclick.net';
            $extra['frame-src'][] = 'https://securepubads.g.doubleclick.net';
        }

        if ($this->arrowConfigured()) {
            $extra['frame-src'][] = "'self'";
            $extra['frame-src'][] = 'https://ad.ad-arrow.com';
            $extra['img-src'][] = 'https://ad.ad-arrow.com';
            $extra['img-src'][] = 'https://m6bf8zz8.user.webaccel.jp';
        }

        return $extra;
    }

    /**
     * R18 で読む直配信。
     */
    public function adultDirectNetwork(): string
    {
        return 'arrow';
    }

    public function arrowConfigured(): bool
    {
        if (! (bool) config('ads.arrow.enabled')) {
            return false;
        }

        foreach (['banner_l', 'banner_m', 'banner_s', 'rail'] as $key) {
            if ($this->arrowSpot($key) !== null) {
                return true;
            }
        }

        return false;
    }

    public function arrowSpot(string $key): ?string
    {
        $id = $this->trimmed('ads.arrow.spots.'.$key);

        return ctype_digit($id) ? $id : null;
    }

    /**
     * @return array{id: int, width: int, height: int}|null
     */
    public function arrowSlotSpec(string $slot): ?array
    {
        $map = [
            'l' => ['banner_l', 728, 90],
            'm' => ['banner_m', 468, 60],
            's' => ['banner_s', 320, 50],
            'rail' => ['rail', 300, 250],
        ];
        if (! isset($map[$slot]) || ! $this->arrowConfigured()) {
            return null;
        }

        [$key, $width, $height] = $map[$slot];
        $id = $this->arrowSpot($key);
        if ($id === null) {
            return null;
        }

        return [
            'id' => (int) $id,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * 列幅に合わせて1つだけ読み込むバナー枠の URL。
     *
     * @return array{l?: string, m?: string, s?: string}
     */
    public function arrowInlineSources(): array
    {
        $sources = [];
        foreach (['l', 'm', 's'] as $slot) {
            if ($this->arrowSlotSpec($slot) === null) {
                continue;
            }
            $sources[$slot] = route('ads.arrow', ['slot' => $slot]);
        }

        return $sources;
    }

    public function arrowRailSource(): ?string
    {
        if ($this->arrowSlotSpec('rail') === null) {
            return null;
        }

        return route('ads.arrow', ['slot' => 'rail']);
    }

    public function shouldLoadArrow(bool $isR18Room, bool $searchIncludesR18): bool
    {
        return $this->arrowConfigured() && $this->isAdultContext($isR18Room, $searchIncludesR18);
    }

    public function shouldServeArrowInline(bool $isR18Room, bool $searchIncludesR18): bool
    {
        return $this->shouldLoadArrow($isR18Room, $searchIncludesR18)
            && $this->arrowInlineSources() !== [];
    }

    public function shouldServeArrowRail(string $side, bool $isR18Room, bool $searchIncludesR18): bool
    {
        if (! $this->shouldLoadArrow($isR18Room, $searchIncludesR18)) {
            return false;
        }

        return $this->arrowRailSource() !== null;
    }

    /**
     * 公式タグを iframe 内だけで実行するための CSP。親ページの nonce 付き CSP とは分ける。
     */
    public function arrowDocumentCsp(): string
    {
        return implode('; ', [
            "default-src 'none'",
            "script-src 'unsafe-inline' https://ad.ad-arrow.com https://genieedmp.com",
            "style-src 'unsafe-inline' https://ad.ad-arrow.com https://m6bf8zz8.user.webaccel.jp",
            "img-src https://ad.ad-arrow.com https://m6bf8zz8.user.webaccel.jp data:",
            "connect-src https://ad.ad-arrow.com",
            "frame-src https://ad.ad-arrow.com",
            "frame-ancestors 'self'",
            "base-uri 'none'",
        ]);
    }

    private function trimmed(string $key): string
    {
        return trim((string) config($key, ''));
    }
}

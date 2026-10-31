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
            || $this->gamSlotPath('inline') !== null
            || $this->gamSlotPath('interstitial') !== null
            || $this->gamSlotPath('rewarded') !== null;
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

        return $this->gamSlotPath('inline') !== null
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

        return $this->gamSlotPath('inline') !== null;
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

    public function exoclickConfigured(): bool
    {
        if (! (bool) config('ads.exoclick.enabled')) {
            return false;
        }

        return $this->exoclickZone('rail') !== null
            || $this->exoclickZone('inline') !== null;
    }

    public function exoclickZone(string $key): ?string
    {
        $id = $this->trimmed('ads.exoclick.zones.'.$key);

        return $id === '' ? null : $id;
    }

    public function shouldLoadExoclick(bool $isR18Room, bool $searchIncludesR18): bool
    {
        return $this->exoclickConfigured() && $this->isAdultContext($isR18Room, $searchIncludesR18);
    }

    public function shouldServeExoclickRail(string $side, bool $isR18Room, bool $searchIncludesR18): bool
    {
        if (! $this->shouldLoadExoclick($isR18Room, $searchIncludesR18)) {
            return false;
        }

        return $this->exoclickZone('rail') !== null;
    }

    public function shouldServeExoclickInline(bool $isR18Room, bool $searchIncludesR18): bool
    {
        return $this->shouldLoadExoclick($isR18Room, $searchIncludesR18)
            && $this->exoclickZone('inline') !== null;
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

        if ($this->exoclickConfigured()) {
            $extra['script-src'][] = 'https://a.realsrv.com';
            $extra['script-src'][] = 'https://ads.exoclick.com';
            $extra['img-src'][] = 'https://a.realsrv.com';
            $extra['img-src'][] = 'https://syndication.exoclick.com';
            $extra['img-src'][] = 'https://ads.exoclick.com';
            $extra['connect-src'][] = 'https://a.realsrv.com';
            $extra['connect-src'][] = 'https://ads.exoclick.com';
            $extra['frame-src'][] = 'https://a.realsrv.com';
            $extra['frame-src'][] = 'https://syndication.exoclick.com';
            $extra['frame-src'][] = 'https://ads.exoclick.com';
        }

        return $extra;
    }

    private function trimmed(string $key): string
    {
        return trim((string) config($key, ''));
    }
}

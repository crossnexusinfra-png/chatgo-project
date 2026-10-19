<?php

namespace App\Services;

class AdNetworkService
{
    /**
     * SFW の GAM レールを出すルート（Google + ExoClick を同一ページに載せない）。
     *
     * @var list<string>
     */
    private const GAM_INVENTORY_ROUTES = [
        'threads.show',
        'legal.guide',
        'legal.faq',
        'legal.articles',
        'legal.articles.show',
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

        return $this->gamSlotPath('rail_left') !== null
            || $this->gamSlotPath('rail_right') !== null;
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

        return in_array($routeName, self::GAM_INVENTORY_ROUTES, true);
    }

    public function shouldServeGamRail(string $side, bool $isR18Room, bool $searchIncludesR18): bool
    {
        if ($this->isAdultContext($isR18Room, $searchIncludesR18)) {
            return false;
        }

        if (! (bool) config('ads.gam.enabled')) {
            return false;
        }

        $key = $side === 'right' ? 'rail_right' : 'rail_left';

        return $this->gamSlotPath($key) !== null;
    }

    public function exoclickConfigured(): bool
    {
        if (! (bool) config('ads.exoclick.enabled')) {
            return false;
        }

        return $this->exoclickZone('rail_left') !== null
            || $this->exoclickZone('rail_right') !== null
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

        $key = $side === 'right' ? 'rail_right' : 'rail_left';

        return $this->exoclickZone($key) !== null;
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

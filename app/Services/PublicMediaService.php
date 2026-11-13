<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * ルーム画像とリプライのメディアを公開する。
 * ファイル名は保存時に決まるので、中身は変わらない。
 * ローカル保存のあいだは /media から Cache-Control 付きで返し、
 * 公開ディスクが S3 互換（Cloudflare R2 を含む）になったら、その公開URLをそのまま使う。
 */
class PublicMediaService
{
    public const CACHE_CONTROL = 'public, max-age=2592000';

    private const PREFIXES = [
        'thread_images/',
        'response_media/',
    ];

    public static function usesObjectStorage(): bool
    {
        return config('filesystems.disks.public.driver') === 's3';
    }

    /**
     * アップロード時に渡すオプション。R2 ではオブジェクトの Cache-Control になる。
     *
     * @return array<string, string>
     */
    public static function writeOptions(): array
    {
        return [
            'disk' => 'public',
            'visibility' => 'public',
            'CacheControl' => self::CACHE_CONTROL,
        ];
    }

    public static function normalize(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    public static function isManaged(?string $path): bool
    {
        $path = self::normalize($path);
        if ($path === null) {
            return false;
        }
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function url(?string $path): ?string
    {
        $path = self::normalize($path);
        if ($path === null) {
            return null;
        }
        if (!self::isManaged($path)) {
            return Storage::disk('public')->url($path);
        }
        if (self::usesObjectStorage()) {
            return Storage::disk('public')->url($path);
        }

        return url('media/'.$path);
    }

    public static function localFullPath(string $path): ?string
    {
        $path = self::normalize($path);
        if ($path === null || !self::isManaged($path) || self::usesObjectStorage()) {
            return null;
        }

        $root = realpath(Storage::disk('public')->path(''));
        $full = Storage::disk('public')->path($path);
        $real = realpath($full);
        if ($root === false || $real === false || !is_file($real)) {
            return null;
        }
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (!str_starts_with($real, $rootPrefix)) {
            return null;
        }

        return $real;
    }
}

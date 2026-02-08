<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SecureFilePathService
{
    /**
     * 許可されたルートディレクトリ
     */
    private const ALLOWED_ROOTS = [
        'storage' => 'storage/app/public',
        'public' => 'public',
    ];

    /**
     * ファイルパスを正規化し、ルートディレクトリ外へのアクセスを防止
     *
     * @param string $path ユーザー入力または相対パス
     * @param string $rootType ルートタイプ（'storage' または 'public'）
     * @return string|null 正規化された絶対パス、またはnull（無効な場合）
     */
    public static function normalizePath(string $path, string $rootType = 'storage'): ?string
    {
        try {
            // ルートタイプの検証
            if (!isset(self::ALLOWED_ROOTS[$rootType])) {
                Log::warning('SecureFilePathService: Invalid root type', [
                    'root_type' => $rootType,
                    'path' => $path,
                ]);
                return null;
            }

            // ルートディレクトリの絶対パスを取得
            $rootPath = self::getRootPath($rootType);
            if (!$rootPath) {
                return null;
            }

            // 相対パスを絶対パスに変換
            // 相対パス（../など）を含む場合は、ルートディレクトリを基準に解決
            $normalizedPath = $rootPath . '/' . ltrim($path, '/');

            // realpath()で実態パスを取得（シンボリックリンクを解決）
            $realPath = realpath($normalizedPath);

            // realpath()が失敗した場合（ファイルが存在しない場合）
            if ($realPath === false) {
                // ディレクトリが存在するかチェック
                $parentDir = dirname($normalizedPath);
                $realParentDir = realpath($parentDir);
                
                if ($realParentDir === false) {
                    Log::warning('SecureFilePathService: Parent directory does not exist', [
                        'path' => $path,
                        'normalized_path' => $normalizedPath,
                        'root_type' => $rootType,
                    ]);
                    return null;
                }

                // 親ディレクトリがルート内にあることを確認
                if (!self::isPathWithinRoot($realParentDir, $rootPath)) {
                    Log::warning('SecureFilePathService: Parent directory outside root', [
                        'path' => $path,
                        'parent_dir' => $realParentDir,
                        'root_path' => $rootPath,
                    ]);
                    return null;
                }

                // ファイルが存在しない場合は、親ディレクトリ内のパスを返す
                $realPath = $realParentDir . '/' . basename($normalizedPath);
            }

            // ルートディレクトリ外へのアクセスをチェック
            if (!self::isPathWithinRoot($realPath, $rootPath)) {
                Log::warning('SecureFilePathService: Path outside root directory', [
                    'path' => $path,
                    'real_path' => $realPath,
                    'root_path' => $rootPath,
                    'root_type' => $rootType,
                ]);
                return null;
            }

            return $realPath;

        } catch (\Exception $e) {
            Log::error('SecureFilePathService: Exception during path normalization', [
                'path' => $path,
                'root_type' => $rootType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * ルートディレクトリの絶対パスを取得
     *
     * @param string $rootType
     * @return string|null
     */
    private static function getRootPath(string $rootType): ?string
    {
        $relativeRoot = self::ALLOWED_ROOTS[$rootType] ?? null;
        if (!$relativeRoot) {
            return null;
        }

        // base_path()を使用してプロジェクトルートからの絶対パスを取得
        $rootPath = base_path($relativeRoot);
        
        // realpath()で正規化
        $realRootPath = realpath($rootPath);
        
        if ($realRootPath === false) {
            Log::warning('SecureFilePathService: Root directory does not exist', [
                'root_type' => $rootType,
                'root_path' => $rootPath,
            ]);
            return null;
        }

        return $realRootPath;
    }

    /**
     * パスがルートディレクトリ内にあるかチェック
     *
     * @param string $path
     * @param string $rootPath
     * @return bool
     */
    private static function isPathWithinRoot(string $path, string $rootPath): bool
    {
        // パスを正規化（末尾のスラッシュを統一）
        $normalizedPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $normalizedRoot = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // パスがルートパスで始まるかチェック
        return str_starts_with($normalizedPath, $normalizedRoot);
    }

    /**
     * ユーザー入力から安全なファイル名を生成
     * （既に実装されているが、このサービスでも提供）
     *
     * @param string $userInput
     * @param string $extension
     * @return string
     */
    public static function generateSafeFilename(string $userInput, string $extension = ''): string
    {
        // ユーザー入力をハッシュ化
        $hashed = hash('sha256', time() . $userInput . uniqid());
        
        // 拡張子を追加
        if ($extension) {
            // 拡張子から危険な文字を除去
            $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
            return $hashed . '.' . $extension;
        }

        return $hashed;
    }

    /**
     * ファイルパスから安全なファイル名を抽出
     *
     * @param string $path
     * @return string
     */
    public static function getSafeBasename(string $path): string
    {
        // パス区切り文字を除去してファイル名のみを取得
        $basename = basename($path);
        
        // 危険な文字を除去
        $basename = preg_replace('/[^a-zA-Z0-9._-]/', '', $basename);
        
        return $basename;
    }
}

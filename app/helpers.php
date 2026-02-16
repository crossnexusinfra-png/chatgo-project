<?php

if (!function_exists('linkify_urls')) {
    /**
     * テキスト内のURLをクリック可能なリンクに変換
     *
     * @param string $text
     * @return string
     */
    function linkify_urls(string $text): string
    {
        // HTMLエスケープ + 改行を <br> に変換（表示で改行を保持）
        $text = nl2br(e($text), false);
        
        // URLパターン（http/httpsで始まるURL）
        $pattern = '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i';
        
        // URLをリンクに変換
        $text = preg_replace_callback($pattern, function ($matches) {
            $url = $matches[1];
            // 末尾の句読点や括弧を除外
            $url = rtrim($url, '.,;:!?)');
            $displayUrl = $url;
            
            // 表示用URLを短縮（長すぎる場合）
            if (mb_strlen($displayUrl) > 50) {
                $displayUrl = mb_substr($displayUrl, 0, 47) . '...';
            }
            
            return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" class="response-url-link">' . e($displayUrl) . '</a>';
        }, $text);
        
        return $text;
    }
}


<?php

namespace App\Services;

class ArticleService
{
    /**
     * @return list<array{slug: string, category: string, published_at: ?string, title: string, summary: string, body: string}>
     */
    public static function all(?string $language = null): array
    {
        $language = $language ?? LanguageService::getCurrentLanguage();
        $items = config('articles.items', []);
        $articles = [];

        foreach ($items as $item) {
            $article = self::hydrateItem($item, $language);
            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * @return list<array{category: string, title: string, articles: list<array{slug: string, category: string, published_at: ?string, title: string, summary: string, body: string}>}>
     */
    public static function groupedByCategory(?string $language = null): array
    {
        $language = $language ?? LanguageService::getCurrentLanguage();
        $categoryOrder = config('articles.categories', []);
        $grouped = [];

        foreach ($categoryOrder as $category) {
            $category = (string) $category;
            if ($category === '') {
                continue;
            }

            $titleKey = 'articles_category_'.str_replace('-', '_', $category);
            $title = LanguageService::trans($titleKey, $language);
            if ($title === $titleKey) {
                $title = $category;
            }

            $grouped[$category] = [
                'category' => $category,
                'title' => $title,
                'articles' => [],
            ];
        }

        foreach (self::all($language) as $article) {
            $category = $article['category'];
            if (!isset($grouped[$category])) {
                $titleKey = 'articles_category_'.str_replace('-', '_', $category);
                $title = LanguageService::trans($titleKey, $language);
                $grouped[$category] = [
                    'category' => $category,
                    'title' => $title === $titleKey ? $category : $title,
                    'articles' => [],
                ];
            }
            $grouped[$category]['articles'][] = $article;
        }

        return array_values(array_filter(
            $grouped,
            static fn (array $group) => count($group['articles']) > 0
        ));
    }

    /**
     * @return array{slug: string, category: string, published_at: ?string, title: string, summary: string, body: string}|null
     */
    public static function find(string $slug, ?string $language = null): ?array
    {
        foreach (self::all($language) as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{slug: string, category: string, published_at: ?string, title: string, summary: string, body: string}|null
     */
    private static function hydrateItem(array $item, string $language): ?array
    {
        $slug = (string) ($item['slug'] ?? '');
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        $title = LanguageService::trans('article_'.$slug.'_title', $language);
        if ($title === 'article_'.$slug.'_title' || trim($title) === '') {
            return null;
        }

        $summary = LanguageService::trans('article_'.$slug.'_summary', $language);
        if ($summary === 'article_'.$slug.'_summary') {
            $summary = '';
        }

        $body = LanguageService::trans('article_'.$slug.'_body', $language);
        if ($body === 'article_'.$slug.'_body') {
            $body = '';
        }

        return [
            'slug' => $slug,
            'category' => (string) ($item['category'] ?? 'other'),
            'published_at' => isset($item['published_at']) ? (string) $item['published_at'] : null,
            'title' => $title,
            'summary' => $summary,
            'body' => $body,
            'sections' => self::parseBody($body),
        ];
    }

    /**
     * 本文を intro / 見出しセクション / 段落 / リスト に分解する。
     * 「## 見出し」でセクション分割、行頭の ・•- はリスト項目。
     *
     * @return list<array{title: ?string, blocks: list<array{type: string, text?: string, items?: list<string>}>}>
     */
    public static function parseBody(string $body): array
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            return [];
        }

        $rawSections = preg_split('/^##\s+/m', $body) ?: [];
        $sections = [];

        foreach ($rawSections as $index => $rawSection) {
            $rawSection = trim($rawSection);
            if ($rawSection === '') {
                continue;
            }

            $title = null;
            $content = $rawSection;
            if ($index > 0) {
                $parts = preg_split('/\n/', $rawSection, 2) ?: [];
                $title = trim((string) ($parts[0] ?? ''));
                $content = trim((string) ($parts[1] ?? ''));
                if ($title === '') {
                    $title = null;
                }
            }

            $sections[] = [
                'title' => $title,
                'blocks' => self::parseBlocks($content),
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{type: string, text?: string, items?: list<string>}>
     */
    private static function parseBlocks(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $lines = preg_split('/\n/', $content) ?: [];
        $blocks = [];
        $listItems = [];

        $flushList = static function () use (&$blocks, &$listItems): void {
            if ($listItems === []) {
                return;
            }
            $blocks[] = [
                'type' => 'list',
                'items' => $listItems,
            ];
            $listItems = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $flushList();
                continue;
            }

            if (preg_match('/^[・•\-]\s*(.+)$/u', $line, $matches) === 1) {
                $listItems[] = trim($matches[1]);
                continue;
            }

            $flushList();
            $blocks[] = [
                'type' => 'paragraph',
                'text' => $line,
            ];
        }

        $flushList();

        return $blocks;
    }
}

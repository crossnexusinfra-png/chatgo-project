<?php

namespace App\Http\Controllers;

use App\Services\ArticleService;
use App\Services\LanguageService;

class ArticleController extends Controller
{
    public function index()
    {
        $lang = LanguageService::getCurrentLanguage();
        $articleGroups = ArticleService::groupedByCategory($lang);

        return view('legal.articles.index', [
            'lang' => $lang,
            'articleGroups' => $articleGroups,
        ]);
    }

    public function show(string $slug)
    {
        $lang = LanguageService::getCurrentLanguage();
        $article = ArticleService::find($slug, $lang);

        if ($article === null) {
            abort(404);
        }

        return view('legal.articles.show', [
            'lang' => $lang,
            'article' => $article,
        ]);
    }
}

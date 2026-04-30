<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>運営者情報 | Chatgo</title>
    @include('layouts.favicon')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
</head>
<body>
    <main class="main-content" style="max-width: 840px; margin: 0 auto; padding: 24px;">
        <h1>運営者情報</h1>
        <p>サービス名: Chatgo</p>
        <p>運営者: CrossNexus</p>
        <p>連絡先: crossnexus.support@gmail.com</p>
    </main>
    <footer class="site-footer legal-footer">
        <a href="{{ route('auth.terms') }}">利用規約</a>
        <span> | </span>
        <a href="{{ route('legal.privacy') }}">プライバシーポリシー</a>
        <span> | </span>
        <a href="{{ route('legal.company') }}">運営者情報</a>
        <span> | </span>
        <a href="{{ route('legal.contact') }}">お問い合わせ</a>
    </footer>
</body>
</html>

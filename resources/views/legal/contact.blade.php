<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ | Chatgo</title>
    @include('layouts.favicon')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
</head>
<body>
    <main class="main-content" style="max-width: 840px; margin: 0 auto; padding: 24px;">
        <h1>お問い合わせ</h1>
        <p>サービスに関するご意見・ご質問は、以下までご連絡ください。</p>
        <p>メール: crossnexus.support@gmail.com</p>
        <p>内容確認後、順次ご返信いたします。</p>
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

# セキュリティ実装ドキュメント

## 実装済みセキュリティ対策

### 1. CSP (Content Security Policy)
- **実装方法**: nonceベースのCSP
- **設定ファイル**: `config/csp.php`
- **ミドルウェア**: `app/Http/Middleware/CspMiddleware.php`
- **特徴**:
  - `unsafe-inline`を削除し、nonceを使用
  - すべてのscriptタグとstyleタグにnonce属性を追加
  - リクエストごとに新しいnonceを生成

### 2. CORS (Cross-Origin Resource Sharing)
- **設定ファイル**: `config/cors.php`
- **設定内容**:
  - `FRONTEND_URL`環境変数でフロントエンドURLを指定
  - 許可メソッド: GET, POST, PUT, DELETE
  - `max_age`: 86400秒（1日）- パフォーマンスとセキュリティのバランスを考慮

### 3. HSTS / TLS強制
- **実装方法**: Cloudflareで実装
- **Cloudflare設定**:
  - HTTP Strict Transport Security (HSTS) を有効化
  - Always Use HTTPS を有効化
  - Automatic HTTPS Rewrites を有効化
- **注意**: 本番環境でのみ有効化すること

## 環境変数設定

`.env`ファイルに以下の設定を追加（既存項目がある場合は置き換え）：

```env
# CSP設定
CSP_ENABLED=true
CSP_REPORT_ONLY=false
CSP_REPORT_URI=

# CORS設定
FRONTEND_URL=https://your-frontend-domain.com
CORS_MAX_AGE=86400

# アプリケーションURL（バックエンド）
APP_URL=https://your-backend-domain.com
```

## Cloudflare設定確認事項

本番環境で以下の設定が有効になっているか確認してください：

1. **HTTP Strict Transport Security (HSTS)**
   - SSL/TLS → Edge Certificates → HTTP Strict Transport Security (HSTS)
   - Status: ON
   - Max Age: 31536000（1年）以上推奨
   - Include Subdomains: 必要に応じて有効化
   - Preload: 必要に応じて有効化

2. **Always Use HTTPS**
   - SSL/TLS → Edge Certificates → Always Use HTTPS
   - Status: ON

3. **Automatic HTTPS Rewrites**
   - SSL/TLS → Edge Certificates → Automatic HTTPS Rewrites
   - Status: ON

## インラインJSの移行について

nonceを使用することで、viewファイルから動かすことのできないインラインscriptタグも安全に実行できます。そのため、**残りのインラインJSの移行は必要ありません**。

ただし、将来的にメンテナンス性を向上させるため、可能な限り外部JSファイルへの移行を推奨します。


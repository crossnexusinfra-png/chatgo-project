# Cloudflare設定チェックリスト

## 確認方法

Cloudflareダッシュボードにログインして、以下の設定を確認してください。

## 1. HTTP Strict Transport Security (HSTS)

**設定場所**: SSL/TLS → Edge Certificates → HTTP Strict Transport Security (HSTS)

### 確認項目
- [ ] **Status**: ON（有効になっている）
- [ ] **Max Age**: 31536000（1年）以上に設定されている
- [ ] **Include Subdomains**: 必要に応じて有効化（サブドメインも含める場合）
- [ ] **Preload**: 必要に応じて有効化（HSTS Preload Listに登録する場合）

### 設定手順（未設定の場合）
1. Cloudflareダッシュボードにログイン
2. 対象のドメインを選択
3. 左メニューから「SSL/TLS」を選択
4. 「Edge Certificates」タブを選択
5. 「HTTP Strict Transport Security (HSTS)」セクションまでスクロール
6. 「Enable HSTS」をクリック
7. 設定を保存

## 2. Always Use HTTPS

**設定場所**: SSL/TLS → Edge Certificates → Always Use HTTPS

### 確認項目
- [ ] **Status**: ON（有効になっている）

### 設定手順（未設定の場合）
1. Cloudflareダッシュボードにログイン
2. 対象のドメインを選択
3. 左メニューから「SSL/TLS」を選択
4. 「Edge Certificates」タブを選択
5. 「Always Use HTTPS」セクションまでスクロール
6. 「Always Use HTTPS」をONにする
7. 設定を保存

## 3. Automatic HTTPS Rewrites

**設定場所**: SSL/TLS → Edge Certificates → Automatic HTTPS Rewrites

### 確認項目
- [ ] **Status**: ON（有効になっている）

### 設定手順（未設定の場合）
1. Cloudflareダッシュボードにログイン
2. 対象のドメインを選択
3. 左メニューから「SSL/TLS」を選択
4. 「Edge Certificates」タブを選択
5. 「Automatic HTTPS Rewrites」セクションまでスクロール
6. 「Automatic HTTPS Rewrites」をONにする
7. 設定を保存

## 動作確認方法

### 1. HSTSヘッダーの確認
ブラウザの開発者ツール（F12）で、Networkタブを開き、任意のリクエストを選択して、Response Headersに以下が含まれているか確認：

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### 2. HTTPSリダイレクトの確認
HTTPでアクセスした場合、自動的にHTTPSにリダイレクトされるか確認：
- `http://your-domain.com` → `https://your-domain.com` にリダイレクトされる

### 3. HTTPSリライトの確認
ページ内のHTTPリンクが自動的にHTTPSに書き換えられているか確認：
- ブラウザの開発者ツールで、HTMLソースを確認
- HTTPのリンクがHTTPSに書き換えられている

## 注意事項

- これらの設定は**本番環境でのみ有効化**してください
- 開発環境で有効化すると、ローカル開発に影響が出る可能性があります
- HSTSのMax Ageを長く設定すると、設定変更の反映に時間がかかります
- Preloadを有効化する場合は、[HSTS Preload List](https://hstspreload.org/)への登録も検討してください

## トラブルシューティング

### HSTSヘッダーが表示されない場合
1. Cloudflareの設定が正しく保存されているか確認
2. ブラウザのキャッシュをクリア
3. プライベートブラウジングモードで確認
4. Cloudflareのキャッシュをパージ（必要に応じて）

### HTTPSリダイレクトが動作しない場合
1. 「Always Use HTTPS」がONになっているか確認
2. SSL/TLSモードが「Full」または「Full (strict)」になっているか確認
3. ページルールでHTTP→HTTPSリダイレクトが設定されていないか確認（重複設定の可能性）


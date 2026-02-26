# 413 Request Entity Too Large の原因切り分け

アップロード時に 413 が出た場合、**どの層の上限**で弾かれているかを次の手順で確認できます。

---

## 1. PHP の上限を確認する

**条件**: `.env` で `APP_DEBUG=true` のときだけ有効です。

ブラウザで次のURLを開きます（実際のドメインに置き換えてください）。

```
https://あなたのドメイン/upload-limits
```

表示される内容の例:

- `upload_max_filesize` … 1ファイルあたりの上限（例: 2M, 5M）
- `post_max_size` … リクエスト全体の上限（例: 8M）

音声は最大 5MB まで許可しているため、どちらも **5M 以上**（余裕を見て 10M 推奨）必要です。

- ここで表示された値が 5M 未満 → **PHP の設定が原因**の可能性が高いです。
- ここに**正常にアクセスできる**（413 にならない）場合、少なくとも **GET は Nginx を通過して PHP まで届いている**ことになります。

---

## 2. 413 が「Nginx で弾かれているか」を確認する

- **Nginx のエラーログ**を確認する  
  413 が発生したタイミングで、Nginx のログに 413 や `client intended to send too large body` のようなメッセージが出ていれば、**Nginx の `client_max_body_size` が原因**です。

  ```bash
  # 例: エラーログの場所
  sudo tail -f /var/log/nginx/error.log
  ```

- **レスポンスヘッダー**を確認する  
  ブラウザの開発者ツール（F12）→ ネットワーク → 413 になったリクエストを選択 → ヘッダーで `Server: nginx` などが出ていれば、そのサーバーが 413 を返しています（Nginx の可能性が高い）。

- **Nginx の設定を一時的に緩める**  
  `client_max_body_size 50M;` などに大きくして `nginx -t` → `reload` したあと、再度アップロードして 413 が消えれば、**原因は Nginx の上限**だったと判断できます。

---

## 3. 判定の目安

| 状況 | 想定される原因 |
|------|----------------|
| `/upload-limits` が 413 や 502 で開けない | 手元の環境では PHP まで届いていない可能性（例: 別のプロキシやロードバランサで 413）。 |
| `/upload-limits` は開けるが、`upload_max_filesize` や `post_max_size` が 5M 未満 | **PHP の上限**が原因。`php.ini` や `user.ini` で 5M 以上に変更。 |
| PHP の値は 5M 以上なのにアップロードで 413 | **Nginx（または前段のプロキシ）の上限**の可能性が高い。`client_max_body_size` などを 10M 程度に増やす。 |
| Nginx を増やしても 413 | Apache の `LimitRequestBody` や、Cloudflare など前段のプロキシの制限を確認。 |

---

## 4. 本番で `/upload-limits` を出したくない場合

`APP_DEBUG=false` にすると `/upload-limits` は登録されません。  
切り分けが終わったら、本番では `APP_DEBUG=false` のままにしておけばこのURLは使えません。

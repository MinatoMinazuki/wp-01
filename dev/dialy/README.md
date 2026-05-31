# Diary

`dev/dialy/` は、PHP + jQuery で作られた日記アプリです。

日付ごとの投稿、画像アップロード、タグ、検索、日付移動、自動ログイン、スマホ向けのタイムラインUIに対応しています。

## ディレクトリ構成

```text
dialy/
  api/              JSON API
  class/            DB接続・設定クラス
  css/              スタイル
  db/               任意適用のDB改善SQL
  includes/         共通ヘルパー
  js/               フロントエンド処理
  uploads/          アップロード画像
  index.php         日記メイン画面
  login.php         ログイン画面
  register.php      新規登録画面
  logout.php        ログアウト処理
```

## 必要な環境

- PHP 8.x
- MySQL または MariaDB
- PDO MySQL
- 任意: Imagick  
  画像のリサイズ・圧縮に使います。
- 任意: `../php-heic-to-jpg-maestro`  
  HEIC画像をJPGに変換するために使います。

## セットアップ

1. `class/config.php` にDB接続情報を設定します。
2. `uploads/` に書き込み権限があることを確認します。
3. 必要なテーブルを作成、または既存DBをインポートします。
   - `users`
   - `login_tokens`
   - `tweets`
   - `tags`
   - `tweet_tags`
4. XAMPPから以下のURLを開きます。

```text
http://localhost/wp/files/dev/dialy/
```

## 任意のDB改善SQL

以下のSQLは、タグのユーザー別管理と検索向けインデックスを追加します。

```text
db/20260530_improvements.sql
```

適用前にDBのバックアップを取ってください。

アプリ側は後方互換になっているため、`tags.user_id` を追加していないDBでも動作します。

## 主な機能

- ログイン・新規登録
- 自動ログイントークン
- POST APIのCSRF対策
- 日付ごとのタイムライン表示
- 前日・翌日への移動
- 今日へ戻るボタン
- キーワード・タグ検索
- 検索中ステータス表示
- 画像アップロード
- 画像プレビュー
- HEIC画像のJPG変換
- タグのチップ入力
- 既存タグの候補表示
- 投稿メニュー
  - 本文コピー
  - 画像を開く
  - タグ入力へ移動
  - 削除
- 投稿の論理削除
- 投稿削除時のアップロード画像削除

## APIの書き方

APIファイルでは、共通初期化として以下を読み込みます。

```php
require_once __DIR__ . '/bootstrap.php';
```

JSONレスポンスは `json_response()` を使います。

```php
json_response(['success' => true]);
```

POST APIでは、原則として以下を呼びます。

```php
require_post();
require_csrf();
```

## セキュリティ関連

- DB接続エラーの詳細は画面に表示せず、ログに出します。
- 自動ログインCookieには以下を設定しています。
  - `HttpOnly`
  - `SameSite=Lax`
  - HTTPS時のみ `Secure`
- アップロード画像のファイル名はランダム化しています。
- `tags.user_id` が存在する場合は、タグをユーザー別に扱います。

## DBアクセスについて

古い互換メソッドとして、以下はまだ残しています。

- `Dsql()`
- `select()`

新しいコードでは、以下を使ってください。

- `fetchAll()`
- `fetchOne()`
- `execute()`
- `insert()`

## 既知の注意点

- `class/config.php` はGit管理外です。DB接続情報やAPIキーなどはここに置きます。
- Imagickのバージョン警告が出る場合がありますが、アプリのロジックではなくローカルPHP環境側の問題です。
- HEIC変換は `../php-heic-to-jpg-maestro` が存在し、変換バイナリが実行できる場合に動作します。

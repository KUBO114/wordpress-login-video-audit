# Login Video Audit - WordPress Plugin

## 概要

ログイン時に 1〜3 秒の顔動画を取得して、管理者のみが閲覧可能な形で保存する WordPress プラグインです。セキュリティ監査目的での利用を想定しています。

## バージョン

**Current Version: 0.1.0**

## 機能

- ログイン画面でカメラアクセスを要求
- 1.5 秒の短い動画を自動録画（WebM 形式）
- 動画を非公開ディレクトリに保存（直アクセス不可）
- 管理画面で録画一覧を表示
- 管理者のみが動画を再生・ダウンロード可能
- ユーザー名、IP、User-Agent を記録

## システム要件

- WordPress 5.0 以上
- PHP 7.4 以上
- モダンブラウザ（MediaRecorder API 対応）

## インストール

### 手動インストール

1. このプラグインフォルダを `/wp-content/plugins/` にアップロード
2. WordPress の管理画面でプラグインを有効化

### GitHub Updater を使用した自動更新

1. [GitHub Updater](https://github.com/afragen/github-updater) プラグインをインストール
2. 管理画面の「GitHub Updater」メニューから「Login Video Audit」を追加
3. 自動更新が有効になります

## セキュリティ設定

- 動画は `/wp-content/uploads/login-videos/` に保存
- `.htaccess` で直アクセスを遮断
- 管理者権限（`manage_options`）がないとアクセス不可
- Nonce によるリクエスト検証

## 開発者向け情報

### バージョン管理

このプラグインは Git を使用してバージョン管理されています。

#### 新しいバージョンをリリースする手順

1. `login-video-audit.php` のバージョン番号を更新
2. `CHANGELOG.md` に変更内容を記載
3. 変更をコミット
4. Git タグを作成してプッシュ

```bash
# バージョン0.1.0の例
git add .
git commit -m "Release version 0.1.0"
git tag -a v0.1.0 -m "Version 0.1.0 - Initial release"
git push origin main --tags
```

### ディレクトリ構造

```
login-video-audit/
├── login-video-audit.php  # メインプラグインファイル
├── login-video.js         # フロントエンド録画スクリプト
├── README.md             # このファイル
├── CHANGELOG.md          # 変更履歴
└── .gitignore            # Git除外設定
```

## 注意事項

- カメラアクセス許可が必要
- プライバシーポリシーに録画の旨を明記すること
- GDPR 等の法規制に準拠した運用が必要
- 動画ファイルは定期的に削除することを推奨

## ライセンス

GPLv2 or later

## 変更履歴

詳細は [CHANGELOG.md](CHANGELOG.md) を参照してください。

## サポート

- Repository: https://github.com/KUBO114/wordpress-login-video-audit
- Issues: https://github.com/KUBO114/wordpress-login-video-audit/issues

# Login Video Audit - WordPress Plugin

## 概要
ログイン時に1〜3秒の顔動画を取得して、管理者のみが閲覧可能な形で保存するWordPressプラグインです。セキュリティ監査目的での利用を想定しています。

## バージョン
**Current Version: 0.1.0**

## 機能
- ログイン画面でカメラアクセスを要求
- 1.5秒の短い動画を自動録画（WebM形式）
- 動画を非公開ディレクトリに保存（直アクセス不可）
- 管理画面で録画一覧を表示
- 管理者のみが動画を再生・ダウンロード可能
- ユーザー名、IP、User-Agentを記録

## システム要件
- WordPress 5.0以上
- PHP 7.4以上
- モダンブラウザ（MediaRecorder API対応）

## インストール
1. このプラグインフォルダを `/wp-content/plugins/` にアップロード
2. WordPressの管理画面でプラグインを有効化

## セキュリティ設定
- 動画は `/wp-content/uploads/login-videos/` に保存
- `.htaccess` で直アクセスを遮断
- 管理者権限（`manage_options`）がないとアクセス不可
- Nonceによるリクエスト検証

## 開発者向け情報

### バージョン管理
このプラグインはGitを使用してバージョン管理されています。

#### 新しいバージョンをリリースする手順
1. `login-video-audit.php` のバージョン番号を更新
2. `CHANGELOG.md` に変更内容を記載
3. 変更をコミット
4. Gitタグを作成してプッシュ

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
- GDPR等の法規制に準拠した運用が必要
- 動画ファイルは定期的に削除することを推奨

## ライセンス
GPLv2 or later

## 変更履歴
詳細は [CHANGELOG.md](CHANGELOG.md) を参照してください。

## サポート
- Repository: https://github.com/KUBO114/wordpress-login-video-audit
- Issues: https://github.com/KUBO114/wordpress-login-video-audit/issues

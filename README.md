# ログイン動画監査 (Login Video Audit)

セキュリティ監査のためにログイン時に短い動画クリップをキャプチャするWordPressプラグインです。

## 説明

ログイン動画監査は、ユーザーがログインする際に1〜2秒の短い動画クリップを記録することで、WordPressサイトのセキュリティを強化します。これらの動画は安全に保存され、サイト管理者のみがアクセスでき、セキュリティ監視とインシデント調査のための貴重な監査証跡を提供します。

## 機能

- **自動動画キャプチャ**: ログイン時に1〜2秒の動画を記録
- **安全な保存**: .htaccess制限付きの保護されたディレクトリに動画を保存
- **管理者専用アクセス**: 管理者のみが録画動画を閲覧可能
- **非侵襲的**: 動画キャプチャ失敗でもログインは妨げられない
- **プライバシー重視**: カメラアクセス前にユーザーに通知
- **整理された保存**: 日付（年/月）による自動ファイル整理
- **軽量**: ログインパフォーマンスへの影響が最小限

## 要件

- WordPress 5.0以上
- PHP 7.4以上
- MediaRecorder APIをサポートする最新ブラウザ（Chrome、Firefox、Edge、Safari 14.1+）
- WordPressアップロードディレクトリへの書き込み権限

## インストール

### WordPress管理画面から

1. プラグインのZIPファイルをダウンロード
2. WordPress管理画面で**プラグイン > 新規追加**に移動
3. **プラグインのアップロード**をクリックしてZIPファイルを選択
4. **今すぐインストール**をクリックし、次に**有効化**

### 手動インストール

1. `login-video-audit`フォルダを`/wp-content/plugins/`にアップロード
2. WordPressの**プラグイン**メニューからプラグインを有効化
3. 設定不要 - プラグインは自動的に動作します

## 使用方法

### 録画された動画の閲覧

1. WordPress管理画面にログイン
2. 管理メニューの**ログイン動画**に移動
3. 録画されたログイン試行のリストを表示
4. **再生/ダウンロード**をクリックして特定の動画を表示

### エンドユーザー向け

ログイン時、ユーザーにはセキュリティ目的でカメラアクセスが要求される旨の簡単な通知が表示されます。動画キャプチャの成否に関わらず、ログインプロセスは通常通り続行されます。

## プライバシーとコンプライアンス

### ユーザー通知

プラグインはログイン画面に動画がキャプチャされることをユーザーに通知する通知を表示します。これはプライバシー規制下での透明性要件を満たすのに役立ちます。

### データ保存

- 動画はサーバーにローカルで保存されます
- データは外部サービスに送信されません
- ファイルは直接のWebアクセスから保護されています
- 管理者のみが動画を閲覧できます

### コンプライアンス推奨事項

1. **プライバシーポリシーの更新**: ログイン時の動画キャプチャに関する開示を追加
2. **法的レビュー**: 地域のプライバシー法に関して法律顧問に相談
3. **データ保持**: 適切な保持と削除ポリシーを実装
4. **ユーザー同意**: 法律で義務付けられている場合は、明示的な同意メカニズムの追加を検討
5. **アクセスログ**: 録画された動画にアクセスしているユーザーを監視

## 設定

### 録画時間の調整

`login-video-audit.php`を編集し、定数を変更します：

```php
define( 'LVA_SEC', 1.5 ); // 希望の秒数に変更（例：2.0）
```

### ファイルサイズ制限の調整

```php
define( 'LVA_MAX_BYTES', 2000000 ); // 希望のバイト数に変更
```

### 保存ディレクトリの変更

```php
define( 'LVA_DIR', 'login-videos' ); // ディレクトリ名を変更
```

## 技術詳細

### ファイル形式

- **コンテナ**: WebM
- **ビデオコーデック**: VP8
- **一般的なサイズ**: 動画1つあたり100〜500KB
- **最大サイズ**: 2MB（設定可能）

### 保存構造

```
/wp-content/uploads/login-videos/
├── 2024/
│   ├── 01/
│   │   ├── .htaccess
│   │   ├── lva_20240115_143022_abc123.webm
│   │   └── lva_20240115_150033_def456.webm
│   └── 02/
│       ├── .htaccess
│       └── ...
```

### セキュリティ対策

1. **ディレクトリ保護**: .htaccessファイルが直接のWebアクセスを防止
2. **Nonce検証**: すべてのAJAXリクエストでWordPress nonceを使用
3. **権限チェック**: 動画アクセスには`manage_options`権限が必要
4. **入力サニタイゼーション**: すべてのユーザー入力がサニタイズされる
5. **ファイル検証**: アップロードサイズとタイプが検証される

## トラブルシューティング

### 動画が録画されない

- ブラウザコンソールでJavaScriptエラーを確認
- ブラウザがMediaRecorder APIをサポートしていることを確認
- カメラ許可が付与されていることを確認
- アップロードディレクトリへのサーバー書き込み権限を確認

### 動画を表示できない

- 管理者としてログインしていることを確認
- 動画ファイルのファイル権限を確認
- .htaccessが管理者アクセスをブロックしていないことを確認

### ファイルサイズが大きい

- 録画時間を短縮（LVA_SEC定数）
- 最大ファイルサイズ制限を下げる
- カメラ解像度設定を検討

## 開発

### ファイル構造

```
login-video-audit/
├── login-video-audit.php  # Main plugin file
├── login-video.js          # Frontend JavaScript
├── readme.txt              # WordPress.org readme
├── README.md               # This file
├── uninstall.php           # Uninstall cleanup
└── LICENSE                 # GPL v2 license
```

### フックとフィルター

プラグインは標準のWordPressフックを使用します：

- `login_enqueue_scripts` - Enqueue JavaScript on login page
- `login_message` - Display notice to users
- `wp_ajax_nopriv_lva_upload` - Handle video uploads
- `init` - Register custom post type
- `manage_lva_log_posts_columns` - Customize admin columns
- `admin_post_lva_dl` - Handle video downloads

## 貢献

貢献を歓迎します！以下の手順をお願いします：

1. リポジトリをフォーク
2. 機能ブランチを作成
3. 変更を加える
4. プルリクエストを送信

## サポート

バグレポートと機能リクエストについては、GitHubでissueを開いてください：
https://github.com/yourusername/login-video-audit

## ライセンス

このプラグインはGPL v2以降のライセンスで提供されています。

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## クレジット

開発者：[あなたの名前]

## 変更履歴

### 1.0.0 - 2024-01-15

- 初回リリース
- ログイン時の動画キャプチャ
- 安全な保存の実装
- 動画閲覧用の管理インターフェース
- プライバシー通知とユーザー通知
- 自動ファイル整理
- アンインストールクリーンアップ

## ロードマップ

今後の機能候補：

- [ ] 設定用の設定ページ
- [ ] 動画保持ポリシー
- [ ] ログイン失敗時のメール通知
- [ ] セキュリティプラグインとの統合
- [ ] エクスポート機能
- [ ] 高度なフィルタリングと検索
- [ ] マルチサイト対応
- [ ] 多言語対応

## FAQ

**Q: これはログインページを遅くしますか？**  
A: いいえ。動画キャプチャは非同期で行われ、ログインプロセスをブロックしません。

**Q: ユーザーがカメラを持っていない場合は？**  
A: ログインは通常通り進行します。動画キャプチャはオプションで非ブロッキングです。

**Q: 特定のユーザーの動画キャプチャを無効化できますか？**  
A: 現在はできませんが、この機能は将来のバージョンで追加される可能性があります。

**Q: 動画はどのくらい保持する必要がありますか？**  
A: これはセキュリティとコンプライアンス要件によります。保持ポリシーの実装を検討してください。

**Q: これはGDPRに準拠していますか？**  
A: プラグインはコンプライアンスのためのツールを提供しますが、あなたの実装があなたの管轄地域の法的要件を満たすことを確認する必要があります。

## 謝辞

- 優れたドキュメントを提供するWordPressコミュニティ
- MediaRecorder API仕様への貢献者
- 監査証跡の重要性を強調するセキュリティ研究者

# WordPress Login Video Audit Plugin

WordPressのログイン時に顔動画を取得して保存する、シンプルなセキュリティ監査プラグインです。

## 🎯 概要

このプラグインは、ログイン試行時にユーザーのカメラから約1.5秒の動画を取得し、セキュリティ監査目的で保存します。不正アクセスの追跡や、セキュリティインシデントの調査に役立ちます。

## ✨ 特徴

- 🎥 **自動動画取得**: ログイン時に自動的に1.5秒の動画を録画
- 🔒 **セキュア**: .htaccessで動画ファイルへの直接アクセスを遮断
- 📝 **シンプルなログ**: テキストファイルでログを管理
- 🚀 **軽量**: 最小限のコード（PHP: 58行、JS: 40行）
- 🎯 **非侵襲的**: カメラ許可が拒否されてもログインは継続可能
- 💾 **容量制限**: 動画サイズを2MBに制限

## 📋 要件

- WordPress 5.0以上
- PHP 7.4以上
- モダンブラウザ（MediaRecorder API対応）
  - Chrome 47+
  - Firefox 25+
  - Safari 14.1+
  - Edge 79+

## 🚀 インストール

1. このリポジトリをWordPressの`wp-content/plugins/`ディレクトリにクローン：

```bash
cd wp-content/plugins/
git clone https://github.com/YOUR_USERNAME/wordpress-login-video-audit.git login-video-audit
```

2. WordPress管理画面の「プラグイン」メニューから「Login Video Audit」を有効化

## 📁 ファイル構成

```
login-video-audit/
├── login-video-audit.php  # メインプラグインファイル
├── login-video.js         # フロントエンド JavaScript
├── README.md              # このファイル
└── .gitignore
```

## 🔧 使い方

プラグインを有効化すると、自動的に以下の動作を行います：

1. ユーザーがログインフォームを送信
2. カメラへのアクセス許可を要求
3. 1.5秒間の動画を録画（WebM形式）
4. 動画を`/wp-content/uploads/login-videos/`に保存
5. ログ情報を`access.log`に記録
6. ログイン処理を継続

### ログの確認

動画とログファイルは以下の場所に保存されます：

```
/wp-content/uploads/login-videos/
├── .htaccess                          # アクセス制限
├── access.log                         # ログファイル
├── 20251004_114200_abc123.webm       # 動画ファイル
└── 20251004_114530_def456.webm
```

**ログファイルの形式：**
```
[2025-10-04 11:42:00] User: admin, IP: 192.168.1.1, File: 20251004_114200_abc123.webm
[2025-10-04 11:45:30] User: editor, IP: 192.168.1.2, File: 20251004_114530_def456.webm
```

### 動画ファイルへのアクセス

動画ファイルは`.htaccess`で保護されているため、直接URLでアクセスできません。サーバーに直接アクセスして確認してください：

```bash
# SSHでサーバーにアクセス
cd /path/to/wordpress/wp-content/uploads/login-videos/

# ログを確認
cat access.log

# 動画をダウンロード
scp user@server:/path/to/wordpress/wp-content/uploads/login-videos/*.webm ./
```

## ⚙️ カスタマイズ

### 録画時間の変更

`login-video.js`の35行目を編集：

```javascript
setTimeout(() => recorder.stop(), 1500); // 1500ms = 1.5秒
```

### 最大ファイルサイズの変更

`login-video-audit.php`の24行目を編集：

```php
if (!$video || $video['error'] !== UPLOAD_ERR_OK || $video['size'] > 2000000) {
```

## 🔒 セキュリティ

- 動画ファイルは`.htaccess`で保護され、直接アクセス不可
- nonceトークンによるCSRF対策
- ファイル名にランダム文字列を使用
- サニタイゼーション処理を実装

## ⚠️ 注意事項

- このプラグインはセキュリティ監査目的で設計されています
- ユーザーのプライバシーに配慮し、適切な通知と同意を得てください
- 地域のプライバシー法規（GDPR、個人情報保護法など）を遵守してください
- 動画ファイルは定期的に削除することを推奨します

## 📄 ライセンス

MIT License

## 🤝 コントリビューション

プルリクエストを歓迎します！大きな変更の場合は、まずissueを開いて変更内容を議論してください。

## 📝 変更履歴

### v0.2 (2025-10-04)
- シンプルな設計に変更
- カスタム投稿タイプを削除し、ログファイル方式に変更
- コード量を大幅削減

### v0.1 (2025-10-04)
- 初回リリース
- 基本的な動画取得・保存機能

## 👤 作者

Kubo Mikiya

## 🐛 バグ報告

バグを発見した場合は、[Issues](https://github.com/YOUR_USERNAME/wordpress-login-video-audit/issues)で報告してください。

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2025-01-27

### Added

- Face ID 風の顔認証ログイン機能を追加
- 顔登録機能（ユーザーが自分の顔を登録可能）
- 顔認証データベーステーブル（wp_lva_face_data）の作成
- 顔認証ログイン記録機能
- 管理画面での顔認証設定ページ
- 顔認証ボタンをログイン画面に追加
- 顔認証用の JavaScript（face-auth.js）を追加
- 顔検出と認証のフロントエンド処理
- 顔認証データの統計表示機能

### Changed

- ログインメッセージから「（約 1.5 秒）」の時間記載を削除
- GitHub Updater サポートを追加
- プラグインヘッダーに GitHub Updater 用の情報を追加

### Security

- 顔認証データの安全な保存
- 顔認証ログインの権限チェック強化
- 顔データの暗号化保存

## [0.1.1] - 2025-10-04

### Changed

- カメラ許可メッセージの文言を簡潔に修正
- ログインメッセージの表示テキストを短縮

### Fixed

- コードフォーマットの改善（インデント、スペースの統一）
- PHPDoc コメントの追加
- 文字列の連結方法を統一

## [0.1.0] - 2025-10-04

### Added

- 初期リリース
- ログイン時の顔認証動画録画機能
- 管理画面での動画一覧表示
- 動画の安全なダウンロード機能
- 非公開ディレクトリへの保存（.htaccess による保護）
- ユーザー情報（ユーザー名、IP、User-Agent）の記録
- カスタム投稿タイプ（lva_log）による管理
- セキュリティ対策（Nonce 検証、権限チェック）

### Security

- 管理者のみがアクセス可能
- 直リンク防止機能
- ファイルサイズ制限（最大 2MB）

---

## バージョニング規則

- **メジャーバージョン (X.0.0)**: 互換性のない大きな変更
- **マイナーバージョン (0.X.0)**: 後方互換性のある機能追加
- **パッチバージョン (0.0.X)**: 後方互換性のあるバグ修正

## 記載方法

### Added

新機能の追加

### Changed

既存機能の変更

### Deprecated

今後削除される予定の機能

### Removed

削除された機能

### Fixed

バグ修正

### Security

セキュリティ関連の修正

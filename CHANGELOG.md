# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2025-10-04

### Changed
- カメラ許可メッセージの文言を簡潔に修正
- ログインメッセージの表示テキストを短縮

### Fixed
- コードフォーマットの改善（インデント、スペースの統一）
- PHPDocコメントの追加
- 文字列の連結方法を統一

## [0.1.0] - 2025-10-04

### Added
- 初期リリース
- ログイン時の顔認証動画録画機能
- 管理画面での動画一覧表示
- 動画の安全なダウンロード機能
- 非公開ディレクトリへの保存（.htaccessによる保護）
- ユーザー情報（ユーザー名、IP、User-Agent）の記録
- カスタム投稿タイプ（lva_log）による管理
- セキュリティ対策（Nonce検証、権限チェック）

### Security
- 管理者のみがアクセス可能
- 直リンク防止機能
- ファイルサイズ制限（最大2MB）

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

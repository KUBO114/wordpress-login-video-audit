# バージョン管理ガイド

このドキュメントでは、Login Video Auditプラグインのバージョン管理方法について説明します。

## バージョン番号の規則

セマンティックバージョニング（Semantic Versioning）を採用しています：`MAJOR.MINOR.PATCH`

- **MAJOR**: 互換性のない大きな変更（例: 1.0.0 → 2.0.0）
- **MINOR**: 後方互換性のある機能追加（例: 1.0.0 → 1.1.0）
- **PATCH**: 後方互換性のあるバグ修正（例: 1.0.0 → 1.0.1）

## バージョンをリリースする手順

### 1. バージョン番号を更新

以下のファイルでバージョン番号を更新します：

#### `login-video-audit.php`
```php
/**
 * Plugin Name: Login Video Audit
 * Description: ログイン時に1〜3秒の顔動画を取得して管理者のみ閲覧可能に保存。
 * Version: 0.2.0  ← ここを更新
 */
```

#### JavaScript バージョン（同じファイル内）
```php
$ver = '0.2.0';  ← ここも更新
wp_enqueue_script('lva', plugin_dir_url(__FILE__).'login-video.js', [], $ver, true);
```

### 2. CHANGELOG.mdを更新

`CHANGELOG.md` に変更内容を記載します：

```markdown
## [0.2.0] - 2025-10-05

### Added
- 新しい機能の説明

### Fixed
- バグ修正の説明

### Changed
- 変更内容の説明
```

### 3. 変更をコミット

```bash
# すべての変更をステージング
git add .

# コミット（バージョン番号を含める）
git commit -m "Release version 0.2.0

- 新機能の追加
- バグ修正
- その他の変更"
```

### 4. Gitタグを作成

```bash
# アノテーション付きタグを作成（推奨）
git tag -a v0.2.0 -m "Version 0.2.0 - 変更の概要"

# タグの確認
git tag -l
```

### 5. GitHubにプッシュ

```bash
# 変更とタグをプッシュ
git push origin main
git push origin --tags

# または一度にプッシュ
git push origin main --tags
```

## バージョン履歴の確認

### すべてのタグを表示
```bash
git tag -l
```

### タグの詳細を表示
```bash
git show v0.2.0
```

### 特定のバージョンをチェックアウト
```bash
git checkout v0.1.0
```

### 最新バージョンに戻る
```bash
git checkout main
```

## ブランチ戦略

### main ブランチ
- 常に安定版を保持
- リリース可能な状態を維持

### 開発ブランチ（推奨）
新機能やバグ修正は別ブランチで開発：

```bash
# 新機能ブランチを作成
git checkout -b feature/new-feature

# 開発とコミット
git add .
git commit -m "Add new feature"

# mainにマージ
git checkout main
git merge feature/new-feature

# ブランチを削除
git branch -d feature/new-feature
```

## リリースチェックリスト

新しいバージョンをリリースする前に確認：

- [ ] `login-video-audit.php` のバージョン番号を更新
- [ ] JavaScript バージョン番号を更新
- [ ] `CHANGELOG.md` に変更内容を記載
- [ ] テストを実行（手動または自動）
- [ ] README.md の更新（必要に応じて）
- [ ] コミットメッセージが明確
- [ ] Gitタグを作成
- [ ] GitHubにプッシュ（タグを含む）
- [ ] GitHub Releasesページで公開（オプション）

## ロールバック方法

問題が発生した場合、以前のバージョンに戻す：

```bash
# 特定のタグに戻る
git checkout v0.1.0

# 新しいブランチとして作成
git checkout -b rollback-v0.1.0

# mainに適用
git checkout main
git reset --hard v0.1.0
git push -f origin main
```

⚠️ **注意**: `git push -f` は既存の履歴を上書きするため、慎重に使用してください。

## GitHub Releases の作成（オプション）

1. GitHubのリポジトリページにアクセス
2. 「Releases」タブをクリック
3. 「Create a new release」をクリック
4. タグを選択（例: v0.2.0）
5. リリースタイトルと説明を入力
6. プラグインのZIPファイルを添付（オプション）
7. 「Publish release」をクリック

## よくある質問

### Q: タグを間違えて作成した場合は？
```bash
# ローカルのタグを削除
git tag -d v0.2.0

# リモートのタグを削除
git push origin :refs/tags/v0.2.0

# 正しいタグを作成し直す
git tag -a v0.2.0 -m "正しいメッセージ"
git push origin v0.2.0
```

### Q: 複数の変更を一つのバージョンにまとめたい場合は？
開発中はコミットを頻繁に行い、リリース時にまとめてバージョン番号を更新・タグ付けします。

### Q: ホットフィックスのバージョニングは？
緊急のバグ修正はPATCHバージョンを上げます（例: 0.1.0 → 0.1.1）

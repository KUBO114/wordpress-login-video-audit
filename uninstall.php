<?php
/**
 * ログイン動画監査プラグインのアンインストールスクリプト。
 *
 * @package LoginVideoAudit
 */

// WordPressからアンインストールが呼び出されていない場合は終了。
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// すべてのログイン動画投稿を削除。
$posts = get_posts(
    array(
        'post_type'      => 'lva_log',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    )
);

foreach ( $posts as $post ) {
    // 関連する動画ファイルを削除。
    $video_path = get_post_meta( $post->ID, '_lva_path', true );
    if ( $video_path && file_exists( $video_path ) ) {
        wp_delete_file( $video_path );
    }
    
    // 投稿とすべてのメタデータを削除。
    wp_delete_post( $post->ID, true );
}

// オプション: 空の場合はアップロードディレクトリを削除。
$upload_dir = wp_upload_dir();
$lva_dir    = trailingslashit( $upload_dir['basedir'] ) . 'login-videos';

if ( is_dir( $lva_dir ) ) {
    // .htaccessファイルを削除。
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $lva_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $iterator as $file ) {
        if ( $file->isFile() && '.htaccess' === $file->getFilename() ) {
            unlink( $file->getPathname() );
        }
    }
    
    // ディレクトリの削除を試みる（空の場合のみ成功）。
    @rmdir( $lva_dir );
}

<?php
/**
 * プラグイン名: ログインビデオ監査
 * プラグイン URI: https://github.com/yourusername/login-video-audit
 * 説明: セキュリティ監査のため、ログイン時に短いビデオを記録します。ビデオは安全に保存され、管理者のみがアクセスできます。
 * バージョン: 1.0.0
 * 必要な WordPress バージョン: 5.0 以上
 * 必要な PHP バージョン: 7.4 以上
 * 作者: MIKIYA KUBO
 * 作者 URI: https://yourwebsite.com
 * ライセンス: GPL v2 またはそれ以降
 * ライセンス URI: https://www.gnu.org/licenses/gpl-2.0.html
 * テキストドメイン: login-video-audit
 * 言語ファイルパス: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数。
define( 'LVA_VERSION', '1.0.0' );
define( 'LVA_SEC', 1.5 );
define( 'LVA_MAX_BYTES', 2000000 );
define( 'LVA_DIR', 'login-videos' );

/**
 * ログインページでスクリプトを読み込み。
 *
 * @return void
 */
function lva_enqueue_login_scripts() {
    wp_enqueue_script(
        'lva',
        plugin_dir_url( __FILE__ ) . 'login-video.js',
        array(),
        LVA_VERSION,
        true
    );
    
    wp_localize_script(
        'lva',
        'LVA',
        array(
            'ajax'   => admin_url( 'admin-ajax.php' ),
            'nonce'  => wp_create_nonce( 'lva_nonce' ),
            'sec'    => LVA_SEC,
            'notice' => __(
                'This site captures a short video during login ' .
                'for security audit purposes.',
                'login-video-audit'
            ),
        )
    );
}
add_action( 'login_enqueue_scripts', 'lva_enqueue_login_scripts' );

/**
 * ログインメッセージを表示。
 *
 * @param string $message ログインメッセージ。
 * @return string 修正されたログインメッセージ。
 */
function lva_login_message( $message ) {
    $notice = sprintf(
        '<p style="text-align:center;background:#fff3cd;border:1px solid #ffe69c;' .
        'padding:8px;border-radius:8px;">%s</p>',
        esc_html__(
            'Camera access will be requested for security audit purposes ' .
            '(short video, admin viewing only).',
            'login-video-audit'
        )
    );
    return $notice . $message;
}
add_action( 'login_message', 'lva_login_message' );

/**
 * AJAX経由で動画アップロードを処理。
 *
 * @return void
 */
function lva_upload() {
    if ( ! check_ajax_referer( 'lva_nonce', 'nonce', false )) {
        wp_send_json_error( 'bad_nonce', 400 );
    }

    // 入力をサニタイズ。
    $username = isset( $_POST['username'] ) ?
        sanitize_text_field( wp_unslash( $_POST['username'] )) : '';
    $ua_raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ?
        sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] )) : '';
    $ua = substr( $ua_raw, 0, 255 );
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ?
        sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] )) : '';
    $blob     = isset( $_FILES['video'] ) ? $_FILES['video'] : null;

    if ( ! $blob || UPLOAD_ERR_OK !== $blob['error'] ) {
        wp_send_json_error( 'no_file', 400 );
    }
    
    if ( $blob['size'] > LVA_MAX_BYTES ) {
        wp_send_json_error( 'too_large', 413 );
    }

    // アップロードディレクトリを作成。
    $upload_dir = wp_upload_dir();
    $base_dir   = trailingslashit( $upload_dir['basedir'] ) .
        LVA_DIR . '/' . gmdate( 'Y/m' );
    
    if ( ! wp_mkdir_p( $base_dir )) {
        wp_send_json_error( 'mkdir_failed', 500 );
    }

    // .htaccessでディレクトリを保護。
    $htaccess = $base_dir . '/.htaccess';
    if ( ! file_exists( $htaccess )) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $htaccess, "Require all denied\n" );
    }

    // ファイル名を生成。
    $filename = sprintf(
        'lva_%s_%s.webm',
        gmdate( 'Ymd_His' ),
        wp_generate_password( 6, false )
    );
    $dest = $base_dir . '/' . $filename;
    
    if ( ! move_uploaded_file( $blob['tmp_name'], $dest )) {
        wp_send_json_error( 'move_failed', 500 );
    }

    // カスタム投稿タイプに記録。
    $post_id = wp_insert_post(
        array(
            'post_type'    => 'lva_log',
            'post_status'  => 'private',
            'post_title'   => sprintf(
                /* translators: 1: Date and time, 2: Username */
                __( 'Login Video %1$s (%2$s)', 'login-video-audit' ),
                date_i18n( 'Y-m-d H:i:s' ),
                $username ? $username : 'unknown'
            ),
            'post_content' => '',
        )
    );
    
    if ( $post_id ) {
        $rel_path = str_replace(
            trailingslashit( $upload_dir['basedir'] ),
            '',
            $dest
        );
        add_post_meta( $post_id, '_lva_path', $dest );
        add_post_meta( $post_id, '_lva_rel', $rel_path );
        add_post_meta( $post_id, '_lva_username', $username );
        add_post_meta( $post_id, '_lva_ip', $ip );
        add_post_meta( $post_id, '_lva_ua', $ua );
    }

    wp_send_json_success( array( 'ok' => true ) );
}
add_action( 'wp_ajax_nopriv_lva_upload', 'lva_upload' );

/**
 * ログイン動画ログ用のカスタム投稿タイプを登録。
 *
 * @return void
 */
function lva_register_post_type() {
    register_post_type(
        'lva_log',
        array(
            'label'          => __( 'Login Videos', 'login-video-audit' ),
            'public'         => false,
            'show_ui'        => true,
            'capability_type' => 'post',
            'map_meta_cap'   => true,
            'supports'       => array( 'title' ),
            'menu_position'  => 75,
            'menu_icon'      => 'dashicons-video-alt2',
        )
    );
}
add_action( 'init', 'lva_register_post_type' );

/**
 * 管理画面リストにカスタムカラムを追加。
 *
 * @param array $cols 既存のカラム。
 * @return array 修正されたカラム。
 */
function lva_manage_columns( $cols ) {
    $cols['lva_user'] = __( 'User', 'login-video-audit' );
    $cols['lva_ip']   = __( 'IP Address', 'login-video-audit' );
    $cols['lva_vid']  = __( 'Video', 'login-video-audit' );
    return $cols;
}
add_filter( 'manage_lva_log_posts_columns', 'lva_manage_columns' );

/**
 * カスタムカラムの内容を表示。
 *
 * @param string $col     カラム名。
 * @param int    $post_id 投稿ID。
 * @return void
 */
function lva_custom_column_content( $col, $post_id ) {
    if ( 'lva_user' === $col ) {
        echo esc_html( get_post_meta( $post_id, '_lva_username', true ) );
    }
    
    if ( 'lva_ip' === $col ) {
        echo esc_html( get_post_meta( $post_id, '_lva_ip', true ) );
    }
    
    if ( 'lva_vid' === $col ) {
        $rel = get_post_meta( $post_id, '_lva_rel', true );
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=lva_dl&id=' . $post_id ),
            'lva_dl_' . $post_id
        );
        
        if ( $rel ) {
            printf(
                '<a class="button" href="%s">%s</a>',
                esc_url( $url ),
                esc_html__( 'Play/Download', 'login-video-audit' )
            );
        } else {
            echo '-';
        }
    }
}
add_action( 'manage_lva_log_posts_custom_column', 'lva_custom_column_content', 10, 2 );

/**
 * 権限チェック付きで動画ダウンロードを処理。
 *
 * @return void
 */
function lva_download_video() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden', 'login-video-audit' ) );
    }
    
    $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

    $nonce = isset( $_GET['_wpnonce'] ) ?
        sanitize_text_field( wp_unslash( $_GET['_wpnonce'] )) : '';
    if ( ! wp_verify_nonce( $nonce, 'lva_dl_' . $id )) {
        wp_die( esc_html__( 'Invalid nonce', 'login-video-audit' ) );
    }
    
    $path = get_post_meta( $id, '_lva_path', true );
    
    if ( ! $path || ! file_exists( $path ) ) {
        wp_die( esc_html__( 'File not found', 'login-video-audit' ) );
    }

    header( 'Content-Type: video/webm' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
    readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}
add_action( 'admin_post_lva_dl', 'lva_download_video' );

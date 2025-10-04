<?php

/**
 * Plugin Name: Login Video Audit
 * Description: ログイン時に1〜3秒の顔動画を取得して管理者のみ閲覧可能に保存。
 * Version: 0.2.0
 * Author: KUBO114
 * Plugin URI: https://github.com/KUBO114/wordpress-login-video-audit
 * GitHub Plugin URI: https://github.com/KUBO114/wordpress-login-video-audit
 * GitHub Branch: main
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// === 設定 ===
const LVA_SEC = 1.5;            // 録画秒数
const LVA_MAX_BYTES = 2_000_000; // 最大 2MB（短尺WebM想定）
const LVA_DIR = 'login-videos';  // /uploads/login-videos/ に保存

// スクリプト投入（ログイン画面だけ）
add_action('login_enqueue_scripts', function () {
    $ver = '0.2.0';
    wp_enqueue_script('lva', plugin_dir_url(__FILE__) . 'login-video.js', [], $ver, true);
    wp_enqueue_script('face-auth', plugin_dir_url(__FILE__) . 'face-auth.js', [], $ver, true);
    wp_localize_script('lva', 'LVA', [
        'ajax'   => admin_url('admin-ajax.php'),
        'nonce'  => wp_create_nonce('lva_nonce'),
        'sec'    => LVA_SEC,
        'notice' => 'このサイトはセキュリティ監査のため、ログイン時にカメラ映像を取得します。',
        'face_auth_enabled' => get_option('lva_face_auth_enabled', false)
    ]);
    // 軽い注意書きを表示（同意テキスト）
    add_action('login_message', fn($m) => '<p style="text-align:center;background:#fff3cd;border:1px solid #ffe69c;padding:8px;border-radius:8px;">'
        . esc_html('ログイン前にカメラ利用の許可を求めます') . '</p>' . $m);
});

// AJAX: 動画アップロード
add_action('wp_ajax_nopriv_lva_upload', 'lva_upload');
/**
 * Summary of lva_upload
 * @return void
 */
function lva_upload()
{
    if (!check_ajax_referer('lva_nonce', 'nonce', false)) wp_send_json_error('bad_nonce', 400);

    // 入力
    $username = sanitize_text_field($_POST['username'] ?? '');
    $ua       = substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ip       = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $blob     = $_FILES['video'] ?? null;

    if (!$blob || $blob['error'] !== UPLOAD_ERR_OK) wp_send_json_error('no_file', 400);
    if ($blob['size'] > LVA_MAX_BYTES) wp_send_json_error('too_large', 413);

    // 保存ディレクトリ
    $up   = wp_upload_dir();
    $base = trailingslashit($up['basedir']) . LVA_DIR . '/' . date('Y/m');
    if (!wp_mkdir_p($base)) wp_send_json_error('mkdir_failed', 500);

    // 直リンク遮断（.htaccessでdeny）
    $ht = "$base/.htaccess";
    if (!file_exists($ht))
        file_put_contents($ht, "Require all denied\n");

    // ファイル名
    $name = sprintf('lva_%s_%s.webm', date('Ymd_His'), wp_generate_password(6, false));
    $dest = "$base/$name";
    if (!move_uploaded_file($blob['tmp_name'], $dest))
        wp_send_json_error('move_failed', 500);

    // ログをCPTに記録
    $post_id = wp_insert_post([
        'post_type' => 'lva_log',
        'post_status' => 'private',
        'post_title' => sprintf('Login Video %s (%s)', date_i18n('Y-m-d H:i:s'), $username ?: 'unknown'),
        'post_content' => '',
    ]);
    if ($post_id) {
        add_post_meta($post_id, '_lva_path', $dest);
        add_post_meta($post_id, '_lva_rel', str_replace(trailingslashit($up['basedir']), '', $dest));
        add_post_meta($post_id, '_lva_username', $username);
        add_post_meta($post_id, '_lva_ip', $ip);
        add_post_meta($post_id, '_lva_ua', $ua);
    }

    wp_send_json_success(['ok' => true]);
}

// AJAX: 顔認証
add_action('wp_ajax_nopriv_lva_face_auth', 'lva_face_auth');
add_action('wp_ajax_lva_face_auth', 'lva_face_auth');
/**
 * 顔認証処理
 */
function lva_face_auth()
{
    if (!check_ajax_referer('lva_nonce', 'nonce', false)) {
        wp_send_json_error('bad_nonce', 400);
    }

    $face_data = sanitize_text_field($_POST['face_data'] ?? '');
    if (empty($face_data)) {
        wp_send_json_error('no_face_data', 400);
    }

    // 顔認証処理（簡易版）
    $user_id = lva_authenticate_face($face_data);

    if ($user_id) {
        // 認証成功 - セッションに記録
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // ログイン記録
        lva_log_face_login($user_id, $face_data);

        wp_send_json_success(['user_id' => $user_id, 'redirect' => admin_url()]);
    } else {
        wp_send_json_error('face_auth_failed', 401);
    }
}

// AJAX: 顔登録
add_action('wp_ajax_lva_face_enroll', 'lva_face_enroll');
/**
 * 顔登録処理
 */
function lva_face_enroll()
{
    if (!check_ajax_referer('lva_nonce', 'nonce', false)) {
        wp_send_json_error('bad_nonce', 400);
    }

    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $face_data = sanitize_text_field($_POST['face_data'] ?? '');
    if (empty($face_data)) {
        wp_send_json_error('no_face_data', 400);
    }

    $user_id = get_current_user_id();
    $enrolled = lva_enroll_face($user_id, $face_data);

    if ($enrolled) {
        wp_send_json_success(['message' => '顔の登録が完了しました']);
    } else {
        wp_send_json_error('enrollment_failed', 500);
    }
}

// CPT 登録
add_action('init', function () {
    register_post_type('lva_log', [
        'label' => 'Login Videos',
        'public' => false,
        'show_ui' => true,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'supports' => ['title'],
        'menu_position' => 75,
        'menu_icon' => 'dashicons-video-alt2',
    ]);
});

// 管理一覧のカラム
add_filter('manage_lva_log_posts_columns', function ($cols) {
    $cols['lva_user'] = 'User';
    $cols['lva_ip'] = 'IP';
    $cols['lva_vid'] = 'Video';
    return $cols;
});
add_action('manage_lva_log_posts_custom_column', function ($col, $post_id) {
    if ($col === 'lva_user')
        echo esc_html(get_post_meta($post_id, '_lva_username', true));
    if ($col === 'lva_ip')
        echo esc_html(get_post_meta($post_id, '_lva_ip', true));
    if ($col === 'lva_vid') {
        $rel = get_post_meta($post_id, '_lva_rel', true);
        $url = wp_nonce_url(admin_url("admin-post.php?action=lva_dl&id=$post_id"), "lva_dl_$post_id");
        echo $rel ? '<a class="button" href="' . esc_url($url) . '">再生/ダウンロード</a>' : '-';
    }
}, 10, 2);

// ダウンロード（権限チェック＋非公開パスから読み出し）
add_action('admin_post_lva_dl', function () {
    if (!current_user_can('manage_options'))
        wp_die('forbidden');
    $id = intval($_GET['id'] ?? 0);
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', "lva_dl_$id"))
        wp_die('bad nonce');
    $path = get_post_meta($id, '_lva_path', true);
    if (!$path || !file_exists($path)) wp_die('not found');

    header('Content-Type: video/webm');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    readfile($path);
    exit;
});

/**
 * 顔認証処理（簡易版）
 */
function lva_authenticate_face($face_data)
{
    global $wpdb;

    // 顔データベースから検索
    $table_name = $wpdb->prefix . 'lva_face_data';
    $faces = $wpdb->get_results("SELECT user_id, face_data FROM $table_name WHERE face_data IS NOT NULL");

    foreach ($faces as $face) {
        // 簡易的な顔比較（実際の実装ではより高度なアルゴリズムを使用）
        if (lva_compare_faces($face_data, $face->face_data)) {
            return $face->user_id;
        }
    }

    return false;
}

/**
 * 顔登録処理
 */
function lva_enroll_face($user_id, $face_data)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'lva_face_data';

    // 既存の顔データを更新または新規作成
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if ($existing) {
        $result = $wpdb->update(
            $table_name,
            ['face_data' => $face_data, 'updated_at' => current_time('mysql')],
            ['user_id' => $user_id],
            ['%s', '%s'],
            ['%d']
        );
    } else {
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'face_data' => $face_data,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    return $result !== false;
}

/**
 * 顔比較処理（簡易版）
 */
function lva_compare_faces($face1, $face2)
{
    // 実際の実装では、より高度な顔認識アルゴリズムを使用
    // ここでは簡易的な比較を実装
    return abs(strlen($face1) - strlen($face2)) < 1000; // 簡易的な閾値
}

/**
 * 顔認証ログイン記録
 */
function lva_log_face_login($user_id, $face_data)
{
    $user = get_user_by('id', $user_id);
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    // ログ記録
    $post_id = wp_insert_post([
        'post_type' => 'lva_log',
        'post_status' => 'private',
        'post_title' => sprintf('Face Auth Login %s (%s)', date_i18n('Y-m-d H:i:s'), $user->user_login),
        'post_content' => '',
    ]);

    if ($post_id) {
        add_post_meta($post_id, '_lva_username', $user->user_login);
        add_post_meta($post_id, '_lva_ip', $ip);
        add_post_meta($post_id, '_lva_ua', $ua);
        add_post_meta($post_id, '_lva_auth_type', 'face_auth');
        add_post_meta($post_id, '_lva_face_data', $face_data);
    }
}

/**
 * 顔認証データベーステーブル作成
 */
function lva_create_face_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'lva_face_data';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        face_data longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// プラグイン有効化時にテーブル作成
register_activation_hook(__FILE__, 'lva_create_face_table');

// 管理画面に設定ページを追加
add_action('admin_menu', function () {
    add_options_page(
        'Login Video Audit 設定',
        'Login Video Audit',
        'manage_options',
        'lva-settings',
        'lva_settings_page'
    );
});

/**
 * 設定ページ
 */
function lva_settings_page()
{
    if (isset($_POST['submit'])) {
        update_option('lva_face_auth_enabled', isset($_POST['lva_face_auth_enabled']));
        echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
    }

    $face_auth_enabled = get_option('lva_face_auth_enabled', false);
?>
    <div class="wrap">
        <h1>Login Video Audit 設定</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">顔認証機能</th>
                    <td>
                        <label>
                            <input type="checkbox" name="lva_face_auth_enabled" value="1" <?php checked($face_auth_enabled); ?>>
                            顔認証ログインを有効にする
                        </label>
                        <p class="description">有効にすると、ログイン画面に顔認証ボタンが表示されます。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2>顔認証データ</h2>
        <p>登録済みの顔認証データ: <strong><?php echo lva_get_face_count(); ?></strong> 件</p>
        <p><a href="<?php echo admin_url('edit.php?post_type=lva_log'); ?>" class="button">ログイン記録を表示</a></p>
    </div>
<?php
}

/**
 * 顔認証データ数を取得
 */
function lva_get_face_count()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'lva_face_data';
    return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
}

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

// 録画必須のログイン制御
add_action('wp_login', 'lva_check_mandatory_recording', 10, 2);
add_action('wp_authenticate_user', 'lva_validate_recording_requirement', 10, 2);

// === 設定 ===
const LVA_SEC = 1.5;            // 録画秒数
const LVA_MAX_BYTES = 2_000_000; // 最大 2MB（短尺WebM想定）
const LVA_DIR = 'login-videos';  // /uploads/login-videos/ に保存

// スクリプト投入（ログイン画面だけ）
add_action('login_enqueue_scripts', function () {
    $ver = '0.2.0';
    $face_auth_enabled = get_option('lva_face_auth_enabled', false);

    wp_enqueue_script('lva', plugin_dir_url(__FILE__) . 'login-video.js', [], $ver, true);

    // 顔認証機能が有効な場合のみface-auth.jsを読み込み
    if ($face_auth_enabled) {
        wp_enqueue_script('face-auth', plugin_dir_url(__FILE__) . 'face-auth.js', [], $ver, true);
    }

    wp_localize_script('lva', 'LVA', [
        'ajax'   => admin_url('admin-ajax.php'),
        'nonce'  => wp_create_nonce('lva_nonce'),
        'sec'    => LVA_SEC,
        'notice' => 'このサイトはセキュリティ監査のため、ログイン時にカメラ映像を取得します。',
        'face_auth_enabled' => $face_auth_enabled
    ]);
    // 軽い注意書きを表示（同意テキスト）
    add_action('login_message', fn($m) => '<p style="text-align:center;color:#125E96;">'
        . esc_html('カメラ許可が必要') . '</p>' . $m);

    // JavaScript無効時のフォールバック
    add_action('login_footer', 'lva_add_nojs_fallback');
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

    // 録画完了フラグをセッションに設定
    $_SESSION['lva_recording_completed'] = true;

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
    // 顔認証機能が無効化されている場合は拒否
    if (!get_option('lva_face_auth_enabled', false)) {
        wp_send_json_error('顔認証機能が無効化されています', 403);
    }

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
    // 顔認証機能が無効化されている場合は拒否
    if (!get_option('lva_face_auth_enabled', false)) {
        wp_send_json_error('顔認証機能が無効化されています', 403);
    }

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
        <?php if ($face_auth_enabled): ?>
            <p>登録済みの顔認証データ: <strong><?php echo lva_get_face_count(); ?></strong> 件</p>
            <p><a href="<?php echo admin_url('edit.php?post_type=lva_log'); ?>" class="button">ログイン記録を表示</a></p>
        <?php else: ?>
            <p style="color: #666;">顔認証機能が無効化されているため、顔認証データは表示されません。</p>
            <p><a href="<?php echo admin_url('edit.php?post_type=lva_log'); ?>" class="button">ログイン記録を表示</a></p>
        <?php endif; ?>
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

/**
 * 録画必須のログイン検証
 */
function lva_validate_recording_requirement($user, $password)
{
    // 管理者は除外（緊急時のアクセス用）
    if (user_can($user, 'manage_options')) {
        return $user;
    }

    // セッションに録画フラグがあるかチェック
    if (!isset($_SESSION['lva_recording_completed']) || $_SESSION['lva_recording_completed'] !== true) {
        // 録画なしでログインを試行した場合、セッションを無効化
        wp_destroy_current_session();
        wp_logout();

        // エラーメッセージを表示してログイン画面にリダイレクト
        add_filter('login_errors', function ($errors) {
            return 'セキュリティのため、ログインには録画が必要です。';
        });

        // ログインを拒否
        return new WP_Error('recording_required', '録画が必要です。');
    }

    return $user;
}

/**
 * ログイン成功時の録画チェック
 */
function lva_check_mandatory_recording($user_login, $user)
{
    // セッションから録画フラグを削除（一度使用したら無効化）
    unset($_SESSION['lva_recording_completed']);

    // ログイン記録に録画必須フラグを追加
    lva_log_mandatory_recording_login($user);
}

/**
 * 録画必須ログインの記録
 */
function lva_log_mandatory_recording_login($user)
{
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $post_id = wp_insert_post([
        'post_type' => 'lva_log',
        'post_status' => 'private',
        'post_title' => sprintf('Mandatory Recording Login %s (%s)', date_i18n('Y-m-d H:i:s'), $user->user_login),
        'post_content' => '',
    ]);

    if ($post_id) {
        add_post_meta($post_id, '_lva_username', $user->user_login);
        add_post_meta($post_id, '_lva_ip', $ip);
        add_post_meta($post_id, '_lva_ua', $ua);
        add_post_meta($post_id, '_lva_auth_type', 'mandatory_recording');
        add_post_meta($post_id, '_lva_recording_required', true);
    }
}

/**
 * セッション開始
 */
function lva_start_session()
{
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'lva_start_session', 1);

// 自前アップデートロジック
add_action('admin_init', 'lva_check_for_updates');
add_action('wp_ajax_lva_manual_update', 'lva_manual_update');
add_action('admin_notices', 'lva_update_notice');
add_action('wp_ajax_lva_dismiss_notification', 'lva_dismiss_notification');
add_action('lva_update_available', 'lva_send_update_notifications');

/**
 * JavaScript無効時のフォールバック
 */
function lva_add_nojs_fallback()
{
?>
    <noscript>
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: white; z-index: 9999; display: flex; justify-content: center; align-items: center;">
            <div style="text-align: center;">
                <h2 style="color: #125E96;">JavaScriptが必要</h2>
                <button onclick="location.reload()" style="background: #125E96; color: white; border: none; padding: 10px; cursor: pointer;">
                    再読み込み
                </button>
            </div>
        </div>
    </noscript>

    <script>
        // 録画必須のログイン制御
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginform');
            if (!form) return;

            // フォーム送信を完全にブロック
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // 録画が完了していない場合は送信を拒否
                if (!window.lvaRecordingCompleted) {
                    alert('セキュリティのため、ログインには録画が必要です。');
                    return false;
                }
            });

            // 録画完了フラグを設定する関数
            window.setRecordingCompleted = function() {
                window.lvaRecordingCompleted = true;
            };
        });
    </script>
<?php
}

/**
 * アップデートチェッカー
 */
function lva_check_for_updates()
{
    $current_version = '0.2.0';
    $last_check = get_option('lva_last_update_check', 0);
    $check_interval = 24 * 60 * 60; // 24時間

    // チェック間隔を確認
    if (time() - $last_check < $check_interval) {
        return;
    }

    // GitHub APIから最新バージョンを取得
    $latest_version = lva_get_latest_version();

    if ($latest_version && version_compare($latest_version, $current_version, '>')) {
        update_option('lva_update_available', $latest_version);
        update_option('lva_update_available_time', time());

        // 更新通知を送信
        do_action('lva_update_available', $latest_version, $current_version);
    } else {
        delete_option('lva_update_available');
    }

    update_option('lva_last_update_check', time());
}

/**
 * GitHub APIから最新バージョンを取得
 */
function lva_get_latest_version()
{
    $api_url = 'https://api.github.com/repos/KUBO114/wordpress-login-video-audit/releases/latest';

    $response = wp_remote_get($api_url, [
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'WordPress-Plugin-Update-Checker'
        ]
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['tag_name'])) {
        return ltrim($data['tag_name'], 'v');
    }

    return false;
}

/**
 * アップデート通知
 */
function lva_update_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $update_available = get_option('lva_update_available');
    if (!$update_available) {
        return;
    }

    // 通知が非表示にされているかチェック
    $dismissed_time = get_option('lva_notification_dismissed', 0);
    $update_time = get_option('lva_update_available_time', 0);

    // 通知が非表示にされていて、かつ更新時間より後なら表示しない
    if ($dismissed_time > $update_time) {
        return;
    }

    $current_version = '0.2.0';
    $time_ago = human_time_diff($update_time);

?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong>Login Video Audit</strong>: 新しいバージョン <code><?php echo esc_html($update_available); ?></code> が利用可能です。
            （現在: <?php echo esc_html($current_version); ?>）
            <a href="#" id="lva-manual-update" class="button button-primary" style="margin-left: 10px;">
                今すぐ更新
            </a>
            <a href="#" id="lva-dismiss-notification" class="button" style="margin-left: 10px;">
                非表示
            </a>
            <span id="lva-update-status" style="margin-left: 10px;"></span>
        </p>
    </div>

    <script>
        document.getElementById('lva-manual-update').addEventListener('click', function(e) {
            e.preventDefault();

            const button = this;
            const status = document.getElementById('lva-update-status');

            button.disabled = true;
            button.textContent = '更新中...';
            status.textContent = 'GitHubから最新版をダウンロード中...';

            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'lva_manual_update',
                        nonce: '<?php echo wp_create_nonce('lva_update_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        status.textContent = '更新完了！ページを再読み込みします...';
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        status.textContent = '更新に失敗しました: ' + data.data;
                        button.disabled = false;
                        button.textContent = '今すぐ更新';
                    }
                })
                .catch(error => {
                    status.textContent = 'エラーが発生しました: ' + error.message;
                    button.disabled = false;
                    button.textContent = '今すぐ更新';
                });

            // 非表示ボタンの処理
            document.getElementById('lva-dismiss-notification').addEventListener('click', function(e) {
                e.preventDefault();

                fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'lva_dismiss_notification',
                            nonce: '<?php echo wp_create_nonce('lva_dismiss_nonce'); ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelector('.notice').style.display = 'none';
                        }
                    });
            });
        });
    </script>
<?php
}

/**
 * 手動アップデート処理
 */
function lva_manual_update()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('権限がありません', 403);
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lva_update_nonce')) {
        wp_send_json_error('Nonce検証に失敗しました', 400);
    }

    $latest_version = get_option('lva_update_available');
    if (!$latest_version) {
        wp_send_json_error('更新可能なバージョンが見つかりません', 404);
    }

    // バックアップを作成
    $backup_result = lva_create_backup();
    if (!$backup_result) {
        wp_send_json_error('バックアップの作成に失敗しました', 500);
    }

    // 最新版をダウンロード
    $download_result = lva_download_latest_version();
    if (!$download_result) {
        wp_send_json_error('最新版のダウンロードに失敗しました', 500);
    }

    // 一時ファイルを保存
    set_transient('lva_temp_update_file', $download_result, 300);

    // ファイルを更新
    $update_result = lva_update_files();
    if (!$update_result) {
        wp_send_json_error('ファイルの更新に失敗しました', 500);
    }

    // 更新完了
    delete_option('lva_update_available');
    update_option('lva_last_update_check', time());

    wp_send_json_success([
        'message' => '更新が完了しました',
        'version' => $latest_version
    ]);
}

/**
 * バックアップを作成
 */
function lva_create_backup()
{
    $plugin_dir = plugin_dir_path(__FILE__);
    $backup_dir = WP_CONTENT_DIR . '/lva-backups/' . date('Y-m-d_H-i-s');

    if (!wp_mkdir_p($backup_dir)) {
        return false;
    }

    $files = [
        'login-video-audit.php',
        'login-video.js',
        'face-auth.js',
        'README.md',
        'CHANGELOG.md'
    ];

    foreach ($files as $file) {
        $source = $plugin_dir . $file;
        $dest = $backup_dir . '/' . $file;

        if (file_exists($source)) {
            copy($source, $dest);
        }
    }

    return true;
}

/**
 * 最新版をダウンロード
 */
function lva_download_latest_version()
{
    $download_url = 'https://github.com/KUBO114/wordpress-login-video-audit/archive/main.zip';
    $temp_file = wp_tempnam('lva_update');

    $response = wp_remote_get($download_url, [
        'timeout' => 300,
        'stream' => true,
        'filename' => $temp_file
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    return $temp_file;
}

/**
 * ファイルを更新
 */
function lva_update_files()
{
    $temp_file = get_transient('lva_temp_update_file');
    if (!$temp_file || !file_exists($temp_file)) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($temp_file) !== TRUE) {
        return false;
    }

    $plugin_dir = plugin_dir_path(__FILE__);
    $extract_dir = WP_CONTENT_DIR . '/lva-temp-extract/';

    if (!wp_mkdir_p($extract_dir)) {
        return false;
    }

    $zip->extractTo($extract_dir);
    $zip->close();

    // ファイルをコピー
    $source_dir = $extract_dir . 'wordpress-login-video-audit-main/';
    $files = [
        'login-video-audit.php',
        'login-video.js',
        'face-auth.js',
        'README.md',
        'CHANGELOG.md'
    ];

    foreach ($files as $file) {
        $source = $source_dir . $file;
        $dest = $plugin_dir . $file;

        if (file_exists($source)) {
            copy($source, $dest);
        }
    }

    // 一時ファイルを削除
    unlink($temp_file);
    lva_recursive_rmdir($extract_dir);

    return true;
}

/**
 * 再帰的にディレクトリを削除
 */
function lva_recursive_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    lva_recursive_rmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
}

/**
 * 更新通知を送信
 */
function lva_send_update_notifications($latest_version, $current_version)
{
    // 管理者にメール通知
    lva_send_admin_email_notification($latest_version, $current_version);

    // 管理画面に通知を記録
    lva_log_update_notification($latest_version, $current_version);

    // 通知を非表示にするオプションをリセット
    delete_option('lva_notification_dismissed');
}

/**
 * 管理者にメール通知を送信
 */
function lva_send_admin_email_notification($latest_version, $current_version)
{
    $admin_email = get_option('admin_email');
    $site_name = get_option('blogname');
    $site_url = get_option('home');

    $subject = sprintf('[%s] Login Video Audit プラグインの更新が利用可能です', $site_name);

    $message = sprintf(
        "Login Video Audit プラグインの新しいバージョンが利用可能です。\n\n" .
            "現在のバージョン: %s\n" .
            "新しいバージョン: %s\n\n" .
            "更新方法:\n" .
            "1. WordPress管理画面にログイン\n" .
            "2. プラグイン一覧で「Login Video Audit」を探す\n" .
            "3. 「今すぐ更新」ボタンをクリック\n\n" .
            "サイト: %s\n" .
            "管理画面: %s/wp-admin/\n\n" .
            "この通知は自動送信されています。",
        $current_version,
        $latest_version,
        $site_url,
        $site_url
    );

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $site_name . ' <' . $admin_email . '>'
    ];

    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * 更新通知をログに記録
 */
function lva_log_update_notification($latest_version, $current_version)
{
    $post_id = wp_insert_post([
        'post_type' => 'lva_log',
        'post_status' => 'private',
        'post_title' => sprintf('Update Notification %s → %s', $current_version, $latest_version),
        'post_content' => sprintf('新しいバージョン %s が利用可能です（現在: %s）', $latest_version, $current_version),
    ]);

    if ($post_id) {
        add_post_meta($post_id, '_lva_notification_type', 'update_available');
        add_post_meta($post_id, '_lva_current_version', $current_version);
        add_post_meta($post_id, '_lva_latest_version', $latest_version);
        add_post_meta($post_id, '_lva_notification_time', time());
    }
}

/**
 * 通知を非表示にする
 */
function lva_dismiss_notification()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('権限がありません', 403);
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lva_dismiss_nonce')) {
        wp_send_json_error('Nonce検証に失敗しました', 400);
    }

    update_option('lva_notification_dismissed', time());
    wp_send_json_success(['message' => '通知を非表示にしました']);
}

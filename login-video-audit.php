<?php
/**
 * Plugin Name: Login Video Audit
 * Description: ログイン時に1〜3秒の顔動画を取得して管理者のみ閲覧可能に保存。
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) exit;

// === 設定 ===
const LVA_SEC = 1.5;            // 録画秒数
const LVA_MAX_BYTES = 2_000_000; // 最大 2MB（短尺WebM想定）
const LVA_DIR = 'login-videos';  // /uploads/login-videos/ に保存

// スクリプト投入（ログイン画面だけ）
add_action('login_enqueue_scripts', function () {
  $ver = '0.1.0';
  wp_enqueue_script('lva', plugin_dir_url(__FILE__).'login-video.js', [], $ver, true);
  wp_localize_script('lva', 'LVA', [
    'ajax'   => admin_url('admin-ajax.php'),
    'nonce'  => wp_create_nonce('lva_nonce'),
    'sec'    => LVA_SEC,
    'notice' => 'このサイトはセキュリティ監査のため、ログイン時に1〜2秒のカメラ映像を取得します。'
  ]);
  // 軽い注意書きを表示（同意テキスト）
  add_action('login_message', function($m){
    return '<p style="text-align:center;background:#fff3cd;border:1px solid #ffe69c;padding:8px;border-radius:8px;">'
      . esc_html('ログイン前にカメラ利用の許可を求めます（監査目的・短尺・管理者のみ閲覧）。') . '</p>' . $m;
  });
});

// AJAX: 動画アップロード
add_action('wp_ajax_nopriv_lva_upload', 'lva_upload');
function lva_upload() {
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
  $ht = $base . '/.htaccess';
  if (!file_exists($ht)) file_put_contents($ht, "Require all denied\n");

  // ファイル名
  $name = sprintf('lva_%s_%s.webm', date('Ymd_His'), wp_generate_password(6, false));
  $dest = $base . '/' . $name;
  if (!move_uploaded_file($blob['tmp_name'], $dest)) wp_send_json_error('move_failed', 500);

  // ログをCPTに記録
  $post_id = wp_insert_post([
    'post_type'   => 'lva_log',
    'post_status' => 'private',
    'post_title'  => sprintf('Login Video %s (%s)', date_i18n('Y-m-d H:i:s'), $username ?: 'unknown'),
    'post_content'=> '',
  ]);
  if ($post_id) {
    add_post_meta($post_id, '_lva_path', $dest);
    add_post_meta($post_id, '_lva_rel',  str_replace(trailingslashit($up['basedir']), '', $dest));
    add_post_meta($post_id, '_lva_username', $username);
    add_post_meta($post_id, '_lva_ip', $ip);
    add_post_meta($post_id, '_lva_ua', $ua);
  }

  wp_send_json_success(['ok'=>true]);
}

// CPT 登録
add_action('init', function(){
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
add_filter('manage_lva_log_posts_columns', function($cols){
  $cols['lva_user'] = 'User';
  $cols['lva_ip']   = 'IP';
  $cols['lva_vid']  = 'Video';
  return $cols;
});
add_action('manage_lva_log_posts_custom_column', function($col,$post_id){
  if ($col==='lva_user') echo esc_html(get_post_meta($post_id,'_lva_username',true));
  if ($col==='lva_ip')   echo esc_html(get_post_meta($post_id,'_lva_ip',true));
  if ($col==='lva_vid') {
    $rel = get_post_meta($post_id,'_lva_rel',true);
    $url = wp_nonce_url(admin_url('admin-post.php?action=lva_dl&id='.$post_id), 'lva_dl_'.$post_id);
    echo $rel ? '<a class="button" href="'.esc_url($url).'">再生/ダウンロード</a>' : '-';
  }
}, 10, 2);

// ダウンロード（権限チェック＋非公開パスから読み出し）
add_action('admin_post_lva_dl', function(){
  if (!current_user_can('manage_options')) wp_die('forbidden');
  $id = intval($_GET['id'] ?? 0);
  if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'lva_dl_'.$id)) wp_die('bad nonce');
  $path = get_post_meta($id, '_lva_path', true);
  if (!$path || !file_exists($path)) wp_die('not found');

  header('Content-Type: video/webm');
  header('Content-Length: '.filesize($path));
  header('Content-Disposition: inline; filename="'.basename($path).'"');
  readfile($path);
  exit;
});

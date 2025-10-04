<?php
/**
 * Plugin Name: Login Video Audit
 * Description: ログイン時に顔動画を取得して保存（シンプル版）
 * Version: 0.2
 */

if (!defined('ABSPATH')) exit;

// ログイン画面にスクリプトを読み込み
add_action('login_enqueue_scripts', function () {
  wp_enqueue_script('lva', plugin_dir_url(__FILE__).'login-video.js', [], '0.2', true);
  wp_localize_script('lva', 'LVA', [
    'ajax'  => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('lva_nonce')
  ]);
});

// 動画アップロード処理
add_action('wp_ajax_nopriv_lva_upload', function() {
  if (!check_ajax_referer('lva_nonce', 'nonce', false)) wp_send_json_error();

  $video = $_FILES['video'] ?? null;
  if (!$video || $video['error'] !== UPLOAD_ERR_OK || $video['size'] > 2000000) {
    wp_send_json_error();
  }

  // 保存先ディレクトリ
  $upload = wp_upload_dir();
  $dir = trailingslashit($upload['basedir']) . 'login-videos';
  wp_mkdir_p($dir);
  
  // .htaccessで保護
  $htaccess = $dir . '/.htaccess';
  if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Require all denied\n");
  }

  // ファイル保存
  $filename = date('Ymd_His') . '_' . wp_generate_password(8, false) . '.webm';
  $filepath = $dir . '/' . $filename;
  
  if (move_uploaded_file($video['tmp_name'], $filepath)) {
    // ログファイルに記録
    $log = $dir . '/access.log';
    $entry = sprintf(
      "[%s] User: %s, IP: %s, File: %s\n",
      date('Y-m-d H:i:s'),
      sanitize_text_field($_POST['username'] ?? 'unknown'),
      $_SERVER['REMOTE_ADDR'] ?? '',
      $filename
    );
    file_put_contents($log, $entry, FILE_APPEND);
    wp_send_json_success();
  }
  
  wp_send_json_error();
});

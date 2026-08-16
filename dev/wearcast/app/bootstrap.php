<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

$sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/view.php';

try {
    $config = wc_load_config();
    $pdo = wc_connect_db($config['db'] ?? []);
    $user = wc_resolve_user($pdo);
    $locations = wc_get_locations($pdo, $user);
    $activeLocation = wc_get_active_location($locations);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Wearcast Error</title>';
    echo '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef5fc;color:#102944;font-family:sans-serif}.box{max-width:520px;padding:28px;border-radius:24px;background:#fff;box-shadow:0 24px 60px rgba(16,41,68,.12)}h1{margin:0 0 12px;font-size:24px}p{line-height:1.7;color:#58718a}</style>';
    echo '</head><body><main class="box"><h1>保存機能を準備できません</h1><p>現在、データベースに接続できないため Wearcast を表示できません。時間をおいて再度お試しください。</p></main></body></html>';
    exit;
}

return [
    'config' => $config,
    'pdo' => $pdo,
    'mode' => 'database',
    'user' => $user,
    'locations' => $locations,
    'activeLocation' => $activeLocation,
    'flash' => wc_take_flash(),
];

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

$config = wc_load_config();
$pdo = wc_connect_db($config['db'] ?? []);
$user = wc_resolve_user($pdo);
$locations = wc_get_locations($pdo, $user);
$activeLocation = wc_get_active_location($locations);

return [
    'config' => $config,
    'pdo' => $pdo,
    'mode' => $pdo ? 'database' : 'demo',
    'user' => $user,
    'locations' => $locations,
    'activeLocation' => $activeLocation,
    'flash' => wc_take_flash(),
];

<?php

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
wc_require_csrf();

$email = trim((string) ($_POST['email'] ?? ''));
$locations = $_POST['locations'] ?? [];

wc_save_locations($app['pdo'], $app['user'], is_array($locations) ? $locations : [], $email);
wc_flash('success', '設定を保存しました。');

header('Location: ' . wc_url('settings.php'));
exit;

<?php

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
wc_require_csrf();

$recordId = trim((string) ($_POST['record_id'] ?? ''));
wc_delete_record($app['pdo'], $app['user'], $recordId);
wc_flash('success', '今日の記録を削除しました。');

header('Location: ' . wc_url('history.php'));
exit;

<?php

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
wc_require_csrf();

$email = trim((string) ($_POST['email'] ?? ''));
$locations = $_POST['locations'] ?? [];

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    wc_flash('error', 'メールアドレスの形式を確認してください。');
    header('Location: ' . wc_url('settings.php'));
    exit;
}

if (!is_array($locations)) {
    $locations = [];
}

$main = is_array($locations[0] ?? null) ? $locations[0] : [];
if (trim((string) ($main['office_code'] ?? '')) === '' || trim((string) ($main['area_code'] ?? '')) === '') {
    wc_flash('error', 'メイン地点の地域を選択してください。');
    header('Location: ' . wc_url('settings.php'));
    exit;
}

foreach ($locations as $index => $row) {
    if (!is_array($row)) {
        continue;
    }

    $officeCode = trim((string) ($row['office_code'] ?? ''));
    $areaCode = trim((string) ($row['area_code'] ?? ''));
    if ($index > 0 && ($officeCode === '' || $areaCode === '')) {
        $locations[$index]['office_code'] = '';
        $locations[$index]['area_code'] = '';
        $locations[$index]['lat'] = '';
        $locations[$index]['lng'] = '';
        continue;
    }

    $locations[$index]['lat'] = '';
    $locations[$index]['lng'] = '';
}

wc_save_locations($app['pdo'], $app['user'], $locations, $email);
wc_flash('success', '設定を保存しました。');

header('Location: ' . wc_url('settings.php'));
exit;

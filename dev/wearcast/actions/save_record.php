<?php

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
wc_require_csrf();

try {
    $imagePath = wc_uploaded_image_path((string) $app['user']['token']);
    wc_save_record($app['pdo'], $app['user'], [
        'id' => '',
        'location_id' => trim((string) ($_POST['location_id'] ?? '')),
        'record_date' => trim((string) ($_POST['record_date'] ?? date('Y-m-d'))),
        'weather_group' => trim((string) ($_POST['weather_group'] ?? 'cloudy')),
        'weather_label' => trim((string) ($_POST['weather_label'] ?? '')),
        'weather_code' => trim((string) ($_POST['weather_code'] ?? '')),
        'temp_max' => trim((string) ($_POST['temp_max'] ?? '0')),
        'temp_min' => trim((string) ($_POST['temp_min'] ?? '0')),
        'outfit_category' => trim((string) ($_POST['outfit_category'] ?? '')),
        'comfort_vote' => trim((string) ($_POST['comfort_vote'] ?? 'just')),
        'comment_text' => trim((string) ($_POST['comment_text'] ?? '')),
        'free_note' => trim((string) ($_POST['free_note'] ?? '')),
        'image_path' => $imagePath,
    ]);
    wc_flash('success', '今日の服装を保存しました。');
} catch (Throwable $exception) {
    wc_flash('error', $exception->getMessage());
}

header('Location: ' . wc_url('history.php'));
exit;

<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$officeCode = trim((string) ($_GET['office'] ?? ''));
echo json_encode([
    'areas' => wc_area_options($officeCode),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

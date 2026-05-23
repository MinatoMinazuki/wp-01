<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json; charset=utf-8');

// 日記のユーザー登録済みタグ一覧を取得
$sql = sprintf("
    SELECT
    id,
    name
    FROM
    tags
    ORDER BY
    created_at DESC
    ");

$tags = $dbc->Dsql($sql);

if (is_array($tags)) {
    echo json_encode(['success' => true, 'tags' => $tags]);
} else {
    echo json_encode(['error' => 'Failed to fetch tags.']);
}
?>

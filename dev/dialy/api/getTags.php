<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

$tags = $dbc->fetchAll(
    "
    SELECT DISTINCT
        tg.id,
        tg.name
    FROM tags AS tg
    INNER JOIN tweet_tags AS tt ON tt.tag_id = tg.id
    INNER JOIN tweets AS t ON t.id = tt.tweet_id
    WHERE t.user_id = :user_id
      AND t.is_deleted = 0
    ORDER BY tg.created_at DESC
    ",
    ['user_id' => current_user_id()]
);

echo json_encode(['success' => true, 'tags' => $tags]);

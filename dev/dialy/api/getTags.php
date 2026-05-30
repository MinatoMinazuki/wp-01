<?php
require_once __DIR__ . '/bootstrap.php';

$hasTagOwner = tags_have_user_id($dbc);
$ownerSelect = $hasTagOwner ? 'tg.user_id = :user_id' : 't.user_id = :user_id';
$params = ['user_id' => current_user_id()];

$tags = $dbc->fetchAll(
    "
    SELECT DISTINCT
        tg.id,
        tg.name
    FROM tags AS tg
    INNER JOIN tweet_tags AS tt ON tt.tag_id = tg.id
    INNER JOIN tweets AS t ON t.id = tt.tweet_id
    WHERE {$ownerSelect}
      AND t.is_deleted = 0
    ORDER BY tg.created_at DESC
    ",
    $params
);

json_response(['success' => true, 'tags' => $tags]);

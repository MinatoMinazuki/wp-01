<?php
require_once __DIR__ . '/bootstrap.php';

require_post();
require_csrf();

$userId = current_user_id();
$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$tagId = isset($_POST['tagId']) ? (int)$_POST['tagId'] : 0;

if ($tweetId <= 0 || $tagId <= 0) {
    json_response(['error' => 'Invalid parameters.'], 400);
}

$tweet = $dbc->fetchOne(
    "
    SELECT id
    FROM tweets
    WHERE id = :tweet_id
      AND user_id = :user_id
      AND is_deleted = 0
    ",
    [
        'tweet_id' => $tweetId,
        'user_id' => $userId,
    ]
);

if ($tweet === null) {
    json_response(['error' => 'Not authorized or tweet not found.'], 404);
}

$tagParams = ['tag_id' => $tagId];
$tagOwnerSql = '';
if (tags_have_user_id($dbc)) {
    $tagOwnerSql = ' AND user_id = :user_id';
    $tagParams['user_id'] = $userId;
}

$tag = $dbc->fetchOne(
    "SELECT id FROM tags WHERE id = :tag_id{$tagOwnerSql}",
    $tagParams
);

if ($tag === null) {
    json_response(['error' => 'Tag not found.'], 404);
}

$dbc->execute(
    "
    INSERT IGNORE INTO tweet_tags (tweet_id, tag_id)
    VALUES (:tweet_id, :tag_id)
    ",
    [
        'tweet_id' => $tweetId,
        'tag_id' => $tagId,
    ]
);

$ownerJoinCondition = tags_have_user_id($dbc) ? ' AND tg.user_id = :user_id' : '';
$currentTags = $dbc->fetchAll(
    "
    SELECT tg.name
    FROM tweet_tags AS tt
    JOIN tags AS tg ON tt.tag_id = tg.id
    WHERE tt.tweet_id = :tweet_id
    {$ownerJoinCondition}
    ORDER BY tg.id ASC
    ",
    tags_have_user_id($dbc)
        ? ['tweet_id' => $tweetId, 'user_id' => $userId]
        : ['tweet_id' => $tweetId]
);

json_response(['success' => true, 'tags' => array_column($currentTags, 'name')]);

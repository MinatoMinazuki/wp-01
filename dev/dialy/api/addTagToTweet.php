<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

require_post();
require_csrf();

$userId = (int)$_SESSION['userId'];
$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$tagId = isset($_POST['tagId']) ? (int)$_POST['tagId'] : 0;

if ($tweetId <= 0 || $tagId <= 0) {
    echo json_encode(['error' => 'Invalid parameters.']);
    exit;
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
    echo json_encode(['error' => 'Not authorized or tweet not found.']);
    exit;
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

$currentTags = $dbc->fetchAll(
    "
    SELECT tg.name
    FROM tweet_tags AS tt
    JOIN tags AS tg ON tt.tag_id = tg.id
    WHERE tt.tweet_id = :tweet_id
    ORDER BY tg.id ASC
    ",
    ['tweet_id' => $tweetId]
);

$tagList = array_column($currentTags, 'name');

echo json_encode(['success' => true, 'tags' => $tagList]);

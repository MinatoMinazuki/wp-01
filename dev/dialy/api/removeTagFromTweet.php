<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

require_post();
require_csrf();

$userId = (int)$_SESSION['userId'];

$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$tagName = isset($_POST['tagName']) ? trim((string)$_POST['tagName']) : '';

if ($tweetId <= 0 || $tagName === '') {
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
    echo json_encode(['error' => 'Tweet not found or not authorized.']);
    exit;
}

$tag = $dbc->fetchOne(
    "SELECT id FROM tags WHERE name = :name",
    ['name' => $tagName]
);

if ($tag !== null) {
    $dbc->execute(
        "
        DELETE FROM tweet_tags
        WHERE tweet_id = :tweet_id
          AND tag_id = :tag_id
        ",
        [
            'tweet_id' => $tweetId,
            'tag_id' => (int)$tag['id'],
        ]
    );
}

$remainingTags = $dbc->fetchAll(
    "
    SELECT tg.name
    FROM tweet_tags AS tt
    INNER JOIN tags AS tg ON tt.tag_id = tg.id
    WHERE tt.tweet_id = :tweet_id
    ORDER BY tg.id ASC
    ",
    ['tweet_id' => $tweetId]
);

echo json_encode(['success' => true, 'tags' => array_column($remainingTags, 'name')]);

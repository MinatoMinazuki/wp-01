<?php
require_once __DIR__ . '/bootstrap.php';

require_post();
require_csrf();

$userId = current_user_id();
$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$tagName = isset($_POST['tagName']) ? trim((string)$_POST['tagName']) : '';

if ($tweetId <= 0 || $tagName === '') {
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
    json_response(['error' => 'Tweet not found or not authorized.'], 404);
}

$tagParams = ['name' => $tagName];
$tagOwnerSql = '';
if (tags_have_user_id($dbc)) {
    $tagOwnerSql = ' AND user_id = :user_id';
    $tagParams['user_id'] = $userId;
}

$tag = $dbc->fetchOne(
    "SELECT id FROM tags WHERE name = :name{$tagOwnerSql}",
    $tagParams
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

$ownerJoinCondition = tags_have_user_id($dbc) ? ' AND tg.user_id = :user_id' : '';
$remainingTags = $dbc->fetchAll(
    "
    SELECT tg.name
    FROM tweet_tags AS tt
    INNER JOIN tags AS tg ON tt.tag_id = tg.id
    WHERE tt.tweet_id = :tweet_id
    {$ownerJoinCondition}
    ORDER BY tg.id ASC
    ",
    tags_have_user_id($dbc)
        ? ['tweet_id' => $tweetId, 'user_id' => $userId]
        : ['tweet_id' => $tweetId]
);

json_response(['success' => true, 'tags' => array_column($remainingTags, 'name')]);

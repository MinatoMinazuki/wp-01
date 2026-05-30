<?php
require_once __DIR__ . '/bootstrap.php';

require_post();
require_csrf();

$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$userId = current_user_id();

if ($tweetId <= 0) {
    json_response(['error' => 'Invalid tweet ID.'], 400);
}

$tweet = $dbc->fetchOne(
    "
    SELECT id, image_file AS imageFile
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
    json_response(['error' => 'Failed to delete or not found.'], 404);
}

$updated = $dbc->execute(
    "
    UPDATE tweets
    SET is_deleted = 1
    WHERE id = :tweet_id
      AND user_id = :user_id
    ",
    [
        'tweet_id' => $tweetId,
        'user_id' => $userId,
    ]
);

if ($updated <= 0) {
    json_response(['error' => 'Failed to delete or not found.'], 404);
}

if (!empty($tweet['imageFile'])) {
    $imagePath = realpath(__DIR__ . '/../uploads/' . $tweet['imageFile']);
    $uploadsRoot = realpath(__DIR__ . '/../uploads');

    if ($imagePath !== false && $uploadsRoot !== false && strpos($imagePath, $uploadsRoot) === 0 && is_file($imagePath)) {
        @unlink($imagePath);
    }
}

json_response(['success' => true]);

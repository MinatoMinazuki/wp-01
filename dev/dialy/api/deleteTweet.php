<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

require_post();
require_csrf();

if (!isset($_SESSION['userId'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$userId = (int)$_SESSION['userId'];

if ($tweetId <= 0) {
    echo json_encode(['error' => 'Invalid tweet ID.']);
    exit;
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

if ($updated > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to delete or not found.']);
}

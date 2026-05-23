<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['userId'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$tweetId = isset($_POST['tweetId']) ? (int)$_POST['tweetId'] : 0;
$tagName = isset($_POST['tagName']) ? trim($_POST['tagName']) : '';

if ($tweetId <= 0 || $tagName === '') {
    echo json_encode(['error' => 'Invalid parameters.']);
    exit;
}

// Ensure the tweet belongs to the user and is not deleted
$checkSql = "SELECT `id` FROM tweets WHERE `id` = {$tweetId} AND `user_id` = {$userId} AND `is_deleted` = 0";
$checkRes = $dbc->Dsql($checkSql);
if ($checkRes === false || count($checkRes) === 0) {
    echo json_encode(['error' => 'ツイートが見つからないか、権限がありません。']);
    exit;
}

$escapedTagName = $dbc->escape($tagName);

// Find the tag ID
$tagSql = "SELECT `id` FROM tags WHERE `name` = '{$escapedTagName}'";
$tagRes = $dbc->Dsql($tagSql);

if ($tagRes && count($tagRes) > 0) {
    $tagId = (int)$tagRes[0]['id'];

    // Delete relation
    $delSql = "DELETE FROM tweet_tags WHERE `tweet_id` = {$tweetId} AND `tag_id` = {$tagId}";
    $dbc->Dsql($delSql);
}

// Fetch remaining tags for the tweet
$remainingSql = "
    SELECT tg.`name`
    FROM tweet_tags tt
    INNER JOIN tags tg ON tt.`tag_id` = tg.`id`
    WHERE tt.`tweet_id` = {$tweetId}
";
$remainingRes = $dbc->Dsql($remainingSql);

$tags = [];
if (is_array($remainingRes)) {
    foreach ($remainingRes as $row) {
        $tags[] = $row['name'];
    }
}

echo json_encode(['success' => true, 'tags' => $tags]);
?>

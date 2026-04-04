<?php
require_once __DIR__.'/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['userId'];
$dateParam = isset($_GET['date']) ? htmlspecialchars( $_GET['date'] ) : null;
$offset = isset($_GET['offset']) ? (int)htmlspecialchars( $_GET['offset'] ) : 0;
$limit = 30;

$whereClause = "t.is_deleted` = 0 AND t.`user_id` = {$userId}";

if ($dateParam) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
        echo json_encode(['error' => 'Invalid date format.']);
        exit;
    }
    $whereClause .= " AND DATE(t.`created_at`) = '{$dateParam}'";
}

// ORDER BY created_at DESC to get newest first, then reverse
$sql = sprintf("
    SELECT
        t.`id`,
        t.`content`,
        t.`image_file` as imageFile,
        t.`created_at` as createdAt,
        t.`user_id` as userId,
        GROUP_CONCAT(tg.`name` SEPARATOR ',') as tags
    FROM tweets as t
    LEFT JOIN tweet_tags as tt ON t.id = tt.tweet_id
    LEFT JOIN tags as tg ON tt.tag_id = tg.id
    WHERE %s
    GROUP BY t.`id`
    ORDER BY t.`created_at` DESC
    LIMIT %s, %s
    ",
    $whereClause,
    $offset,
    $limit
);

$tweetsResult = $dbc->Dsql($sql);

if ($tweetsResult === false) {
    echo json_encode(['error' => 'Failed to fetch tweets.']);
} else {
    $formattedTweets = [];
    if (is_array($tweetsResult)) {
        // Reverse array to display oldest at the top, newest at bottom
        $tweetsResult = array_reverse($tweetsResult);

        foreach ($tweetsResult as $tw) {
            $tagList = [];
            if (!empty($tw['tags'])) {
                $tagList = explode(',', $tw['tags']);
            }
            $tw['tags'] = $tagList;
            $formattedTweets[] = $tw;
        }
    }

    // Check if we retrieved the full limit (meaning more might exist)
    $hasMore = count($formattedTweets) === $limit;

    echo json_encode(['success' => true, 'tweets' => $formattedTweets, 'hasMore' => $hasMore]);
}

?>
<?php
require_once __DIR__.'/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['userId'];
$dateParam = isset($_GET['date']) ? htmlspecialchars( $_GET['date'] ) : null;
$beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
$beforeDate = isset($_GET['before_date']) ? $_GET['before_date'] : null;
$searchQuery = isset($_GET['search']) ? htmlspecialchars( $_GET['search'] ) : null;
$limit = 30;

$whereClause = "t.`is_deleted` = 0 AND t.`user_id` = {$userId}";

if ($searchQuery) {
    $escapedQ = $dbc->escape('%' . $searchQuery . '%');
    $whereClause .= " AND (t.`content` LIKE '{$escapedQ}' OR EXISTS(SELECT 1 FROM tweet_tags tt2 INNER JOIN tags tg2 ON tt2.tag_id = tg2.id WHERE tt2.tweet_id = t.id AND tg2.name LIKE '{$escapedQ}'))";
}

$baseWhereClause = $whereClause;

if( $beforeId > 0 ){
    $whereClause .= " AND t.`id` < {$beforeId}";
} else if( $beforeDate ){
    if( !preg_match('/^\d{4}-\d{2}-\d{2}$/', $beforeDate) ){
        echo json_encode(['error' => 'Invalid date format.']);
        exit;
    }
    $whereClause .= " AND DATE(t.`created_at`) < '{$beforeDate}'";
} else if( $dateParam ){
    if( !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam) ){
        echo json_encode(['error' => 'Invalid date format.']);
        exit;
    }
    $whereClause .= " AND DATE(t.`created_at`) = '{$dateParam}'";
}

$sql = sprintf("
    SELECT
        t.`id`,
        t.`content`,
        t.`image_file` as imageFile,
        t.`created_at` as createdAt,
        t.`user_id` as userId,
        GROUP_CONCAT(tg.`name` SEPARATOR ',') as tags
    FROM tweets as t
    LEFT JOIN tweet_tags as tt ON t.id = tt.`tweet_id`
    LEFT JOIN tags as tg ON tt.tag_id = tg.`id`
    WHERE %s
    GROUP BY t.`id`
    ORDER BY t.`created_at` DESC
    LIMIT %s
    ",
    $whereClause,
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

    $hasMore = false;
    if (count($formattedTweets) > 0) {
        $oldestId = $formattedTweets[0]['id'];
        $checkSql = "SELECT t.`id` FROM tweets t WHERE {$baseWhereClause} AND t.`id` < {$oldestId} LIMIT 1";
        $checkRes = $dbc->Dsql($checkSql);
        if (is_array($checkRes) && count($checkRes) > 0) {
            $hasMore = true;
        }
    } else {
        if ($dateParam && !$beforeId) {
            $checkSql = "SELECT t.`id` FROM tweets t WHERE {$baseWhereClause} AND DATE(t.`created_at`) < '{$dateParam}' LIMIT 1";
            $checkRes = $dbc->Dsql($checkSql);
            if (is_array($checkRes) && count($checkRes) > 0) {
                $hasMore = true;
            }
        } else if ($searchQuery && !$beforeId) {
            // IF search turned up empty immediately, can't have more older.
            $hasMore = false;
        }
    }

    echo json_encode(['success' => true, 'tweets' => $formattedTweets, 'hasMore' => $hasMore]);
}

?>
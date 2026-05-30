<?php
require_once __DIR__ . '/bootstrap.php';

$userId = current_user_id();
$dateParam = isset($_GET['date']) ? trim((string)$_GET['date']) : null;
$beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
$beforeDate = isset($_GET['before_date']) ? trim((string)$_GET['before_date']) : null;
$searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : null;
$limit = 30;

$conditions = [
    't.is_deleted = 0',
    't.user_id = :user_id',
];
$params = ['user_id' => $userId];

if ($searchQuery !== null && $searchQuery !== '') {
    $conditions[] = "
        (
            t.content LIKE :search
            OR EXISTS (
                SELECT 1
                FROM tweet_tags AS tt2
                INNER JOIN tags AS tg2 ON tt2.tag_id = tg2.id
                WHERE tt2.tweet_id = t.id
                  AND tg2.name LIKE :search
            )
        )
    ";
    $params['search'] = '%' . $searchQuery . '%';
}

$baseConditions = $conditions;
$baseParams = $params;

if ($beforeId > 0) {
    $conditions[] = 't.id < :before_id';
    $params['before_id'] = $beforeId;
} elseif ($beforeDate !== null && $beforeDate !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $beforeDate)) {
        json_response(['error' => 'Invalid date format.'], 400);
    }
    $conditions[] = 't.created_at < :before_date_start';
    $params['before_date_start'] = $beforeDate . ' 00:00:00';
} elseif ($dateParam !== null && $dateParam !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
        json_response(['error' => 'Invalid date format.'], 400);
    }
    $conditions[] = 't.created_at >= :date_start';
    $conditions[] = 't.created_at < :date_end';
    $params['date_start'] = $dateParam . ' 00:00:00';
    $params['date_end'] = date('Y-m-d 00:00:00', strtotime($dateParam . ' +1 day'));
}

$whereClause = implode(' AND ', $conditions);

$tweetsResult = $dbc->fetchAll(
    "
    SELECT
        t.id,
        t.content,
        t.image_file AS imageFile,
        t.created_at AS createdAt,
        t.user_id AS userId,
        GROUP_CONCAT(tg.name SEPARATOR ',') AS tags
    FROM tweets AS t
    LEFT JOIN tweet_tags AS tt ON t.id = tt.tweet_id
    LEFT JOIN tags AS tg ON tt.tag_id = tg.id
    WHERE {$whereClause}
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT {$limit}
    ",
    $params
);

$formattedTweets = [];
$tweetsResult = array_reverse($tweetsResult);

foreach ($tweetsResult as $tweet) {
    $tweet['tags'] = empty($tweet['tags']) ? [] : explode(',', $tweet['tags']);
    $formattedTweets[] = $tweet;
}

$hasMore = false;
$baseWhereClause = implode(' AND ', $baseConditions);

if (count($formattedTweets) > 0) {
    $oldestId = (int)$formattedTweets[0]['id'];
    $checkParams = $baseParams + ['oldest_id' => $oldestId];
    $olderTweet = $dbc->fetchOne(
        "
        SELECT t.id
        FROM tweets AS t
        WHERE {$baseWhereClause}
          AND t.id < :oldest_id
        LIMIT 1
        ",
        $checkParams
    );
    $hasMore = $olderTweet !== null;
} elseif ($dateParam !== null && $dateParam !== '' && $beforeId <= 0) {
    $checkParams = $baseParams + ['date_start' => $dateParam . ' 00:00:00'];
    $olderTweet = $dbc->fetchOne(
        "
        SELECT t.id
        FROM tweets AS t
        WHERE {$baseWhereClause}
          AND t.created_at < :date_start
        LIMIT 1
        ",
        $checkParams
    );
    $hasMore = $olderTweet !== null;
}

json_response(['success' => true, 'tweets' => $formattedTweets, 'hasMore' => $hasMore]);

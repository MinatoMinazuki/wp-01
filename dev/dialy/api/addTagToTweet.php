<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)htmlspecialchars( $_SESSION['userId'] );

$tweetId = isset($_POST['tweetId']) ? (int)htmlspecialchars( $_POST['tweetId'] ) : 0;
$tagId = isset($_POST['tagId']) ? (int)htmlspecialchars( $_POST['tagId'] ) : 0;

if ($tweetId > 0 && $tagId > 0) {

    $checkSql = sprintf("
        SELECT
        `id`
        FROM
        `tweets`
        WHERE
        `id` = %s
        AND
        `user_id` = %s
        AND
        `is_deleted` = 0
        ",
        $tweetId,
        $userId
    );

    $tweet = $dbc->Dsql($checkSql);

    if( is_array($tweet) && count($tweet) > 0 ){
        $insertSql = sprintf("
            INSERT IGNORE INTO
            tweet_tags
            (
                `tweet_id`,
                `tag_id`
            ) VALUES (
                %s,
                %s
            )
            ",
            $tweetId,
            $tagId
        );

        $dbc->Dsql($insertSql);

        $tagsSql = sprintf("
            SELECT tg.`name`
            FROM tweet_tags as tt
            JOIN tags as tg ON tt.`tag_id` = tg.`id`
            WHERE tt.`tweet_id` = %s
            ORDER BY tg.`id` ASC
            ",
            $tweetId
        );
        $currentTags = $dbc->Dsql($tagsSql);

        $tagList = [];
        if( is_array($currentTags) ){
            foreach($currentTags as $r) {
                $tagList[] = $r['name'];
            }
        }

        echo json_encode(['success' => true, 'tags' => $tagList]);
    } else {
        echo json_encode(['error' => 'Not authorized or tweet not found.']);
    }
} else {
    echo json_encode(['error' => 'Invalid parameters.']);
}
?>

<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json; charset=utf-8');

if( !isset($_SESSION['userId'] ) ){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tweetId = isset($_POST['tweetId']) ? (int)htmlspecialchars( $_POST['tweetId'] ) : 0;
$userId = (int)htmlspecialchars( $_SESSION['userId'] );

if ($tweetId > 0) {
    // 自身のツイートのみ削除可能
    $sql = sprintf("
        UPDATE
        tweets
        SET
        is_deleted = 1
        WHERE
        id = %s
        AND
        user_id = %s
        ",
        $tweetId,
        $userId
    );
    $updated = $dbc->Dsql($sql);

    if ($updated) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete or not found.']);
    }
} else {
    echo json_encode(['error' => 'Invalid tweet ID.']);
}
?>

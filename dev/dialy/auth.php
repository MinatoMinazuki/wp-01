<?php
session_start();
require_once __DIR__.'/class/DBC.php';

$dbc = new DBC();

$useId = isset($_SESSION['userId']) ? htmlspecialchars( $_SESSION['userId'] ) : null;
$token = isset($_COOKIE['autoLoginToken']) ? htmlspecialchars( $_COOKIE['autoLoginToken'] ) : null;

if( !isset($useId) && isset($_COOKIE['autoLoginToken']) ){

    $sql = sprintf("
        SELECT
        user_id,
        expires_at
        FROM
        login_tokens
        WHERE
        token = '%s'
        ",
        $dbc->escape($token)
    );

    $tokenRecord = $dbc->Dsql($sql);

    if( is_array($tokenRecord) && count($tokenRecord) > 0 ){
        if (strtotime($tokenRecord[0]['expires_at']) > time()) {
            $_SESSION['userId'] = $tokenRecord[0]['user_id'];
        } else {
            $delSql = sprintf("
                DELETE FROM
                login_tokens
                WHERE
                token = '%s'
                ",
                $dbc->escape($token)
            );
            $dbc->Dsql($delSql);
            setcookie('autoLoginToken', '', time() - 3600, '/');
        }
    }
}

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$publicPages = ['login.php', 'setupUser.php'];

if (!isset($_SESSION['userId']) && !in_array($currentPage, $publicPages)) {
    header('Location: login.php');
    exit;
}
?>

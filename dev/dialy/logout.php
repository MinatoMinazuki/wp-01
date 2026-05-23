<?php
require_once __DIR__.'/class/DBC.php';
session_start();

$dbc = new DBC();

if( isset($_COOKIE['autoLoginToken']) ){
    $token = $_COOKIE['autoLoginToken'];

    $sql = sprintf("
        DELETE FROM
        login_tokens
        WHERE token = '%s'
        ",
        $dbc->escape($token)
    );

    $dbc->Dsql($sql);
    setcookie('autoLoginToken', '', time() - 3600, '/');
}

$_SESSION = array();
if( ini_get("session.use_cookies") ){
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: login.php');
exit;
?>

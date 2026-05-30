<?php
require_once __DIR__.'/class/DBC.php';
require_once __DIR__.'/includes/helpers.php';
session_start();

$dbc = new DBC();

if( isset($_COOKIE['autoLoginToken']) ){
    $token = $_COOKIE['autoLoginToken'];

    $dbc->execute("
        DELETE FROM
        login_tokens
        WHERE token = :token
        ",
        ['token' => $token]
    );

    clear_secure_cookie('autoLoginToken');
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

<?php
session_start();

require_once __DIR__ . '/../class/DBC.php';

// 自動ログイン用トークンの削除
if (isset($_COOKIE['remember_token'])) {
    $db = new DBC();
    $sql = sprintf("DELETE FROM money_user_tokens WHERE token = '%s'", $db->escape($_COOKIE['remember_token']));
    $db->Dsql($sql);
    
    // クッキーも削除
    setcookie('remember_token', '', time() - 3600, '/');
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header('Location: login.php');
exit;

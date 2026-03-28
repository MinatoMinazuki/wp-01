<?php
session_start();

require_once __DIR__ . '/../class/DBC.php';

// 1. セッションにログイン情報がない場合、クッキーを確認して自動ログインを試みる
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $db = new DBC();
    $token = $_COOKIE['remember_token'];
    
    $sql = sprintf(
        "SELECT t.user_id, u.email 
         FROM money_user_tokens t 
         JOIN money_users u ON t.user_id = u.id 
         WHERE t.token = '%s' AND t.expires_at > NOW()",
        $db->escape($token)
    );
    $results = $db->select($sql);
    
    if (!empty($results)) {
        // トークンが有効ならログイン状態を復元
        $_SESSION['user_id'] = $results[0]['user_id'];
        $_SESSION['user_email'] = $results[0]['email'];
    }
}

// 2. ログインしていなければログインページへリダイレクト
if (!isset($_SESSION['user_id'])) {
    $pathPrefix = strpos($_SERVER['SCRIPT_NAME'], '/list/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/dashboard/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/manual/') !== false ? '../' : '';
    header('Location: ' . $pathPrefix . 'auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];

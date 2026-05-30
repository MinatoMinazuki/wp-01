<?php
session_start();
require_once __DIR__.'/class/DBC.php';
require_once __DIR__.'/includes/helpers.php';

$dbc = new DBC();

$userId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : null;
$token = isset($_COOKIE['autoLoginToken']) ? $_COOKIE['autoLoginToken'] : null;

if ($userId === null && $token !== null) {

    $tokenRecord = $dbc->fetchOne("
        SELECT
        user_id,
        expires_at
        FROM
        login_tokens
        WHERE
        token = :token
        ",
        ['token' => $token]
    );

    if ($tokenRecord !== null) {
        if (strtotime($tokenRecord['expires_at']) > time()) {
            $_SESSION['userId'] = (int)$tokenRecord['user_id'];
        } else {
            $dbc->execute("
                DELETE FROM
                login_tokens
                WHERE
                token = :token
                ",
                ['token' => $token]
            );
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

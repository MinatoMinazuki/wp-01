<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['userId'])) {
    header('Location: index.php');
    exit;
}

$errorMsg = '';
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $loginId = isset($_POST['loginId']) ? trim((string)$_POST['loginId']) : '';
    $password = isset($_POST['password']) ? trim((string)$_POST['password']) : '';

    if ($loginId === '' || $password === '') {
        $errorMsg = 'IDとパスワードを入力してください。';
    } else {
        $user = $dbc->fetchOne(
            "
            SELECT id, password
            FROM users
            WHERE login_id = :login_id
            ",
            ['login_id' => $loginId]
        );

        if ($user !== null && password_verify($password, $user['password'])) {
            $userId = (int)$user['id'];
            $_SESSION['userId'] = $userId;
            create_login_token($dbc, $userId);

            header('Location: index.php');
            exit;
        }

        $errorMsg = 'IDまたはパスワードが違います。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Diary</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="loginWrapper">
        <h2 class="authTitle">Diary Login</h2>
        <div class="loginBox">
            <?php if ($errorMsg): ?>
                <div class="errorMsg"><?= h($errorMsg) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrfToken" value="<?= h($csrfToken) ?>">
                <div class="inputGroup">
                    <label for="loginId">ログインID</label>
                    <input type="text" id="loginId" name="loginId" required>
                </div>
                <div class="inputGroup">
                    <label for="password">パスワード</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="loginSubmitBtn">ログイン</button>
                <div class="authLinks">
                    <a href="register.php" class="authLinkText">新規アカウント作成はこちら</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

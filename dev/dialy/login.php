<?php
require_once __DIR__.'/auth.php';

if (isset($_SESSION['userId'])) {
    header('Location: index.php');
    exit;
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = isset($_POST['loginId']) ? htmlspecialchars( $_POST['loginId'] ) : '';
    $loginId = trim($loginId);

    $password = isset($_POST['password']) ? htmlspecialchars( $_POST['password'] ) : '';
    $password = trim($password);


    if ($loginId !== '' && $password !== '') {
        $sql = sprintf("
            SELECT
            id,
            password
            FROM
            users
            WHERE
            login_id = '%s'
            ",
            $dbc->escape($loginId)
        );

        $user = $dbc->Dsql($sql);

        if( is_array($user) && count($user) > 0 ){
            if( password_verify($password, $user[0]['password']) ){
                $userId = $user[0]['id'];
                $_SESSION['userId'] = $userId;

                $token = bin2hex(random_bytes(100));
                $expiresTime = time() + (90 * 24 * 60 * 60);
                $expiresAt = date('Y-m-d H:i:s', $expiresTime);

                $sqlToken = sprintf("
                    INSERT INTO
                    login_tokens
                    (
                        user_id,
                        token,
                        expires_at
                    ) VALUES (
                        {$userId},
                        '%s',
                        '%s'
                    )
                    ",
                    $dbc->escape($token),
                    $expiresAt
                );

                $dbc->Dsql($sqlToken);

                setcookie('autoLoginToken', $token, $expiresTime, '/');

                header('Location: index.php');
                exit;
            } else {
                $errorMsg = 'パスワードが間違っています。';
            }
        } else {
            $errorMsg = 'IDが見つかりません。';
        }
    } else {
        $errorMsg = 'IDとパスワードを入力してください。';
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
                <div class="errorMsg">
                    <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
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

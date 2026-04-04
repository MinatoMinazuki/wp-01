<?php
session_start();
require_once __DIR__.'/class/DBC.php';

if (isset($_SESSION['userId'])) {
    header('Location: index.php');
    exit;
}

$dbc = new DBC();
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = isset($_POST['loginId']) ? htmlspecialchars( $_POST['loginId'] ) : '';
    $loginId = trim($loginId);

    $password = isset($_POST['password']) ? htmlspecialchars( $_POST['password'] ) : '';


    if (strlen($loginId) < 6) {
        $errorMsg = 'ログインIDは6文字以上で入力してください。';
    } elseif (strlen($password) < 8) {
        $errorMsg = 'パスワードは8文字以上で入力してください。';
    } else {
        $sqlCheck = sprintf("
            SELECT
            id
            FROM
            users
            WHERE
            login_id = '%s'
            ",
            $dbc->escape($loginId)
        );

        $existing = $dbc->Dsql($sqlCheck);
        if( is_array($existing) && count($existing) > 0 ){
            $errorMsg = 'このログインIDは既に使用されています。';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sqlInsert = sprintf("
                INSERT INTO
                users
                (
                    login_id,
                    password
                ) VALUES (
                    '%s',
                    '%s'
                ",
                $dbc->escape($loginId),
                $dbc->escape($passwordHash)
            );

            $insertId = $dbc->Dsql($sqlInsert);

            if ($insertId) {
                $_SESSION['userId'] = $insertId;

                $token = bin2hex(random_bytes(100));
                $expiresTime = time() + (90 * 24 * 60 * 60);

                $sqlToken = sprintf("
                    INSERT INTO
                    login_tokens
                    (
                        user_id,
                        token
                    ) VALUES (
                        %s,
                        '%s',
                        '%s'
                    )
                    ",
                    $insertId,
                    $dbc->escape($token),
                );

                $dbc->Dsql($sqlToken);

                setcookie('autoLoginToken', $token, $expiresTime, '/');

                header('Location: index.php');
                exit;
            } else {
                $errorMsg = '登録に失敗しました。';
            }
        }
    }
} else {
    // Generate initial preset values
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $_POST['loginId'] = substr(str_shuffle($chars), 0, 8);
    $_POST['password'] = substr(str_shuffle($chars), 0, 12);
}

$loginIdVal = isset($_POST['loginId']) ? $_POST['loginId'] : '';
$passwordVal = isset($_POST['password']) ? $_POST['password'] : '';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>新規登録 - Diary</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background-color: #f0ede5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif; }
        .loginWrapper { background-color: transparent; width: 100%; max-width: 480px; padding: 20px; }
        .loginBox { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); width: 100%; border-top: 5px solid #7494c0; }
        .notice { font-size: 13px; color: #555; background: #eaf4fa; padding: 12px; border-radius: 4px; margin-bottom: 20px; line-height: 1.5; border-left: 4px solid #3498db; }
    </style>
</head>
<body>
    <div class="loginWrapper">
        <h2 style="text-align:center; color: #2c3e50; margin-bottom: 20px;">新規アカウント登録</h2>
        <div class="loginBox">
            <?php if ($errorMsg): ?><div class="errorMsg"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>
            <div class="notice">
                ランダムなIDとパスワードが自動入力されています。このまま登録を進めるか、お好きな文字に変更してください。<br>
                (ID: 6文字以上, パスワード: 8文字以上)
            </div>
            <form method="POST">
                <div class="inputGroup">
                    <label for="loginId">ログインID</label>
                    <input type="text" id="loginId" name="loginId" value="<?= htmlspecialchars($loginIdVal) ?>" required minlength="6">
                </div>
                <div class="inputGroup">
                    <label for="password">パスワード</label>
                    <input type="text" id="password" name="password" value="<?= htmlspecialchars($passwordVal) ?>" required minlength="8">
                </div>
                <button type="submit" class="loginSubmitBtn">登録してログイン</button>
                <div style="text-align:center; margin-top: 15px;">
                    <a href="login.php" style="color: #0084ff; text-decoration: none; font-size: 14px;">ログイン画面に戻る</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

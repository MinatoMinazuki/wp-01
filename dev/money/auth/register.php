<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/DBC.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'メールアドレスとパスワードを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } elseif (strlen($password) < 6) {
        $error = 'パスワードは6文字以上で入力してください。';
    } else {
        $db = new DBC();

        // 重複チェック
        $sqlCheck = sprintf("SELECT id FROM money_users WHERE email = '%s'", $db->escape($email));
        $exists = $db->select($sqlCheck);

        if ($exists) {
            $error = 'このメールアドレスは既に登録されています。';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sqlInsert = sprintf(
                "INSERT INTO money_users (email, password) VALUES ('%s', '%s')",
                $db->escape($email),
                $hashedPassword
            );
            $db->Dsql($sqlInsert);
            $success = '登録が完了しました。ログインしてください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - 家計簿アプリ</title>
    <link rel="stylesheet" href="../src/css/common.css">
</head>
<body>

<div class="auth-card">
    <h1 class="title-with-border">新規アカウント登録</h1>
    
    <?php if ($error): ?>
        <div class="message msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message msg-success">
            <?= htmlspecialchars($success) ?><br>
            <a href="login.php" class="btn btn-success btn-full" style="margin-top: 15px; display: block; text-decoration: none;">ログイン画面へ</a>
        </div>
    <?php else: ?>
        <form action="" method="post">
            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" class="form-input" required autofocus>
            </div>
            
            <div class="form-group">
                <label>パスワード (6文字以上)</label>
                <input type="password" name="password" class="form-input" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top: 20px;">登録する</button>
        </form>

        <div style="margin-top: 25px; text-align: center; font-size: 14px;">
            <p>既にアカウントをお持ちの方は <a href="login.php" style="color: var(--primary-color); font-weight: bold; text-decoration: none;">ログイン</a></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

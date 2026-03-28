<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/DBC.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'メールアドレスとパスワードを入力してください。';
    } else {
        $db = new DBC();
        $sql = sprintf("SELECT * FROM money_users WHERE email = '%s' LIMIT 1", $db->escape($email));
        $users = $db->select($sql);

        if ($users && password_verify($password, $users[0]['password'])) {
            $_SESSION['user_id'] = $users[0]['id'];
            $_SESSION['user_email'] = $users[0]['email'];
            header('Location: ../index.php');
            exit;
        } else {
            $error = 'メールアドレスまたはパスワードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 家計簿アプリ</title>
    <link rel="stylesheet" href="../src/css/common.css">
</head>
<body>

<div class="auth-card">
    <h1 class="title-with-border">家計簿 ログイン</h1>
    
    <?php if ($error): ?>
        <div class="message msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="post">
        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" name="email" class="form-input" required autofocus>
        </div>
        
        <div class="form-group">
            <label>パスワード</label>
            <input type="password" name="password" class="form-input" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 20px;">ログイン</button>
    </form>

    <div style="margin-top: 25px; text-align: center; font-size: 14px;">
        <p>アカウントをお持ちでない方は <a href="register.php" style="color: var(--primary-color); font-weight: bold; text-decoration: none;">新規登録</a></p>
    </div>
</div>

</body>
</html>

<?php
session_start();
require_once __DIR__ . '/class/DBC.php';
require_once __DIR__ . '/includes/helpers.php';

if (isset($_SESSION['userId'])) {
    header('Location: index.php');
    exit;
}

$dbc = new DBC();
$errorMsg = '';
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $loginId = isset($_POST['loginId']) ? trim((string)$_POST['loginId']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if (strlen($loginId) < 6 || strlen($loginId) > 20) {
        $errorMsg = 'ログインIDは6文字以上20文字以内で入力してください。';
    } elseif (strlen($password) < 8 || strlen($password) > 32) {
        $errorMsg = 'パスワードは8文字以上32文字以内で入力してください。';
    } else {
        $existing = $dbc->fetchOne(
            "
            SELECT id
            FROM users
            WHERE login_id = :login_id
            ",
            ['login_id' => $loginId]
        );

        if ($existing !== null) {
            $errorMsg = 'このログインIDはすでに使用されています。';
        } else {
            $insertId = (int)$dbc->insert(
                "
                INSERT INTO users (login_id, password)
                VALUES (:login_id, :password)
                ",
                [
                    'login_id' => $loginId,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]
            );

            if ($insertId > 0) {
                $_SESSION['userId'] = $insertId;
                create_login_token($dbc, $insertId);

                header('Location: index.php');
                exit;
            }

            $errorMsg = '登録に失敗しました。';
        }
    }
} else {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $_POST['loginId'] = substr(str_shuffle($chars), 0, 8);
    $_POST['password'] = substr(str_shuffle($chars), 0, 12);
}

$loginIdVal = isset($_POST['loginId']) ? (string)$_POST['loginId'] : '';
$passwordVal = isset($_POST['password']) ? (string)$_POST['password'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>新規登録 - Diary</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="loginWrapper">
        <h2 class="authTitle">新規アカウント登録</h2>
        <div class="loginBox">
            <?php if ($errorMsg): ?>
                <div class="errorMsg"><?= h($errorMsg) ?></div>
            <?php endif; ?>

            <div class="notice">
                IDとパスワードは自由に変更できます。<br>
                ID: 6文字以上20文字以内 / パスワード: 8文字以上32文字以内
            </div>

            <form method="POST">
                <input type="hidden" name="csrfToken" value="<?= h($csrfToken) ?>">
                <div class="inputGroup">
                    <label for="loginId">ログインID</label>
                    <div class="inputCopyWrap">
                        <input type="text" id="loginId" name="loginId" value="<?= h($loginIdVal) ?>" required minlength="6" maxlength="20">
                        <button type="button" class="copyBtn" onclick="copyToClipboard('loginId', this)" title="コピー"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div class="inputGroup">
                    <label for="password">パスワード</label>
                    <div class="inputCopyWrap">
                        <input type="text" id="password" name="password" value="<?= h($passwordVal) ?>" required minlength="8" maxlength="32">
                        <button type="button" class="copyBtn" onclick="copyToClipboard('password', this)" title="コピー"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <button type="submit" class="loginSubmitBtn">登録してログイン</button>
                <div class="authLinks">
                    <a href="login.php" class="authLinkText">ログイン画面に戻る</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function copyToClipboard(inputId, btnElem) {
            const copyText = document.getElementById(inputId);
            if (!copyText || !copyText.value) return;

            const icon = btnElem.querySelector('i');
            const originalClass = icon.className;

            const showSuccess = function() {
                icon.className = 'fas fa-check';
                icon.style.color = '#27ae60';
                setTimeout(() => {
                    icon.className = originalClass;
                    icon.style.color = '';
                }, 1500);
            };

            if (navigator.clipboard) {
                navigator.clipboard.writeText(copyText.value).then(showSuccess).catch(function() {
                    fallbackCopy(copyText, showSuccess);
                });
            } else {
                fallbackCopy(copyText, showSuccess);
            }
        }

        function fallbackCopy(inputElem, successCallback) {
            inputElem.select();
            inputElem.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                successCallback();
            } catch (err) {
                alert('コピーに失敗しました。手動でコピーしてください。');
            }
            window.getSelection().removeAllRanges();
        }
    </script>
</body>
</html>

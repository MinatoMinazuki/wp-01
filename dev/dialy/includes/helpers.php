<?php

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function current_user_id(): int
{
    return isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrfToken'])) {
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrfToken'];
}

function posted_csrf_token(): string
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($headerToken !== '') {
        return (string)$headerToken;
    }

    return isset($_POST['csrfToken']) ? (string)$_POST['csrfToken'] : '';
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Invalid request method.'], 405);
    }
}

function require_csrf(): void
{
    $token = posted_csrf_token();

    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        json_response(['error' => 'Invalid CSRF token.'], 403);
    }
}

function create_login_token(DBC $dbc, int $userId): string
{
    $token = bin2hex(random_bytes(100));
    $expiresTime = time() + (90 * 24 * 60 * 60);
    $expiresAt = date('Y-m-d H:i:s', $expiresTime);

    $dbc->insert(
        "
        INSERT INTO login_tokens (user_id, token, expires_at)
        VALUES (:user_id, :token, :expires_at)
        ",
        [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]
    );

    setcookie('autoLoginToken', $token, $expiresTime, '/', '', false, true);

    return $token;
}

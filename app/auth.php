<?php

declare(strict_types=1);

function attempt_login(string $email, string $password, bool $remember): bool
{
    $stmt = db()->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    login_user($user, $remember);
    return true;
}

function login_user(array $user, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    if (!$remember) {
        clear_remember_cookie();
        return;
    }

    $token = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $token);

    db()->prepare('UPDATE users SET remember_token = :remember_token WHERE id = :id')
        ->execute(['remember_token' => $tokenHash, 'id' => $user['id']]);

    setcookie(
        app_config()['cookie_name'],
        $token,
        [
            'expires' => time() + (app_config()['cookie_days'] * 86400),
            'path' => '/',
            'httponly' => true,
            'secure' => app_config()['cookie_secure'],
            'samesite' => 'Lax',
        ]
    );
}

function restore_remembered_session(): void
{
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $cookie = $_COOKIE[app_config()['cookie_name']] ?? null;
    if (!$cookie) {
        return;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE remember_token = :remember_token LIMIT 1');
    $stmt->execute(['remember_token' => hash('sha256', (string) $cookie)]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = (int) $user['id'];
        return;
    }

    clear_remember_cookie();
}

function clear_remember_cookie(): void
{
    setcookie(app_config()['cookie_name'], '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => app_config()['cookie_secure'],
        'samesite' => 'Lax',
    ]);
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);

    return $stmt->fetch() ?: null;
}

function require_login(): void
{
    if (current_user() === null) {
        flash('error', 'Please login first.');
        redirect('login');
    }
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        db()->prepare('UPDATE users SET remember_token = NULL WHERE id = :id')->execute(['id' => $user['id']]);
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    clear_remember_cookie();
}

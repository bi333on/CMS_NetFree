<?php

declare(strict_types=1);

namespace NetFree;

use PDO;

/**
 * Аутентификация администратора.
 */
class Auth
{
    public function check(): bool
    {
        return Session::get('auth_user_id') !== null;
    }

    public function user(): ?array
    {
        $id = Session::get('auth_user_id');
        if ($id === null) {
            return null;
        }
        return Database::first('SELECT * FROM users WHERE id = ?', [(int) $id]);
    }

    public function attempt(string $username, string $password): bool
    {
        $user = Database::first('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        Session::regenerate();
        Session::set('auth_user_id', (int) $user['id']);
        return true;
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::regenerate();
    }
}

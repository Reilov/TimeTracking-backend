<?php

namespace Support;

class SessionManager
{
    public static function start(): void
    {
        // Настройки ДО старта сессии
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_domain', 'localhost');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.cookie_secure', '0');
            session_start();
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function getUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function setUser(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    public static function updateUser(array $partialData): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = [];
        }
        $_SESSION['user'] = array_merge($_SESSION['user'], $partialData);
    }
}

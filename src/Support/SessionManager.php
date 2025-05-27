<?php

namespace Support;

class SessionManager
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $domain = ($_SERVER['HTTP_HOST'] === 'localhost')
                ? 'localhost'
                : '.dfaaqq.duckdns.org';

            $lifetime = 60 * 60 * 24 * 7; // 7 дней

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => $domain,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ]);

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

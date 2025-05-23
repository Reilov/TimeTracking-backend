<?php

namespace Action;

use Support\SessionManager;
use Responder\JsonResponder;

class LogoutAction
{
    public static function handle(): void
    {
        try {
            // Уничтожаем текущую сессию
            SessionManager::destroy();

            // Очищаем все данные сессии
            $_SESSION = [];

            // Удаляем куку сессии
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            // Отправляем успешный ответ
            JsonResponder::success(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            // Логируем ошибку
            error_log('Logout error: ' . $e->getMessage());

            // Отправляем ошибку клиенту
            JsonResponder::error('Logout failed. Please try again.');
        }
    }
}

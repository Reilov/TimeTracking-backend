<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;

class UsersAction
{
    public static function handle(): void
    {
        $pdo = Database::getConnection();
        $userId = self::getRequestedUserId();

        if ($userId) {
            // Запрос для конкретного пользователя
            self::handleSingleUser($pdo, $userId);
        } else {
            // Запрос для всех пользователей
            self::handleAllUsers($pdo);
        }
    }

    private static function handleSingleUser(\PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                u.email,
                u.position_id,
                u.department_id,
                u.birth_date,
                u.phone,
                u.about,
                u.avatar_path,
                p.name as position_name,
                d.name as department_name
            FROM users u
            LEFT JOIN positions p ON u.position_id = p.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?
        ");

        $stmt->execute([$userId]);

        $user = $stmt->fetch();

        if ($user) {
            $user['avatar'] = $user['avatar_path']
                ? 'http://' . $_SERVER['HTTP_HOST'] . '/public/storage/avatars/' . $user['avatar_path']
                : null;

            unset($user['avatar_path']);

            JsonResponder::success(['user' => $user]);
        } else {
            JsonResponder::error('User not found', 404);
        }
    }

    private static function handleAllUsers(\PDO $pdo): void
    {
        SessionManager::start();
        $currentUserId = SessionManager::getUser() ? $_SESSION['user']['id'] : null;

        $sql = "
        SELECT
            u.id,
            u.name,
            u.email,
            u.position_id,
            u.department_id,
            u.avatar_path,
            p.name AS position_name,
            d.name AS department_name,
            ws.status
        FROM users u
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN (
            SELECT ws1.*
            FROM work_sessions ws1
            INNER JOIN (
                SELECT user_id, MAX(start_time) AS max_start
                FROM work_sessions
                GROUP BY user_id
            ) latest_ws ON ws1.user_id = latest_ws.user_id AND ws1.start_time = latest_ws.max_start
        ) ws ON ws.user_id = u.id
    ";

        $params = [];

        if ($currentUserId !== null) {
            $sql .= " WHERE u.id != ?";
            $params[] = $currentUserId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            $user['avatar'] = $user['avatar_path']
                ? 'https://' . $_SERVER['HTTP_HOST'] . '/public/storage/avatars/' . $user['avatar_path']
                : null;
            unset($user['avatar_path']);
        }

        JsonResponder::success(['users' => $users]);
    }

    private static function getRequestedUserId(): ?int
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $parts = explode('/', $path);
        $id = end($parts);

        return is_numeric($id) ? (int)$id : null;
    }
}

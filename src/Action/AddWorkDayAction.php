<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;
use DateTime;

class AddWorkDayAction
{
    public static function handle()
    {
        SessionManager::start();
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'];
        $status = $input['status'];
        $date = $input['date'];
        $time = $input['elapsed_seconds'];
        $comment = $input['comment'];

        try {
            $pdo = Database::getConnection();

            if (!self::userExists($pdo, $userId)) {
                JsonResponder::error('Пользователь не найден', 404);
                return;
            }
            if ($status == "day_off") {
                self::addEventDayOff($pdo, $userId, $date, $status, $comment);
            } else {
                self::addEventWorkDay($pdo, $userId, $date, $status, $time);
            }

            JsonResponder::success(['message' => 'Событие добавлено успешно']);
        } catch (\Exception $e) {
            JsonResponder::error('Ошибка при добавлении события: ' . $e->getMessage(), 500);
        }
    }

    private static function addEventDayOff($pdo, $userId, $date, $status, $comment)
    {
        $stmt = $pdo->prepare("
            INSERT INTO user_day_events (user_id, start_date, end_date, type, comment)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $date,
            $date,
            $status,
            $comment
        ]);
    }
    private static function addEventWorkDay($pdo, $userId, $date, $status, $time)
    {
        $stmt = $pdo->prepare("
            INSERT INTO work_sessions (user_id, date, start_time, end_time, total_worked_seconds, status)
            VALUES (?, ?, NOW(), NOW(), ?, ?)
        ");

        $stmt->execute([
            $userId,
            $date,
            $time,
            $status
        ]);
    }
    private static function userExists($pdo, $userId)
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
}

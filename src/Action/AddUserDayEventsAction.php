<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;
use DateTime;

class AddUserDayEventsAction
{
    public static function handle()
    {
        SessionManager::start();

        // Предположим, данные приходят через POST
        $input = json_decode(file_get_contents('php://input'), true);

        if (!self::validateInput($input)) {
            JsonResponder::error('Неверные данные', 400);
            return;
        }

        $userId = $input['user_id'];
        $startDate = $input['start_date'];
        $endDate = $input['end_date'];
        $type = $input['type'];
        $comment = $input['comment'] ?? null;

        try {
            $pdo = Database::getConnection();

            if (!self::userExists($pdo, $userId)) {
                JsonResponder::error('Пользователь не найден', 404);
                return;
            }

            self::addEvent($pdo, $userId, $startDate, $endDate, $type, $comment);

            JsonResponder::success(['message' => 'Событие добавлено успешно']);
        } catch (\Exception $e) {
            JsonResponder::error('Ошибка при добавлении события: ' . $e->getMessage(), 500);
        }
    }

    private static function validateInput($input)
    {
        return isset($input['user_id'], $input['start_date'], $input['end_date'], $input['type']) &&
            self::isValidDate($input['start_date']) &&
            self::isValidDate($input['end_date']);
    }

    private static function isValidDate($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private static function userExists($pdo, $userId)
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }

    private static function addEvent($pdo, $userId, $startDate, $endDate, $type, $comment)
    {
        $stmt = $pdo->prepare("
            INSERT INTO user_day_events (user_id, start_date, end_date, type, comment)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $startDate,
            $endDate,
            $type,
            $comment
        ]);
    }
}

<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;

namespace Action;

use Support\Database;
use Responder\JsonResponder;

class TurnstileAction
{
    private const MAX_SHORT_EXIT_MINUTES = 30; // Макс время короткого выхода

    public static function handle(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $action = $input['action'] ?? null;
            $userId = (int)($input['user_id'] ?? 0);
            $exitType = $input['exit_type'] ?? null; // Тип выхода: 'short'|'lunch'|'final'
            $today = date('Y-m-d');

            if (!$userId) {
                throw new \InvalidArgumentException('User ID is required');
            }

            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $workSession = self::getCurrentWorkSession($pdo, $userId, $today);

            switch ($action) {
                case 'enter':
                    self::handleEnter($pdo, $userId, $today, $workSession);
                    break;

                case 'exit':
                    self::handleExit($pdo, $workSession, $exitType);
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid action');
            }

            $pdo->commit();
            JsonResponder::success(['status' => 'success']);
        } catch (\Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            JsonResponder::error($e->getMessage());
        }
    }

    private static function getCurrentWorkSession(\PDO $pdo, int $userId, string $today): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM work_sessions 
            WHERE user_id = ? AND date = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $today]);
        return $stmt->fetch() ?: null;
    }

    private static function handleEnter(\PDO $pdo, int $userId, string $today, ?array $session): void
    {
        if ($session) {
            if ($session['status'] === 'active') {
                throw new \RuntimeException('Сессия уже активна');
            }

            if ($session['status'] === 'paused') {
                // Возобновление после обеда
                $stmt = $pdo->prepare("
                    UPDATE work_sessions SET
                        status = 'active',
                        last_resumed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$session['id']]);
                return;
            }
        }

        // Новая сессия
        $stmt = $pdo->prepare("
            INSERT INTO work_sessions 
            (user_id, date, start_time, status, total_worked_seconds) 
            VALUES (?, ?, NOW(), 'active', 0)
        ");
        $stmt->execute([$userId, $today]);
    }

    private static function handleExit(\PDO $pdo, ?array $session, ?string $exitType): void
    {
        if (!$session || $session['status'] !== 'active') {
            throw new \RuntimeException('Нет активной сессии');
        }
        // var_dump($session);
        // die();
        // Фиксируем выход в лог
        $stmt = $pdo->prepare("
            INSERT INTO turnstile_logs 
            (session_id, action, exit_type, time) 
            VALUES (?, 'exit', ?, NOW())
        ");
        $stmt->execute([$session['id'], $exitType]);

        // Обработка разных типов выходов
        switch ($exitType) {
            case 'lunch':
                // Пауза для обеда
                $stmt = $pdo->prepare("
                    UPDATE work_sessions SET
                        status = 'paused',
                        last_paused_at = NOW(),
                        total_worked_seconds = total_worked_seconds + ?
                    WHERE id = ?
                ");
                $workedSeconds = self::calculateWorkedSeconds($session);
                $stmt->execute([$workedSeconds, $session['id']]);
                break;

            case 'final':
                // Завершение рабочего дня
                $stmt = $pdo->prepare("
                    UPDATE work_sessions SET
                        status = 'completed',
                        end_time = NOW(),
                        total_worked_seconds = total_worked_seconds + ?
                    WHERE id = ?
                ");
                $workedSeconds = self::calculateWorkedSeconds($session);
                $stmt->execute([$workedSeconds, $session['id']]);
                break;

            case 'short':
            default:
                // Короткий выход - ничего не меняем в сессии
                break;
        }
    }

    private static function calculateWorkedSeconds(array $session): int
    {
        $startTime = new \DateTime($session['last_resume_time'] ?? $session['start_time']);
        $now = new \DateTime();
        return $now->getTimestamp() - $startTime->getTimestamp();
    }
}

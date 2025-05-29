<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;

class SessionAction
{
    public static function handle(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? null;
            $userId = (int)($input['user_id'] ?? 0);
            $today = date('Y-m-d');

            if (!$userId) {
                throw new \InvalidArgumentException('User ID is required');
            }

            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $session = self::getCurrentSession($pdo, $userId, $today);

            switch ($action) {
                case 'start':
                    self::handleStart($pdo, $userId, $today, $input, $session);
                    break;

                case 'pause':
                    self::handlePause($pdo, $input, $session);
                    break;

                case 'stop':
                    self::handleStop($pdo, $input, $session);
                    break;

                case 'add_time':
                    self::handleAddTime($pdo, $userId, $today, $input, $session);
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid action');
            }

            $pdo->commit();
            JsonResponder::success(['status' => 'success']);
        } catch (\Exception $e) {
            $pdo->rollBack();
            JsonResponder::error($e->getMessage());
        }
    }

    private static function getCurrentSession(\PDO $pdo, int $userId, string $today): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM work_sessions 
            WHERE user_id = ? AND date = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $today]);
        return $stmt->fetch() ?: null;
    }


    private static function handleStart(\PDO $pdo, int $userId, string $today, array $input, ?array $session): void
    {
        $elapsedSeconds = (int)($input['elapsed_seconds'] ?? 0);


        if ($session && $session['status'] === 'active') {
            throw new \RuntimeException('Session already active');
        }

        if ($session && $session['status'] === 'paused') {
            $lastPausedAt = strtotime($session['last_paused_at']);
            $now = time();
            $pauseDuration = $now - $lastPausedAt;

            $stmt = $pdo->prepare("
                UPDATE work_sessions SET
                    status = 'active',
                    last_paused_at = NULL,
                    total_paused_seconds = total_paused_seconds + ?,
                    start_time = NOW() - INTERVAL ? SECOND,
                    total_worked_seconds = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $pauseDuration,
                $elapsedSeconds,
                $elapsedSeconds,
                $session['id']
            ]);
        } else {
            $stmt = $pdo->prepare("
            INSERT INTO work_sessions 
            (user_id, date, start_time, status, total_worked_seconds) 
            VALUES (?, ?, NOW(), 'active', ?)
        ");
            $stmt->execute([$userId, $today, $elapsedSeconds]);
        }
    }

    private static function handlePause(\PDO $pdo, array $input, ?array $session): void
    {
        if (!$session || $session['status'] !== 'active') {
            throw new \RuntimeException('No active session to pause');
        }

        $stmt = $pdo->prepare("
            UPDATE work_sessions SET
                status = 'paused',
                last_paused_at = NOW(),
                total_worked_seconds = ?
            WHERE id = ?
        ");
        $stmt->execute([$input['elapsed_seconds'], $session['id']]);
    }

    private static function handleStop(\PDO $pdo, array $input, ?array $session): void
    {
        if (!$session) {
            throw new \RuntimeException('Session not found');
        }

        $totalSeconds = $session['status'] === 'paused'
            ? $session['total_worked_seconds']
            : $input['elapsed_seconds'];

        $stmt = $pdo->prepare("
            UPDATE work_sessions SET
                status = 'completed',
                end_time = NOW(),
                total_worked_seconds = ?
            WHERE id = ?
        ");
        $stmt->execute([$totalSeconds, $session['id']]);
    }

    private static function handleAddTime(\PDO $pdo, int $userId, string $today, array $input, ?array $session): void
    {
        $newTotalSeconds = (int)($input['elapsed_seconds'] ?? 0);
        if ($newTotalSeconds <= 0) {
            throw new \InvalidArgumentException('Неверное количество времени для добавления');
        }

        if (!$session) {
            // Создаем новую сессию с добавленным временем
            $stmt = $pdo->prepare("
            INSERT INTO work_sessions 
            (user_id, date, start_time, status, total_worked_seconds) 
            VALUES (?, ?, NOW(), 'paused', ?)
        ");
            $stmt->execute([$userId, $today, $newTotalSeconds]);
        } else {
            // Обновляем существующую сессию
            // Теперь принимаем уже готовую сумму (старое время + добавленное)
            $stmt = $pdo->prepare("
            UPDATE work_sessions SET
                total_worked_seconds = ?,
                status = IF(status = 'completed', 'paused', status)
            WHERE id = ?
        ");
            $stmt->execute([$newTotalSeconds, $session['id']]);
        }
    }
}

<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;

class GetActiveSessionAction
{
    public static function handle(): void
    {
        try {
            $userId = (int)$_GET['user_id'];
            $today = date('Y-m-d');

            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("
                SELECT 
                    *,
                    CASE
                        WHEN status = 'paused' THEN total_worked_seconds
                        WHEN status = 'active' THEN 
                            TIMESTAMPDIFF(SECOND, start_time, NOW())
                        ELSE 0
                    END as elapsed_seconds
                FROM work_sessions 
                WHERE user_id = ? AND date = ?
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([$userId, $today]);
            $session = $stmt->fetch();

            JsonResponder::success($session ? [
                'active' => $session['status'] === 'active',
                'elapsed_seconds' => max(0, (int)$session['elapsed_seconds']), // Гарантируем неотрицательное значение
                'status' => $session['status']
            ] : [
                'active' => false,
                'elapsed_seconds' => 0
            ]);
        } catch (\Exception $e) {
            JsonResponder::error('Failed to get session: ' . $e->getMessage());
        }
    }
}

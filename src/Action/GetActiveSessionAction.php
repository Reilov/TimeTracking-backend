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
                    TIMESTAMPDIFF(SECOND, start_time, NOW()) - total_paused_seconds as current_seconds,
                    CASE 
                        WHEN status = 'paused' THEN total_worked_seconds
                        ELSE TIMESTAMPDIFF(SECOND, start_time, NOW()) - total_paused_seconds
                    END as elapsed_seconds
                FROM work_sessions 
                WHERE user_id = ? AND date = ? AND status IN ('active', 'paused')
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([$userId, $today]);
            $session = $stmt->fetch();

            JsonResponder::success($session ? [
                'active' => $session['status'] === 'active',
                'elapsed_seconds' => (int)$session['elapsed_seconds'],
                'status' => $session['status'],
                'last_paused_at' => $session['last_paused_at']
            ] : ['active' => false]);
        } catch (\Exception $e) {
            JsonResponder::error('Failed to get session: ' . $e->getMessage());
        }
    }
}

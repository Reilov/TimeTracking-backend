<?php

namespace Action\Timer;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;

class GetWeeklyStatsAction
{
    public static function handle()
    {
        SessionManager::start();
        $userId = self::getRequestedUserId();

        if ($userId) {
            $user['id'] = $userId;
        } else {
            $user = SessionManager::getUser();
        }
        $pdo = Database::getConnection();

        // Запрос с исключением выходных
        $stmt = $pdo->prepare("
            SELECT
                total_worked_seconds, date
            FROM 
                work_sessions 
            WHERE 
                user_id = ? 
                AND status = 'completed'
                AND date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                AND DAYOFWEEK(date) NOT IN (1, 7)
        ");

        $stmt->execute([$user['id']]);
        $stats = $stmt->fetchAll();

        $totalSeconds = array_reduce($stats, function ($carry, $session) {
            return $carry + $session['total_worked_seconds'];
        }, 0);

        $totalHours = round($totalSeconds / 3600, 2);
        $workingDaysCount = count(array_unique(array_column($stats, 'date')));
        $averageHoursPerDay = $workingDaysCount > 0
            ? round($totalHours / $workingDaysCount, 2)
            : 0;

        JsonResponder::success([
            'stats' => $stats,
            'total_hours' => $totalHours,
            'avg_hours' => $averageHoursPerDay,
            'working_days_count' => $workingDaysCount
        ]);
    }

    private static function getRequestedUserId(): ?int
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $parts = explode('/', $path);
        $id = end($parts);

        return is_numeric($id) ? (int)$id : null;
    }
}

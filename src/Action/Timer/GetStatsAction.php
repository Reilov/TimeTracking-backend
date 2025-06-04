<?php

namespace Action\Timer;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;
use InvalidArgumentException;
use DateTime;
use DateInterval;

class GetStatsAction
{
    public static function handle()
    {
        SessionManager::start();
        $userId = self::getRequestedUserId();
        $period = self::getRequestedPeriod();

        $user = $userId ? ['id' => $userId] : SessionManager::getUser();

        $pdo = Database::getConnection();
        $queryParams = [$user['id']];

        // Получаем start_date и end_date на бэке
        [$dateCondition, $startDate, $endDate] = self::buildDateCondition($period, $queryParams);

        $stmt = $pdo->prepare("
        SELECT
            SUM(total_worked_seconds) as total_worked_seconds, 
            date
        FROM 
            work_sessions 
        WHERE 
            user_id = ? 
            AND status = 'completed'
            {$dateCondition}
            AND DAYOFWEEK(date) NOT IN (1, 7)
        GROUP BY date
    ");

        $stmt->execute($queryParams);
        $stats = $stmt->fetchAll();

        $totalSeconds = array_reduce($stats, fn($c, $s) => $c + $s['total_worked_seconds'], 0);
        $totalHours = round($totalSeconds / 3600, 2);
        $workingDaysCount = count($stats); // Теперь count($stats) вернет количество уникальных дат
        $avgHours = $workingDaysCount > 0 ? round($totalHours / $workingDaysCount, 2) : 0;

        JsonResponder::success([
            'stats' => $stats,
            'total_hours' => $totalHours,
            'avg_hours' => $avgHours,
            'working_days_count' => $workingDaysCount,
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    private static function getRequestedUserId(): ?int
    {
        $pathParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        return is_numeric($pathParts[5] ?? null) ? (int)$pathParts[5] : null;
    }

    private static function getRequestedPeriod(): string
    {
        $pathParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        $period = $pathParts[4] ?? 'week';

        if (!in_array($period, ['week', 'month', 'quarter', 'year', 'custom'])) {
            throw new InvalidArgumentException("Invalid period type");
        }

        return $period;
    }

    private static function buildDateCondition(string $period, array &$queryParams): array
    {
        $today = new DateTime();
        $startDate = null;
        $endDate = $today->format('Y-m-d');

        switch ($period) {
            case 'week':
                $start = (clone $today)->sub(new DateInterval('P6D'));
                $startDate = $start->format('Y-m-d');
                break;
            case 'month':
                $start = (clone $today)->sub(new DateInterval('P1M'));
                $startDate = $start->format('Y-m-d');
                break;
            case 'quarter':
                $start = (clone $today)->sub(new DateInterval('P3M'));
                $startDate = $start->format('Y-m-d');
                break;
            case 'year':
                $start = (clone $today)->sub(new DateInterval('P1Y'));
                $startDate = $start->format('Y-m-d');
                break;
            case 'custom':
                if (empty($_GET['start']) || empty($_GET['end'])) {
                    JsonResponder::error('Пользовательский период требует параметров «начало» и «конец»', 500);
                    die();
                }
                $startDate = $_GET['start'];
                $endDate = $_GET['end'];
                $queryParams[] = $startDate;
                $queryParams[] = $endDate;
                return ["AND date BETWEEN ? AND ?", $startDate, $endDate];
            default:
                JsonResponder::error('Неподдерживаемый период', 500);
                die();
        }

        // Добавляем в параметры
        $queryParams[] = $startDate;

        return ["AND date >= ?", $startDate, $endDate];
    }
}

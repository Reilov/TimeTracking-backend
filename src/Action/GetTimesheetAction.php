<?php

namespace Action;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;
use DateTime;
use DateInterval;
use DatePeriod;

class GetTimesheetAction
{
    public static function handle()
    {
        SessionManager::start();
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');

        $pdo = Database::getConnection();

        $employees = self::getEmployees($pdo);

        if (empty($employees)) {
            JsonResponder::error('Сотрудник не найден', 404);
            return;
        }

        $result = [];
        foreach ($employees as $employee) {
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');

            $workSessions = self::getWorkSessions($pdo, $employee['id'], $startDate, $endDate);
            $events = self::getEvents($pdo, $employee['id'], $startDate, $endDate);
            $daysData = self::buildDaysData($workSessions, $events, $startDate, $endDate);
            $summary = self::calculateSummary($daysData, $startDate, $endDate);

            $result[] = [
                'employee' => $employee,
                'days' => $daysData,
                'summary' => $summary,
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d')
                ]
            ];
        }
        JsonResponder::success($result);
        return;
    }

    private static function getEmployees($pdo)
    {
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.position_id,
                p.name AS position_name
            FROM users u
            LEFT JOIN positions p ON u.position_id = p.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function getWorkSessions($pdo, $userId, $startDate, $endDate)
    {
        $stmt = $pdo->prepare("
            SELECT
                date,
                SUM(total_worked_seconds) as total_worked_seconds,
                status
            FROM work_sessions
            WHERE 
                user_id = ? AND
                date BETWEEN ? AND ?
            GROUP BY date, status
        ");
        $stmt->execute([
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ]);
        return $stmt->fetchAll();
    }

    private static function getEvents($pdo, $userId, $startDate, $endDate)
    {
        $stmt = $pdo->prepare("
            SELECT
                start_date,
                end_date,
                type AS status,
                comment
            FROM user_day_events
            WHERE 
                user_id = ? AND
                (
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date BETWEEN ? AND ?) OR
                    (end_date BETWEEN ? AND ?)
                )
        ");

        $stmt->execute([
            $userId,
            $endDate->format('Y-m-d'),
            $startDate->format('Y-m-d'),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ]);
        return $stmt->fetchAll();
    }

    private static function buildDaysData($workSessions, $events, $startDate, $endDate)
    {
        $daysData = [];
        $period = new DatePeriod(
            clone $startDate,
            new DateInterval('P1D'),
            clone $endDate
        );

        // Инициализируем все дни месяца
        foreach ($period as $day) {
            $dayStr = $day->format('Y-m-d');
            $dayOfWeek = $day->format('N');

            $daysData[$dayStr] = [
                'date' => $dayStr,
                'status' => $dayOfWeek >= 6 ? 'weekend' : 'absent',
                'worked_seconds' => 0,
                'comment' => $dayOfWeek >= 6 ? 'Выходной' : 'Неявка'
            ];
        }


        foreach ($events as $event) {
            $eventStart = new DateTime($event['start_date']);
            $eventEnd = new DateTime($event['end_date']);

            for ($date = clone $eventStart; $date <= $eventEnd; $date->modify('+1 day')) {
                $dateStr = $date->format('Y-m-d');
                if (isset($daysData[$dateStr])) {
                    $daysData[$dateStr] = [
                        'date' => $dateStr,
                        'status' => $event['status'],
                        'worked_seconds' => 0,
                        'comment' => $event['comment'] ?? ''
                    ];
                }
            }
        }

        foreach ($workSessions as $session) {
            $dateStr = $session['date'];
            if (isset($daysData[$dateStr])) {
                $daysData[$dateStr]['worked_seconds'] = (int)$session['total_worked_seconds'];
                if ($daysData[$dateStr]['status'] === 'absent') {
                    $daysData[$dateStr]['status'] = $session['status'] ?? 'completed';
                }
            }
        }

        ksort($daysData);
        return array_values($daysData);
    }

    private static function calculateSummary($daysData, $startDate, $endDate)
    {
        $totalSeconds = 0;
        $firstHalfHours = 0;
        $secondHalfHours = 0;
        $workingDays = 0;
        $statusCounts = [];

        foreach ($daysData as $day) {
            $dayDate = new DateTime($day['date']);
            $dayNum = (int)$dayDate->format('d');

            $totalSeconds += $day['worked_seconds'];

            if ($dayNum <= 15) {
                $firstHalfHours += $day['worked_seconds'];
            } else {
                $secondHalfHours += $day['worked_seconds'];
            }

            if ($day['status'] === 'completed') {
                $workingDays++;
            }

            if (!isset($statusCounts[$day['status']])) {
                $statusCounts[$day['status']] = 0;
            }
            $statusCounts[$day['status']]++;
        }

        return [
            'total_seconds' => $totalSeconds,
            'first_half_hours' => $firstHalfHours,
            'second_half_hours' => $secondHalfHours,
            'total_first_half' => $firstHalfHours,
            'working_days' => $workingDays,
            'average_per_day' => $workingDays > 0 ? round($totalSeconds / $workingDays) : 0,
            'status_counts' => $statusCounts
        ];
    }
}

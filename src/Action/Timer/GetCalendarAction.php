<?

namespace Action\Timer;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;
use DateTime;

class GetCalendarAction
{
    public static function handle()
    {
        SessionManager::start();
        $userId = self::getRequestedUserId();
        $pdo = Database::getConnection();

        // Получаем рабочие сессии, объединенные по дате
        $stmt = $pdo->prepare("
        SELECT
            date,
            SUM(total_worked_seconds) as total_worked_seconds,
            MAX(status) as status
        FROM work_sessions
        WHERE user_id = ?
        GROUP BY date
    ");
        $stmt->execute([$userId]);
        $workSessions = $stmt->fetchAll();

        // Получаем события (отпуск, больничный и т.д.)
        $stmt2 = $pdo->prepare("
        SELECT
            start_date,
            end_date,
            type AS status,
            comment
        FROM user_day_events
        WHERE user_id = ?
    ");
        $stmt2->execute([$userId]);
        $events = $stmt2->fetchAll();

        // Приводим к единому формату
        $formatted = [];

        // Обрабатываем рабочие сессии
        foreach ($workSessions as $s) {
            $formatted[$s['date']] = [
                'date' => $s['date'],
                'workedSeconds' => (int) $s['total_worked_seconds'],
                'status' => $s['status'],
                'type' => 'work'
            ];
        }

        // Обрабатываем события
        foreach ($events as $e) {
            $start = new DateTime($e['start_date']);
            $end = new DateTime($e['end_date']);

            // Добавляем все даты в диапазоне события
            for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
                $dateStr = $date->format('Y-m-d');

                // Если уже есть запись о работе в этот день, объединяем с событием
                if (isset($formatted[$dateStr])) {
                    $formatted[$dateStr]['status'] = $e['status']; // Приоритет у события
                    $formatted[$dateStr]['comment'] = $e['comment'] ?? '';
                    $formatted[$dateStr]['type'] = 'mixed';
                } else {
                    $formatted[$dateStr] = [
                        'date' => $dateStr,
                        'workedSeconds' => 0,
                        'status' => $e['status'],
                        'comment' => $e['comment'] ?? '',
                        'type' => 'event'
                    ];
                }
            }
        }

        // Преобразуем ассоциативный массив обратно в индексированный
        $result = array_values($formatted);

        JsonResponder::success(['stats' => $result]);
    }

    private static function getRequestedUserId(): ?int
    {
        $pathParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        return is_numeric($pathParts[4] ?? null) ? (int)$pathParts[4] : null;
    }
}

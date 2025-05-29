<?

namespace Action\Timer;

use Support\Database;
use Responder\JsonResponder;
use Support\SessionManager;

class GetCalendarAction
{
    public static function handle()
    {
        SessionManager::start();
        $userId = self::getRequestedUserId();
        // var_dump($userId);
        // die();
        // $userId = $_GET['user_id'] ?? SessionManager::getUser()['id'];
        $pdo = Database::getConnection();

        // Получаем рабочие сессии
        $stmt = $pdo->prepare("
            SELECT
                date,
                start_time,
                end_time,
                total_worked_seconds,
                status
            FROM work_sessions
            WHERE user_id = ?
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

        foreach ($workSessions as $s) {
            $formatted[] = [
                'date' => $s['date'],
                'startTime' => date('H:i', strtotime($s['start_time'])),
                'endTime' => $s['end_time'] ? date('H:i', strtotime($s['end_time'])) : null,
                'workedSeconds' => (int) $s['total_worked_seconds'],
                'status' => $s['status']
            ];
        }

        foreach ($events as $e) {
            $formatted[] = [
                'startDate' => $e['start_date'],
                'endDate' => $e['end_date'],
                'workedSeconds' => 0,
                'status' => $e['status'],
                'comment' => $e['comment'] ?? ''
            ];
        }

        JsonResponder::success(['stats' => $formatted]);
    }

    private static function getRequestedUserId(): ?int
    {
        $pathParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        return is_numeric($pathParts[4] ?? null) ? (int)$pathParts[4] : null;
    }
}

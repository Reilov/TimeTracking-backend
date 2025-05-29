<?php

namespace Router;

class Router
{
    private $routes = [
        'GET' => [
            '/api/check-login' => 'Action\CheckLoginAction::handle',
            '/api/sessions/active' => 'Action\GetActiveSessionAction::handle',
            '/api/users' => 'Action\UsersAction::handle',
            '/api/users/(\d+)' => 'Action\UsersAction::handle',
            '/api/departments' => 'Action\Departments\DepartmentsAction::handle',
            '/api/positions' => 'Action\Positions\PositionsAction::handle',
            '/api/timer/stats/weekly' => 'Action\Timer\GetWeeklyStatsAction::handle',
            '/api/timer/stats/(?P<period>week|month|quarter|year|custom)' => 'Action\Timer\GetStatsAction::handle',
            '/api/timer/stats/(?P<period>week|month|quarter|year|custom)/(?P<userId>\d+)' => 'Action\Timer\GetStatsAction::handle',
            '/api/timer/calendar/(?P<userId>\d+)' => 'Action\Timer\GetCalendarAction::handle',
            '/api/timesheet' => 'Action\GetTimesheetAction::handle',
        ],
        'POST' => [
            '/api/login' => 'Action\LoginAction::handle',
            '/api/logout' => 'Action\LogoutAction::handle',
            '/api/sessions' => 'Action\SessionAction::handle',
            '/api/update-profile' => 'Action\UpdateProfileAction::handle',
            '/api/update-profile/(\d+)' => 'Action\UpdateProfileAction::handle',
            '/api/register' => 'Action\RegisterAction::handle',
            '/api/user-day-events' => 'Action\AddUserDayEventsAction::handle',
            '/api/turnstile' => 'Action\TurnstileAction::handle',
            // '/api/delete' => 'Action\DeleteUser::handle',
            // '/api/profile' => 'Action\UploadProfileAction::handle'
        ],
        'PUT' => [
            // 'api/update-profile/(\d+)' => '',
        ],
    ];

    public function dispatch(string $method, string $uri)
    {
        $uri = strtok($uri, '?');
        $matches = [];

        foreach ($this->routes[$method] as $route => $handler) {
            if ($this->matchRoute($route, $uri, $matches)) {
                return $this->callHandler($handler, $matches);
            }
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Route not found']);
    }

    private function matchRoute(string $route, string $uri, &$matches = []): bool
    {
        $pattern = '#^' . $route . '$#';
        return preg_match($pattern, $uri, $matches) === 1;
    }

    private function callHandler(string $handler, array $params = [])
    {
        list($class, $method) = explode('::', $handler);

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new \Exception("Handler $handler not found");
        }

        // Фильтруем только именованные параметры
        $namedParams = array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY);

        call_user_func([$class, $method], $namedParams);
    }
}

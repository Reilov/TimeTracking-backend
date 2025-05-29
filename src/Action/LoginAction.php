<?php

namespace Action;

use Domain\UserService;
use Responder\JsonResponder;
use Support\SessionManager;

class LoginAction
{
    public static function handle()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['email']) || empty($data['password'])) {
            JsonResponder::error('Почта и пароль обязательны', 400);
            return;
        }

        $user = (new UserService())->authenticate($data['email'], $data['password']);
        if (!$user) {
            JsonResponder::error('Неверный логин или пароль', 401);
            return;
        }
        $domain = ($_SERVER['HTTP_HOST'] === 'localhost')
            ? 'localhost'
            : 'dfaaqq.duckdns.org';
        SessionManager::setUser([
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'position_name' => $user['position_name'],
            'department_name' => $user['department_name'],
            'birth_date' => $user['birth_date'],
            'phone' => $user['phone'],
            'about' => $user['about'],
            'avatar' => $user['avatar_path'] ? 'http://' . $domain . '/public/storage/avatars/' . $user['avatar_path'] : null,
            'role_name' => $user['role_name'],
        ]);

        JsonResponder::success(['user' => SessionManager::getUser()]);
    }
}

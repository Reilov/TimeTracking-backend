<?php

namespace Action;

use Domain\UserService;
use Responder\JsonResponder;
use Support\FileUploader;
use Support\SessionManager;

class RegisterAction
{
    public static function handle()
    {
        SessionManager::start();

        // 1. Получаем данные формы (без аватара)
        $data = $_POST;

        // 2. Проверяем обязательные поля
        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            JsonResponder::error('Email, password and name are required', 400);
            return;
        }

        // 3. Регистрируем пользователя (получаем $userId)
        $userService = new UserService();
        $user = $userService->register($data);

        if (!$user) {
            JsonResponder::error('User registration failed', 500);
            return;
        }

        // 4. Если есть аватар → загружаем его (теперь $userId известен!)
        $avatarUrl = null;
        if (!empty($_FILES['avatar'])) {
            $fileUploader = new FileUploader();
            $fileName = $fileUploader->uploadAvatar($_FILES['avatar'], $user['id']);
            $avatarUrl = 'http://localhost/public/storage/avatars/' . $fileName;

            // Обновляем аватар в БД
            $userService->updateAvatar($user['id'], $fileName);
        }

        // 5. Устанавливаем сессию
        // SessionManager::setUser([
        //     'id' => $user['id'],
        //     'name' => $user['name'],
        //     'email' => $user['email'],
        //     'avatar' => $avatarUrl, // если аватар был загружен
        // ]);

        // 6. Возвращаем ответ
        JsonResponder::success();
    }
}

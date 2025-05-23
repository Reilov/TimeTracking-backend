<?php

namespace Action;

use Domain\UserService;
use Responder\JsonResponder;
use Support\FileUploader;
use Support\SessionManager;

class UpdateProfileAction
{
    public static function handle(): void
    {
        try {
            SessionManager::start();
            $userId = (int)($_POST['user_id'] ?? self::getRequestedUserId());
            $responseData = ['profile' => []];
            $userService = new UserService();

            // 1. Обработка аватара
            if (!empty($_FILES['avatar'])) {
                $uploadedFile = $_FILES['avatar'];
                $fileUploader = new FileUploader();
                $fileName = $fileUploader->uploadAvatar($uploadedFile, $userId);

                $userService->updateAvatar($userId, $fileName);
                $avatarUrl = 'http://localhost/public/storage/avatars/' . $fileName;

                $responseData['profile']['avatar'] = $avatarUrl;

                // Обновляем аватар в сессии только если редактируется текущий пользователь
                if (self::isEditingCurrentUser($userId)) {
                    SessionManager::updateUser(['avatar' => $avatarUrl]);
                }
            }

            $profileData = self::getProfileDataFromRequest();

            if (!empty($profileData)) {
                // Разные наборы полей для разных случаев
                $allowedFields = ['name', 'email', 'birth_date', 'phone', 'about'];
                $hrAllowedFields = array_merge($allowedFields, ['department_id', 'position_id']);

                // Определяем, какие поля разрешены
                $isHrRequest = isset($_POST['employeeData']);

                $fieldsToCheck = $isHrRequest ? $hrAllowedFields : $allowedFields;

                $changes = array_intersect_key($profileData, array_flip($fieldsToCheck));

                if (!empty($changes)) {
                    $userService->updateProfile($userId, $changes);
                    $responseData['profile'] = array_merge(
                        $responseData['profile'] ?? [],
                        $changes
                    );

                    // Обновляем сессию только если редактируется текущий пользователь
                    if (self::isEditingCurrentUser($userId)) {
                        // Для HR не обновляем department_id и position_id в сессии
                        $sessionChanges = $isHrRequest
                            ? array_intersect_key($changes, array_flip($allowedFields))
                            : $changes;

                        if (!empty($sessionChanges)) {
                            SessionManager::updateUser($sessionChanges);
                        }
                    }
                }
            }

            JsonResponder::success($responseData);
        } catch (\Exception $e) {
            JsonResponder::error($e->getMessage(), 400);
        }
    }

    private static function getRequestedUserId(): ?int
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = explode('/', $path);
        $id = end($parts);

        return is_numeric($id) ? (int)$id : null;
    }

    private static function isEditingCurrentUser(int $userId): bool
    {
        return isset($_SESSION['user']['id']) && $_SESSION['user']['id'] === $userId;
    }

    private static function getProfileDataFromRequest(): array
    {
        if (!empty($_POST['profileData'])) {
            return json_decode($_POST['profileData'], true) ?? [];
        }

        if (!empty($_POST['employeeData'])) {
            return json_decode($_POST['employeeData'], true) ?? [];
        }

        return [];
    }
}

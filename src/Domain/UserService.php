<?php

namespace Domain;

use Support\Database;
use PDO;

class UserService
{
    public function authenticate(string $email, string $password): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.birth_date,
                u.phone,
                u.about,
                u.avatar_path,
                u.email,
                u.password,
                p.name as position_name,
                d.name as department_name,
                r.name as role_name
            FROM users u
            LEFT JOIN positions p ON u.position_id = p.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.email = :email
            LIMIT 1
        ");

        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            unset($user['password']);
            return $user;
        }

        return null;
    }

    // public function updateProfile(int $userId, array $profileData): array
    // {
    //     $pdo = Database::getConnection();
    //     $allowedFields = ['name', 'email', 'birth_date', 'phone', 'about'];
    //     $updateData = array_intersect_key($profileData, array_flip($allowedFields));

    //     if (empty($updateData)) {
    //         return $this->getUserById($userId);
    //     }

    //     $setParts = [];
    //     foreach ($updateData as $key => $value) {
    //         $setParts[] = "$key = :$key";
    //     }

    //     $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
    //     $stmt = $pdo->prepare($sql);

    //     foreach ($updateData as $key => $value) {
    //         $stmt->bindValue(":$key", $value);
    //     }
    //     $stmt->bindValue(":id", $userId);

    //     $stmt->execute();

    //     return $this->getUserById($userId);
    // }

    public function updateAvatar(int $userId, string $fileName): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        $stmt->execute([$fileName, $userId]);

        return $this->getUserById($userId);
    }

    public function getUserById(int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getUserWithAvatarUrl(int $userId): ?array
    {
        $user = $this->getUserById($userId);
        if ($user && $user['avatar_path']) {
            $user['avatar'] = 'http://localhost/public/storage/avatars/' . $user['avatar_path'];
        }
        return $user;
    }

    public function register(array $data): ?array
    {
        $pdo = Database::getConnection();

        // Проверяем, существует ли пользователь с таким email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return null; // Пользователь с таким email уже существует
        }

        // Вставляем нового пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, birth_date, position_id, department_id)
            VALUES (:name, :email, :password, :phone, :birth_date, :position_id, :department_id)
        ");

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':birth_date', $data['birth_date']);
        $stmt->bindParam(':position_id', $data['position']);
        $stmt->bindParam(':department_id', $data['department']);

        if ($stmt->execute()) {
            return [
                'id' => (int)$pdo->lastInsertId(),
            ];
        }

        return null;
    }


    public function updateProfile(int $userId, array $profileData): array
    {
        $pdo = Database::getConnection();

        // Базовые поля для всех пользователей
        $baseAllowedFields = ['name', 'email', 'birth_date', 'phone', 'about'];

        // Дополнительные поля только для HR
        $hrAllowedFields = ['department_id', 'position_id'];

        // Определяем, есть ли HR-поля в данных (значит запрос от HR)
        $isHrRequest = count(array_intersect(array_keys($profileData), $hrAllowedFields)) > 0;

        // Какие поля будем обновлять
        $allowedFields = $isHrRequest
            ? array_merge($baseAllowedFields, $hrAllowedFields)
            : $baseAllowedFields;

        $updateData = array_intersect_key($profileData, array_flip($allowedFields));

        if (empty($updateData)) {
            return $this->getUserById($userId);
        }

        // Подготовка SQL
        $setParts = [];
        foreach ($updateData as $key => $value) {
            $setParts[] = "$key = :$key";
        }

        $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        // Привязка значений
        foreach ($updateData as $key => $value) {
            // Особые обработки для разных типов данных
            if ($key === 'department_id' || $key === 'position_id') {
                $value = $value !== null ? (int)$value : null;
            } elseif ($key === 'birth_date' && $value === '') {
                $value = null;
            }

            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(":id", $userId, \PDO::PARAM_INT);

        try {
            $stmt->execute();

            // После обновления возвращаем полные данные пользователя
            $userData = $this->getUserById($userId);

            // Для HR добавляем информацию об отделах и должностях
            if ($isHrRequest) {
                $userData['department'] = $this->getDepartmentInfo($userData['department_id']);
                $userData['position'] = $this->getPositionInfo($userData['position_id']);
            }

            return $userData;
        } catch (\PDOException $e) {
            // Логирование ошибки
            error_log("Database error in updateProfile: " . $e->getMessage());
            throw new \RuntimeException("Ошибка при обновлении профиля");
        }
    }

    // Вспомогательные методы для HR-данных
    private function getDepartmentInfo(?int $departmentId): ?array
    {
        if ($departmentId === null) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function getPositionInfo(?int $positionId): ?array
    {
        if ($positionId === null) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM positions WHERE id = ?");
        $stmt->execute([$positionId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}

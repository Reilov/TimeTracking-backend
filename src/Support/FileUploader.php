<?php

namespace Support;

class FileUploader
{
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    private const MAX_SIZE = 2 * 1024 * 1024;

    public function uploadAvatar(array $file, int $userId): string
    {
        // Проверка ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload error: ' . $file['error']);
        }

        // Проверка типа файла
        if (!in_array($file['type'], self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException('Invalid file type. Only JPG, PNG and GIF are allowed');
        }

        // Проверка размера
        if ($file['size'] > self::MAX_SIZE) {
            throw new \InvalidArgumentException('File size exceeds 2MB limit');
        }

        // Создаем директорию, если не существует
        $uploadDir = __DIR__ . '/../../public/storage/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерируем уникальное имя файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        $fileName = "user_{$userId}.{$extension}";
        $filePath = $uploadDir . $fileName;

        // Перемещаем загруженный файл
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }

        return $fileName;
    }
}

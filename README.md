# TimeTracking Backend API 🚀

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![PDO](https://img.shields.io/badge/PDO-4479A1?style=for-the-badge&logo=php&logoColor=white)
![API](https://img.shields.io/badge/API-FF6C37?style=for-the-badge&logo=postman&logoColor=white)

Backend-часть системы учета рабочего времени для компании [СервисКлауд](https://scloud.ru/).  
RESTful API сервер для фронтенд приложения [TimeTracking Frontend](https://github.com/Reilov/TimeTracking-frontend).

## 📋 Требования

- PHP 8.0+
- MySQL 5.7+
- Composer 2.0+
- Веб-сервер (Apache/Nginx)

## 🏗 Структура проекта

```
├── api/
│   └── index.php          # Точка входа API
├── public/
│   └── storage/
│       └── avatars/       # Хранилище аватарок пользователей
├── src/
│   ├── Action/            # Обработчики действий
│   ├── Domain/            # Доменная логика
│   ├── Responder/         # Формирование ответов
│   ├── Router/            # Маршрутизация
│   └── Support/           # Вспомогательные классы
├── config.php             # Конфигурация БД
├── diplomDB.sql           # Дамп базы данных
└── vendor/                # Зависимости Composer
```

## 🛠 Установка

```bash
git clone https://github.com/ваш-репозиторий/time-tracking-backend.git
cd time-tracking-backend

# Установка зависимостей
composer install --optimize-autoloader

# Настройка прав на папку с аватарками
chmod -R 775 public/storage/avatars
```

### Конфигурация
Создайте `config.php` в корне проекта:
```php
<?php
return [
    'host' => 'localhost',      // Хост БД
    'database' => 'diplomDB',   // Имя базы данных
    'username' => 'root',       // Пользователь БД
    'password' => '',           // Пароль пользователя
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```
### 🔒 Защита конфигурационного файла

**1. Настройка прав доступа:**
```bash
chmod 640 config.php  # Доступ только владельцу и группе
chown www-data:www-data config.php  # Установка правильного владельца
```

**2. Запрет прямого доступа через веб-сервер:**

Для **Apache** добавьте в `.htaccess`:
```apache
<FilesMatch "^config\.php$">
    Require all denied
</FilesMatch>
```

Для **Nginx** добавьте в конфиг сервера:
```nginx
location = /config.php {
    deny all;
    return 403;
}
```

## 📚 Документация API

### Базовый URL
`http(s)://ваш-домен/api`

### Endpoints

#### Пользователи
- `GET /api/users` - Получить список всех пользователей
- `GET /api/users/{id}` - Получить данные конкретного пользователя
- `POST /api/update-profile` - Обновить профиль текущего пользователя
- `POST /api/update-profile/{id}` - Обновить профиль указанного пользователя (HR)

**Пример ответа /api/users:**
```json
{
  "status": "success",
  "users": [
    {
      "id": 1,
      "name": "Глеб Шышкин",
      "email": "andrey@company.com",
      "position_id": 3,
      "department_id": 2,
      "position_name": "Backend Developer",
      "department_name": "IT-отдел",
      "status": "completed",
      "avatar": "/storage/avatars/user_1.jpg"
    },
    ...
  ]
}
```

#### Отделы и должности
- `GET /api/departments` - Список отделов
- `GET /api/positions` - Список должностей

#### Учет времени
- `GET /api/timer/stats/weekly` - Недельная статистика
- `GET /api/timer/stats/{period}` - Статистика за период (week|month|quarter|year|custom)
- `GET /api/timer/stats/{period}/{userId}` - Статистика для конкретного пользователя
- `GET /api/timer/calendar/{userId}` - Календарь учета времени
- `POST /api/add-work-day` - Добавить рабочий день
- `POST /api/user-day-events` - Добавить событие (отпуск)

#### Системные
- `POST /api/login` - Авторизация
- `POST /api/logout` - Выход из системы
- `GET /api/check-login` - Проверка авторизации
- `POST /api/turnstile` - Имитация турникета (отметка прихода/ухода)

## 🌍 Развертывание

### Development

```bash
php -S localhost:8000 -t api/
```

---
Этот проект разработан в учебных целях.

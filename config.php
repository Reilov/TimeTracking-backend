<?php
return [
    'host' => 'localhost',
    'database' => 'diplomDB',
    'username' => 'root',
    'password' => '', // Пароль, если есть
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

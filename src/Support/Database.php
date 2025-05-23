<?php

namespace Support;

use PDO;

class Database
{
    public static function getConnection(): PDO
    {
        $config = include __DIR__ . '/../../config.php';

        return new PDO(
            "mysql:host={$config['host']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
            $config['options']
        );
    }
}

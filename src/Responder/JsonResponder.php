<?php

namespace Responder;

class JsonResponder
{
    public static function success(array $data = []): void
    {
        header('Content-Type: application/json');
        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Credentials: true");
        echo json_encode(['status' => 'success'] + $data);
    }

    public static function error(string $message, int $code = 400): void
    {
        header('Content-Type: application/json');
        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Credentials: true");
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message]);
    }
}

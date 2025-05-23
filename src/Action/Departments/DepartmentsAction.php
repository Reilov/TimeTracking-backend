<?php

namespace Action\Departments;

use Support\Database;
use Responder\JsonResponder;

class DepartmentsAction
{
    public static function handle()
    {
        try {
            $pdo = Database::getConnection();
            $stmt = "SELECT id, name FROM departments";
            $departments = $pdo->query($stmt)->fetchAll();

            JsonResponder::success(['departments' => $departments]);
        } catch (\PDOException $e) {
            JsonResponder::error(
                'Database error: ' . $e->getMessage(),
                500
            );
        } catch (\Exception $e) {
            JsonResponder::error(
                $e->getMessage(),
                400
            );
        }
    }
}

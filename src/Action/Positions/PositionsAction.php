<?php

namespace Action\Positions;

use Support\Database;
use Responder\JsonResponder;

class PositionsAction
{
    public static function handle()
    {
        try {
            $pdo = Database::getConnection();
            $stmt = "SELECT id, name FROM positions WHERE id != 1";
            $positions = $pdo->query($stmt)->fetchAll();

            JsonResponder::success(['positions' => $positions]);
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

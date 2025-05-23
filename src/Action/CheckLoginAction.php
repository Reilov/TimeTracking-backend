<?php

namespace Action;

use Responder\JsonResponder;
use Support\SessionManager;

class CheckLoginAction
{
    public static function handle(): void
    {
        SessionManager::start();

        $user = SessionManager::getUser();

        if ($user) {
            JsonResponder::success(['user' => $user]);
        } else {
            JsonResponder::error('Session not found', 401);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        Session::start();
        $sessionToken = $_SESSION['_csrf'] ?? null;

        if (!is_string($sessionToken) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public static function check(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !self::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Session expiree ou jeton CSRF invalide. Revenez en arriere et rechargez la page.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestion centralisee de la session et des messages flash.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $lifetime = (int) config('session.lifetime', 60) * 60;

        session_name('CENTREMANAGERSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) config('session.secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_httponly', '1');

        session_start();
        self::$started = true;

        self::enforceTimeout($lifetime);
    }

    private static function enforceTimeout(int $lifetime): void
    {
        $now = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);

        if ($now - $last > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['_last_activity'] = $now;
        $_SESSION['_created_at'] ??= $now;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function pullFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    public static function flashInput(array $input): void
    {
        self::start();
        unset($input['_token'], $input['mot_de_passe'], $input['mot_de_passe_confirmation']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        self::start();
        $old = $_SESSION['_old'] ?? [];

        return $old[$key] ?? $default;
    }

    public static function clearOld(): void
    {
        self::start();
        unset($_SESSION['_old']);
    }

    public static function setErrors(array $errors): void
    {
        self::start();
        $_SESSION['_errors'] = $errors;
    }

    public static function pullErrors(): array
    {
        self::start();
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return is_array($errors) ? $errors : [];
    }
}

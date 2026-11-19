<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Connexion PDO unique (lazy singleton) avec exceptions et utf8mb4.
 */
final class Database
{
    private static ?PDO $instance = null;

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$instance = null;
    }

    public static function pdo(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        if (self::$config === null) {
            /** @var array<string, mixed> $config */
            $config = require dirname(__DIR__, 2) . '/config/database.php';
            self::$config = $config;
        }

        $config = self::$config;
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$instance = new PDO((string) $dsn, (string) $config['username'], (string) $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $exception) {
            self::log('Connexion base de donnees impossible : ' . $exception->getMessage());
            throw new RuntimeException(
                'La connexion a la base de donnees a echoue. Verifiez le fichier .env et que MySQL est demarre.',
                0,
                $exception
            );
        }

        return self::$instance;
    }

    /**
     * Connexion ponctuelle (tests, maintenance) sans toucher au singleton.
     *
     * @param array<string, mixed> $config
     */
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        return new PDO((string) $dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }

    public static function log(string $message): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s%s", date('Y-m-d H:i:s'), $message, PHP_EOL);
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}

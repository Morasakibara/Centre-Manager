<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Parametre
{
    /** @return array<string, string> */
    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT cle, valeur FROM parametres')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['cle']] = (string) ($row['valeur'] ?? '');
        }

        return $result;
    }

    public static function get(string $cle, string $default = ''): string
    {
        $statement = Database::pdo()->prepare('SELECT valeur FROM parametres WHERE cle = :cle LIMIT 1');
        $statement->execute(['cle' => $cle]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public static function set(string $cle, string $valeur): void
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO parametres (cle, valeur) VALUES (:cle, :valeur)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)'
        );
        $statement->execute(['cle' => $cle, 'valeur' => $valeur]);
    }

    /** @param array<string, string> $valeurs */
    public static function setMany(array $valeurs): void
    {
        foreach ($valeurs as $cle => $valeur) {
            self::set($cle, $valeur);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Generation de matricules uniques et sure en concurrence.
 * La methode suivante() doit etre appelee dans une transaction ouverte :
 * elle verrouille la ligne du compteur (SELECT ... FOR UPDATE).
 */
final class MatriculeService
{
    public static function suivant(?int $annee = null): string
    {
        $pdo = Database::pdo();
        $annee ??= (int) date('Y');
        $prefixe = (string) config('matricule.prefixe', 'CF');
        $cle = $prefixe . ':' . $annee;

        if (!$pdo->inTransaction()) {
            throw new RuntimeException('La generation du matricule doit se faire dans une transaction.');
        }

        $statement = $pdo->prepare('SELECT valeur FROM compteurs WHERE cle = :cle FOR UPDATE');
        $statement->execute(['cle' => $cle]);
        $valeur = $statement->fetchColumn();

        if ($valeur === false) {
            $insert = $pdo->prepare('INSERT INTO compteurs (cle, valeur) VALUES (:cle, 1)');
            $insert->execute(['cle' => $cle]);
            $numero = 1;
        } else {
            $numero = (int) $valeur + 1;
            $update = $pdo->prepare('UPDATE compteurs SET valeur = :valeur WHERE cle = :cle');
            $update->execute(['valeur' => $numero, 'cle' => $cle]);
        }

        $matricule = sprintf('%s-%d-%04d', $prefixe, $annee, $numero);

        // Garde-fou : le matricule doit rester unique.
        $check = $pdo->prepare('SELECT COUNT(*) FROM etudiants WHERE matricule = :m');
        $check->execute(['m' => $matricule]);
        if ((int) $check->fetchColumn() > 0) {
            throw new RuntimeException('Collision de matricule detectee, veuillez reessayer.');
        }

        return $matricule;
    }

    /** @return array<int, int> */
    public static function compteurs(): array
    {
        $rows = Database::pdo()->query('SELECT cle, valeur FROM compteurs ORDER BY cle')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['cle']] = (int) $row['valeur'];
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AnneeAcademique
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM annees_academiques ORDER BY date_debut DESC')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM annees_academiques WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public static function courante(): ?array
    {
        $row = Database::pdo()->query(
            "SELECT * FROM annees_academiques WHERE statut = 'ouverte' ORDER BY date_debut DESC LIMIT 1"
        )->fetch();

        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO annees_academiques (libelle, date_debut, date_fin, statut) VALUES (:libelle, :debut, :fin, :statut)'
        );
        $statement->execute([
            'libelle' => $data['libelle'],
            'debut' => $data['date_debut'],
            'fin' => $data['date_fin'],
            'statut' => $data['statut'] ?? 'ouverte',
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE annees_academiques SET libelle = :libelle, date_debut = :debut, date_fin = :fin WHERE id = :id'
        );
        $statement->execute([
            'libelle' => $data['libelle'],
            'debut' => $data['date_debut'],
            'fin' => $data['date_fin'],
            'id' => $id,
        ]);
    }

    public static function changerStatut(int $id, string $statut): void
    {
        $statement = Database::pdo()->prepare('UPDATE annees_academiques SET statut = :statut WHERE id = :id');
        $statement->execute(['statut' => $statut, 'id' => $id]);
    }

    public static function libelleExiste(string $libelle, ?int $exclureId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM annees_academiques WHERE libelle = :libelle';
        $params = ['libelle' => $libelle];
        if ($exclureId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exclureId;
        }
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }
}

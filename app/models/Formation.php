<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Formation
{
    /** @return array<int, array<string, mixed>> */
    public static function all(?string $statut = null): array
    {
        if ($statut !== null) {
            $statement = Database::pdo()->prepare('SELECT * FROM formations WHERE statut = :statut ORDER BY nom');
            $statement->execute(['statut' => $statut]);
            return $statement->fetchAll();
        }

        return Database::pdo()->query('SELECT * FROM formations ORDER BY nom')->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function avecStatistiques(): array
    {
        return Database::pdo()->query(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM inscriptions i WHERE i.formation_id = f.id AND i.statut <> 'annulee') AS nb_inscriptions,
                    (SELECT COUNT(*) FROM inscriptions i WHERE i.formation_id = f.id AND i.statut <> 'annulee' AND i.mode_paiement = 'tranches') AS nb_tranches
             FROM formations f
             ORDER BY f.statut ASC, f.nom ASC"
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM formations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO formations (nom, description, frais_formation, statut) VALUES (:nom, :description, :frais, :statut)'
        );
        $statement->execute([
            'nom' => $data['nom'],
            'description' => $data['description'] ?: null,
            'frais' => $data['frais_formation'],
            'statut' => $data['statut'] ?? 'actif',
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE formations SET nom = :nom, description = :description, frais_formation = :frais, statut = :statut WHERE id = :id'
        );
        $statement->execute([
            'nom' => $data['nom'],
            'description' => $data['description'] ?: null,
            'frais' => $data['frais_formation'],
            'statut' => $data['statut'],
            'id' => $id,
        ]);
    }

    public static function changerStatut(int $id, string $statut): void
    {
        $statement = Database::pdo()->prepare('UPDATE formations SET statut = :statut WHERE id = :id');
        $statement->execute(['statut' => $statut, 'id' => $id]);
    }

    public static function nomExiste(string $nom, ?int $exclureId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM formations WHERE nom = :nom';
        $params = ['nom' => $nom];
        if ($exclureId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exclureId;
        }
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }
}

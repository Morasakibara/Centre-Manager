<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Etudiant
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM etudiants WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(array $filtres, int $page = 1, int $parPage = 15): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filtres['q'])) {
            $where[] = '(matricule LIKE :q OR nom LIKE :q OR prenom LIKE :q OR telephone LIKE :q OR CONCAT(prenom, \' \', nom) LIKE :q)';
            $params['q'] = '%' . $filtres['q'] . '%';
        }

        if (!empty($filtres['statut'])) {
            $where[] = 'statut = :statut';
            $params['statut'] = $filtres['statut'];
        } else {
            $where[] = "statut <> 'archive'";
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM etudiants WHERE ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        $statement = $pdo->prepare(
            'SELECT * FROM etudiants WHERE ' . $whereSql . ' ORDER BY nom ASC, prenom ASC LIMIT :limite OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limite', $parPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['rows' => $statement->fetchAll(), 'total' => $total];
    }

    /** @return array<int, array<string, mixed>> */
    public static function tousActifs(): array
    {
        return Database::pdo()
            ->query("SELECT id, matricule, nom, prenom FROM etudiants WHERE statut IN ('actif', 'suspendu') ORDER BY nom, prenom")
            ->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO etudiants (matricule, nom, prenom, sexe, date_naissance, telephone, adresse, date_enregistrement, statut)
             VALUES (:matricule, :nom, :prenom, :sexe, :date_naissance, :telephone, :adresse, :date_enregistrement, :statut)'
        );
        $statement->execute([
            'matricule' => $data['matricule'],
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'],
            'date_naissance' => $data['date_naissance'] ?: null,
            'telephone' => $data['telephone'],
            'adresse' => $data['adresse'] ?: null,
            'date_enregistrement' => $data['date_enregistrement'],
            'statut' => $data['statut'] ?? 'actif',
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE etudiants
             SET nom = :nom, prenom = :prenom, sexe = :sexe, date_naissance = :date_naissance,
                 telephone = :telephone, adresse = :adresse, date_enregistrement = :date_enregistrement
             WHERE id = :id'
        );
        $statement->execute([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'],
            'date_naissance' => $data['date_naissance'] ?: null,
            'telephone' => $data['telephone'],
            'adresse' => $data['adresse'] ?: null,
            'date_enregistrement' => $data['date_enregistrement'],
            'id' => $id,
        ]);
    }

    public static function changerStatut(int $id, string $statut): void
    {
        $statement = Database::pdo()->prepare('UPDATE etudiants SET statut = :statut WHERE id = :id');
        $statement->execute(['statut' => $statut, 'id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function doublonsPotentiels(string $nom, string $prenom, string $telephone, ?int $exclureId = null): array
    {
        $sql = 'SELECT id, matricule, nom, prenom, telephone FROM etudiants
                WHERE telephone = :telephone AND (nom LIKE :nom OR prenom LIKE :prenom)';
        $params = [
            'telephone' => $telephone,
            'nom' => '%' . $nom . '%',
            'prenom' => '%' . $prenom . '%',
        ];

        if ($exclureId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exclureId;
        }

        $statement = Database::pdo()->prepare($sql . ' LIMIT 5');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public static function parIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        return Database::pdo()->query('SELECT * FROM etudiants WHERE id IN (' . $in . ')')->fetchAll();
    }
}

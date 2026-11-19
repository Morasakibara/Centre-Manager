<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Utilisateur
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT id, nom_complet, nom_utilisateur, email, role, statut, derniere_connexion, created_at
             FROM utilisateurs ORDER BY role ASC, nom_complet ASC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM utilisateurs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function nomUtilisateurExiste(string $nom, ?int $exclureId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur = :nom';
        $params = ['nom' => $nom];
        if ($exclureId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exclureId;
        }
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO utilisateurs (nom_complet, nom_utilisateur, email, mot_de_passe, role, statut)
             VALUES (:nom_complet, :nom_utilisateur, :email, :mot_de_passe, :role, :statut)'
        );
        $statement->execute([
            'nom_complet' => $data['nom_complet'],
            'nom_utilisateur' => $data['nom_utilisateur'],
            'email' => $data['email'] ?: null,
            'mot_de_passe' => password_hash((string) $data['mot_de_passe'], PASSWORD_DEFAULT),
            'role' => $data['role'],
            'statut' => $data['statut'] ?? 'actif',
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE utilisateurs SET nom_complet = :nom_complet, nom_utilisateur = :nom_utilisateur,
                email = :email, role = :role WHERE id = :id'
        );
        $statement->execute([
            'nom_complet' => $data['nom_complet'],
            'nom_utilisateur' => $data['nom_utilisateur'],
            'email' => $data['email'] ?: null,
            'role' => $data['role'],
            'id' => $id,
        ]);
    }

    public static function changerStatut(int $id, string $statut): void
    {
        $statement = Database::pdo()->prepare('UPDATE utilisateurs SET statut = :statut WHERE id = :id');
        $statement->execute(['statut' => $statut, 'id' => $id]);
    }

    public static function changerMotDePasse(int $id, string $motDePasse): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE utilisateurs SET mot_de_passe = :p, tentatives_echecs = 0, verrouille_jusqu_a = NULL WHERE id = :id'
        );
        $statement->execute(['p' => password_hash($motDePasse, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function compterAdministrateursActifs(): int
    {
        return (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'administrateur' AND statut = 'actif'"
        )->fetchColumn();
    }
}

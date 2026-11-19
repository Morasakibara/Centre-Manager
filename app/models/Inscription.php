<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Inscription
{
    private const SELECT_BASE = "SELECT i.*,
            e.matricule, e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, e.telephone,
            f.nom AS formation_nom, f.frais_formation,
            a.libelle AS annee_libelle, a.statut AS annee_statut,
            u.nom_complet AS cree_par
        FROM inscriptions i
        INNER JOIN etudiants e ON e.id = i.etudiant_id
        INNER JOIN formations f ON f.id = i.formation_id
        INNER JOIN annees_academiques a ON a.id = i.annee_academique_id
        LEFT JOIN utilisateurs u ON u.id = i.created_by";

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare(self::SELECT_BASE . ' WHERE i.id = :id LIMIT 1');
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
            $where[] = '(e.matricule LIKE :q OR e.nom LIKE :q OR e.prenom LIKE :q OR CONCAT(e.prenom, \' \', e.nom) LIKE :q)';
            $params['q'] = '%' . $filtres['q'] . '%';
        }
        if (!empty($filtres['annee'])) {
            $where[] = 'i.annee_academique_id = :annee';
            $params['annee'] = (int) $filtres['annee'];
        }
        if (!empty($filtres['formation'])) {
            $where[] = 'i.formation_id = :formation';
            $params['formation'] = (int) $filtres['formation'];
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'i.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['mode'])) {
            $where[] = 'i.mode_paiement = :mode';
            $params['mode'] = $filtres['mode'];
        }
        if (!empty($filtres['etudiant'])) {
            $where[] = 'i.etudiant_id = :etudiant';
            $params['etudiant'] = (int) $filtres['etudiant'];
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM inscriptions i
             INNER JOIN etudiants e ON e.id = i.etudiant_id WHERE ' . $whereSql
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        $statement = $pdo->prepare(
            self::SELECT_BASE . ' WHERE ' . $whereSql . ' ORDER BY i.date_inscription DESC, i.id DESC LIMIT :limite OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limite', $parPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['rows' => $statement->fetchAll(), 'total' => $total];
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    public static function allFiltres(array $filtres): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filtres['annee'])) {
            $where[] = 'i.annee_academique_id = :annee';
            $params['annee'] = (int) $filtres['annee'];
        }
        if (!empty($filtres['formation'])) {
            $where[] = 'i.formation_id = :formation';
            $params['formation'] = (int) $filtres['formation'];
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'i.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (isset($filtres['etudiant']) && $filtres['etudiant'] !== '') {
            $where[] = 'i.etudiant_id = :etudiant';
            $params['etudiant'] = (int) $filtres['etudiant'];
        }

        $statement = Database::pdo()->prepare(
            self::SELECT_BASE . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY e.nom, e.prenom, i.id'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function parEtudiant(int $etudiantId): array
    {
        $statement = Database::pdo()->prepare(
            self::SELECT_BASE . " WHERE i.etudiant_id = :id AND i.statut <> 'annulee' ORDER BY a.date_debut DESC"
        );
        $statement->execute(['id' => $etudiantId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO inscriptions
                (etudiant_id, formation_id, annee_academique_id, date_inscription, montant_total,
                 montant_initial, mode_paiement, statut, observation, created_by)
             VALUES
                (:etudiant, :formation, :annee, :date_inscription, :montant_total,
                 :montant_initial, :mode, :statut, :observation, :created_by)'
        );
        $statement->execute([
            'etudiant' => $data['etudiant_id'],
            'formation' => $data['formation_id'],
            'annee' => $data['annee_academique_id'],
            'date_inscription' => $data['date_inscription'],
            'montant_total' => $data['montant_total'],
            'montant_initial' => $data['montant_initial'],
            'mode' => $data['mode_paiement'],
            'statut' => 'active',
            'observation' => $data['observation'] ?: null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function changerStatut(int $id, string $statut, ?string $motif = null): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE inscriptions SET statut = :statut, motif_annulation = :motif WHERE id = :id'
        );
        $statement->execute(['statut' => $statut, 'motif' => $motif, 'id' => $id]);
    }

    public static function existePourEtudiantAnnee(int $etudiantId, int $anneeId, ?int $exclureId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM inscriptions WHERE etudiant_id = :etudiant AND annee_academique_id = :annee';
        $params = ['etudiant' => $etudiantId, 'annee' => $anneeId];
        if ($exclureId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exclureId;
        }
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function compter(?int $anneeId = null): int
    {
        if ($anneeId !== null) {
            $statement = Database::pdo()->prepare("SELECT COUNT(*) FROM inscriptions WHERE annee_academique_id = :annee AND statut <> 'annulee'");
            $statement->execute(['annee' => $anneeId]);
            return (int) $statement->fetchColumn();
        }

        return (int) Database::pdo()->query("SELECT COUNT(*) FROM inscriptions WHERE statut <> 'annulee'")->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public static function actives(): array
    {
        return Database::pdo()->query(
            "SELECT id, montant_total, mode_paiement, etudiant_id, formation_id, annee_academique_id
             FROM inscriptions WHERE statut = 'active'"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function deEtudiant(int $etudiantId): array
    {
        $statement = Database::pdo()->prepare(
            "SELECT i.*, f.nom AS formation_nom, a.libelle AS annee_libelle
             FROM inscriptions i
             INNER JOIN formations f ON f.id = i.formation_id
             INNER JOIN annees_academiques a ON a.id = i.annee_academique_id
             WHERE i.etudiant_id = :id ORDER BY a.date_debut DESC"
        );
        $statement->execute(['id' => $etudiantId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function parAnnee(int $anneeId): array
    {
        $statement = Database::pdo()->prepare(
            self::SELECT_BASE . " WHERE i.annee_academique_id = :annee AND i.statut <> 'annulee' ORDER BY e.nom, e.prenom"
        );
        $statement->execute(['annee' => $anneeId]);

        return $statement->fetchAll();
    }
}

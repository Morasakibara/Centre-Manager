<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Paiement
{
    private const SELECT_BASE = "SELECT p.*, i.montant_total, i.mode_paiement AS mode_inscription,
            e.matricule, e.nom, e.prenom, e.telephone,
            f.nom AS formation_nom, a.libelle AS annee_libelle,
            u.nom_complet AS encaisse_par, au.nom_complet AS annule_par_nom
        FROM paiements p
        INNER JOIN inscriptions i ON i.id = p.inscription_id
        INNER JOIN etudiants e ON e.id = i.etudiant_id
        INNER JOIN formations f ON f.id = i.formation_id
        INNER JOIN annees_academiques a ON a.id = i.annee_academique_id
        INNER JOIN utilisateurs u ON u.id = p.utilisateur_id
        LEFT JOIN utilisateurs au ON au.id = p.annule_par";

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare(self::SELECT_BASE . ' WHERE p.id = :id LIMIT 1');
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
            $where[] = '(e.matricule LIKE :q OR e.nom LIKE :q OR e.prenom LIKE :q OR p.numero_recu LIKE :q OR p.reference LIKE :q)';
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
        if (!empty($filtres['mode'])) {
            $where[] = 'p.mode_paiement = :mode';
            $params['mode'] = $filtres['mode'];
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'p.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['debut'])) {
            $where[] = 'DATE(p.date_paiement) >= :debut';
            $params['debut'] = $filtres['debut'];
        }
        if (!empty($filtres['fin'])) {
            $where[] = 'DATE(p.date_paiement) <= :fin';
            $params['fin'] = $filtres['fin'];
        }
        if (!empty($filtres['inscription'])) {
            $where[] = 'p.inscription_id = :inscription';
            $params['inscription'] = (int) $filtres['inscription'];
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM paiements p
             INNER JOIN inscriptions i ON i.id = p.inscription_id
             INNER JOIN etudiants e ON e.id = i.etudiant_id
             WHERE ' . $whereSql
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        $statement = $pdo->prepare(
            self::SELECT_BASE . ' WHERE ' . $whereSql . ' ORDER BY p.date_paiement DESC, p.id DESC LIMIT :limite OFFSET :offset'
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
     * Totaux des paiements correspondant aux filtres (valides et annules).
     *
     * @param array<string, mixed> $filtres
     * @return array{valide: float, annule: float, nb: int}
     */
    public static function totalFiltre(array $filtres): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filtres['q'])) {
            $where[] = '(e.matricule LIKE :q OR e.nom LIKE :q OR e.prenom LIKE :q OR p.numero_recu LIKE :q OR p.reference LIKE :q)';
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
        if (!empty($filtres['mode'])) {
            $where[] = 'p.mode_paiement = :mode';
            $params['mode'] = $filtres['mode'];
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'p.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['debut'])) {
            $where[] = 'DATE(p.date_paiement) >= :debut';
            $params['debut'] = $filtres['debut'];
        }
        if (!empty($filtres['fin'])) {
            $where[] = 'DATE(p.date_paiement) <= :fin';
            $params['fin'] = $filtres['fin'];
        }

        $statement = Database::pdo()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN p.statut = 'valide' THEN p.montant ELSE 0 END), 0) AS valide,
                COALESCE(SUM(CASE WHEN p.statut = 'annule' THEN p.montant ELSE 0 END), 0) AS annule,
                COUNT(*) AS nb
             FROM paiements p
             INNER JOIN inscriptions i ON i.id = p.inscription_id
             INNER JOIN etudiants e ON e.id = i.etudiant_id
             WHERE " . implode(' AND ', $where)
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return [
            'valide' => (float) ($row['valide'] ?? 0),
            'annule' => (float) ($row['annule'] ?? 0),
            'nb' => (int) ($row['nb'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function recents(int $limite = 8): array
    {
        $statement = Database::pdo()->prepare(
            self::SELECT_BASE . " WHERE p.statut = 'valide' ORDER BY p.date_paiement DESC, p.id DESC LIMIT :limite"
        );
        $statement->bindValue(':limite', $limite, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function deInscription(int $inscriptionId, bool $inclureAnnules = true): array
    {
        $sql = self::SELECT_BASE . ' WHERE p.inscription_id = :id';
        if (!$inclureAnnules) {
            $sql .= " AND p.statut = 'valide'";
        }
        $statement = Database::pdo()->prepare($sql . ' ORDER BY p.date_paiement DESC, p.id DESC');
        $statement->execute(['id' => $inscriptionId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function validesPourPeriode(?string $debut, ?string $fin, ?int $anneeId = null, ?int $formationId = null): array
    {
        $where = ["p.statut = 'valide'"];
        $params = [];

        if ($debut !== null && $debut !== '') {
            $where[] = 'DATE(p.date_paiement) >= :debut';
            $params['debut'] = $debut;
        }
        if ($fin !== null && $fin !== '') {
            $where[] = 'DATE(p.date_paiement) <= :fin';
            $params['fin'] = $fin;
        }
        if ($anneeId !== null) {
            $where[] = 'i.annee_academique_id = :annee';
            $params['annee'] = $anneeId;
        }
        if ($formationId !== null) {
            $where[] = 'i.formation_id = :formation';
            $params['formation'] = $formationId;
        }

        $statement = Database::pdo()->prepare(
            self::SELECT_BASE . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.date_paiement ASC, p.id ASC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public static function totalValide(?int $inscriptionId = null): float
    {
        if ($inscriptionId !== null) {
            $statement = Database::pdo()->prepare(
                "SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE statut = 'valide' AND inscription_id = :id"
            );
            $statement->execute(['id' => $inscriptionId]);
            return (float) $statement->fetchColumn();
        }

        return (float) Database::pdo()->query("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE statut = 'valide'")->fetchColumn();
    }

    public static function dernierNumeroRecu(): ?string
    {
        $value = Database::pdo()->query('SELECT numero_recu FROM paiements ORDER BY id DESC LIMIT 1')->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public static function annuler(int $id, string $motif, int $utilisateurId): void
    {
        $statement = Database::pdo()->prepare(
            "UPDATE paiements
             SET statut = 'annule', motif_annulation = :motif, annule_par = :utilisateur, date_annulation = NOW()
             WHERE id = :id AND statut = 'valide'"
        );
        $statement->execute(['motif' => $motif, 'utilisateur' => $utilisateurId, 'id' => $id]);
    }

    /**
     * Montants deja affectes aux echeances d'une inscription.
     *
     * @return array<int, int> montant en centimes par echeance
     */
    public static function affectationsParEcheance(int $inscriptionId): array
    {
        $statement = Database::pdo()->prepare(
            "SELECT ap.echeance_id, SUM(ap.montant_affecte) AS total
             FROM affectations_paiements ap
             INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
             WHERE ap.inscription_id = :id
             GROUP BY ap.echeance_id"
        );
        $statement->execute(['id' => $inscriptionId]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['echeance_id']] = (int) round((float) $row['total'] * 100);
        }

        return $result;
    }
}

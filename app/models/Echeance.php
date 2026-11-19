<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Echeance
{
    /** @return array<int, array<string, mixed>> */
    public static function deInscription(int $inscriptionId): array
    {
        $statement = Database::pdo()->prepare(
            "SELECT ec.*,
                    COALESCE((
                        SELECT SUM(ap.montant_affecte)
                        FROM affectations_paiements ap
                        INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
                        WHERE ap.echeance_id = ec.id
                    ), 0) AS montant_regle
             FROM echeances ec
             WHERE ec.inscription_id = :id
             ORDER BY ec.date_echeance ASC, ec.id ASC"
        );
        $statement->execute(['id' => $inscriptionId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function deInscriptionPourUpdate(int $inscriptionId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM echeances WHERE inscription_id = :id ORDER BY date_echeance ASC, id ASC FOR UPDATE'
        );
        $statement->execute(['id' => $inscriptionId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM echeances WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function create(int $inscriptionId, string $libelle, string $montant, string $date): int
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO echeances (inscription_id, libelle, montant_attendu, date_echeance)
             VALUES (:inscription, :libelle, :montant, :date)'
        );
        $statement->execute([
            'inscription' => $inscriptionId,
            'libelle' => $libelle,
            'montant' => $montant,
            'date' => $date,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function supprimer(int $id): void
    {
        $statement = Database::pdo()->prepare('DELETE FROM echeances WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * Echeances en retard, avec identite de l'etudiant.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function enRetard(?int $anneeId = null, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $where = [
            'ec.date_echeance < CURDATE()',
            "i.statut <> 'annulee'",
        ];
        $params = [];

        if ($anneeId !== null) {
            $where[] = 'i.annee_academique_id = :annee';
            $params['annee'] = $anneeId;
        }
        if ($dateDebut !== null) {
            $where[] = 'ec.date_echeance >= :debut';
            $params['debut'] = $dateDebut;
        }
        if ($dateFin !== null) {
            $where[] = 'ec.date_echeance <= :fin';
            $params['fin'] = $dateFin;
        }

        $sql = "SELECT ec.*, i.mode_paiement, i.montant_total,
                       e.matricule, e.nom, e.prenom, e.telephone,
                       f.nom AS formation_nom, f.id AS formation_id, a.libelle AS annee_libelle, a.id AS annee_id,
                       COALESCE((
                           SELECT SUM(ap.montant_affecte)
                           FROM affectations_paiements ap
                           INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
                           WHERE ap.echeance_id = ec.id
                       ), 0) AS montant_regle
                FROM echeances ec
                INNER JOIN inscriptions i ON i.id = ec.inscription_id
                INNER JOIN etudiants e ON e.id = i.etudiant_id
                INNER JOIN formations f ON f.id = i.formation_id
                INNER JOIN annees_academiques a ON a.id = i.annee_academique_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY ec.date_echeance ASC';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return array_values(array_filter(
            $statement->fetchAll(),
            static fn (array $row): bool => (float) $row['montant_attendu'] > (float) $row['montant_regle']
        ));
    }

    /**
     * Prochaines echeances a venir.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function aVenir(int $limite = 10): array
    {
        $statement = Database::pdo()->prepare(
            "SELECT ec.*, e.matricule, e.nom, e.prenom, f.nom AS formation_nom,
                    COALESCE((
                        SELECT SUM(ap.montant_affecte)
                        FROM affectations_paiements ap
                        INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
                        WHERE ap.echeance_id = ec.id
                    ), 0) AS montant_regle
             FROM echeances ec
             INNER JOIN inscriptions i ON i.id = ec.inscription_id AND i.statut = 'active'
             INNER JOIN etudiants e ON e.id = i.etudiant_id
             INNER JOIN formations f ON f.id = i.formation_id
             WHERE ec.date_echeance >= CURDATE()
             ORDER BY ec.date_echeance ASC
             LIMIT :limite"
        );
        $statement->bindValue(':limite', $limite, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Toutes les echeances avec leur reglement, pour l'ecran de suivi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toutesAvecDetails(?int $anneeId = null): array
    {
        $where = ["i.statut <> 'annulee'"];
        $params = [];

        if ($anneeId !== null) {
            $where[] = 'i.annee_academique_id = :annee';
            $params['annee'] = $anneeId;
        }

        $statement = Database::pdo()->prepare(
            "SELECT ec.*, i.mode_paiement, i.montant_total,
                    e.matricule, e.nom, e.prenom, f.nom AS formation_nom, a.libelle AS annee_libelle,
                    COALESCE((
                        SELECT SUM(ap.montant_affecte)
                        FROM affectations_paiements ap
                        INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
                        WHERE ap.echeance_id = ec.id
                    ), 0) AS montant_regle
             FROM echeances ec
             INNER JOIN inscriptions i ON i.id = ec.inscription_id
             INNER JOIN etudiants e ON e.id = i.etudiant_id
             INNER JOIN formations f ON f.id = i.formation_id
             INNER JOIN annees_academiques a ON a.id = i.annee_academique_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY ec.date_echeance ASC
             LIMIT 500'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * Somme des montants attendus par inscription.
     *
     * @return array<int, float>
     */
    public static function totauxParInscription(): array
    {
        $rows = Database::pdo()->query('SELECT inscription_id, SUM(montant_attendu) AS total FROM echeances GROUP BY inscription_id')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['inscription_id']] = (float) $row['total'];
        }

        return $result;
    }
}

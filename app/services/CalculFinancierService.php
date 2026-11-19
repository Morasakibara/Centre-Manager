<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Source unique de verite pour les soldes, pourcentages et statuts financiers.
 * Tous les calculs sont faits en centimes (entiers) pour eviter les flottants.
 */
final class CalculFinancierService
{
    public const STATUT_SOLDE = 'solde';
    public const STATUT_EN_RETARD = 'en_retard';
    public const STATUT_PARTIEL = 'partiel';
    public const STATUT_A_JOUR = 'a_jour';
    public const STATUT_A_VENIR = 'a_venir';
    public const STATUT_NON_SOLDE = 'non_solde';

    /**
     * @param array<int, int> $inscriptionIds
     * @return array<int, array<string, mixed>>
     */
    public static function situations(array $inscriptionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $inscriptionIds))));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', $ids);
        $pdo = Database::pdo();

        $inscriptions = $pdo->query(
            "SELECT i.id, i.montant_total, i.mode_paiement, i.statut,
                    (SELECT COALESCE(SUM(p.montant), 0)
                     FROM paiements p
                     WHERE p.inscription_id = i.id AND p.statut = 'valide') AS total_paye,
                    (SELECT COALESCE(SUM(ec2.montant_attendu), 0)
                     FROM echeances ec2
                     WHERE ec2.inscription_id = i.id) AS total_echeances
             FROM inscriptions i
             WHERE i.id IN (" . $in . ')'
        )->fetchAll();

        $echeancesParInscription = [];
        $echeances = $pdo->query(
            "SELECT ec.id, ec.inscription_id, ec.libelle, ec.montant_attendu, ec.date_echeance,
                    (SELECT COALESCE(SUM(ap.montant_affecte), 0)
                     FROM affectations_paiements ap
                     INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
                     WHERE ap.echeance_id = ec.id) AS montant_regle
             FROM echeances ec
             WHERE ec.inscription_id IN (" . $in . ')
             ORDER BY ec.date_echeance ASC, ec.id ASC'
        )->fetchAll();

        foreach ($echeances as $echeance) {
            $echeancesParInscription[(int) $echeance['inscription_id']][] = $echeance;
        }

        $today = date('Y-m-d');
        $result = [];

        foreach ($inscriptions as $inscription) {
            $id = (int) $inscription['id'];
            $totalCents = (int) round((float) $inscription['montant_total'] * 100);
            $payeCents = (int) round((float) $inscription['total_paye'] * 100);
            $resteCents = max(0, $totalCents - $payeCents);
            $pourcentage = $totalCents > 0
                ? min(100.0, round($payeCents / $totalCents * 100, 1))
                : ($payeCents > 0 ? 100.0 : 0.0);

            $lignesEcheances = [];
            $retardCents = 0;
            $nbRetard = 0;
            $aPartiel = false;

            foreach ($echeancesParInscription[$id] ?? [] as $echeance) {
                $attenduCents = (int) round((float) $echeance['montant_attendu'] * 100);
                $regleCents = (int) round((float) $echeance['montant_regle'] * 100);
                $resteEcheance = max(0, $attenduCents - $regleCents);
                $enRetard = $resteEcheance > 0 && (string) $echeance['date_echeance'] < $today;

                if ($regleCents > 0 && $resteEcheance > 0) {
                    $aPartiel = true;
                }
                if ($enRetard) {
                    $retardCents += $resteEcheance;
                    $nbRetard++;
                }

                $lignesEcheances[] = $echeance + [
                    'montant_attendu_cents' => $attenduCents,
                    'montant_regle_cents' => $regleCents,
                    'reste_cents' => $resteEcheance,
                    'reste' => $resteEcheance / 100,
                    'en_retard' => $enRetard,
                    'statut_echeance' => $enRetard
                        ? 'en_retard'
                        : ($resteEcheance === 0 ? 'payee' : ($regleCents > 0 ? 'partielle' : 'attente')),
                ];
            }

            $result[$id] = [
                'inscription_id' => $id,
                'mode_paiement' => $inscription['mode_paiement'],
                'statut_inscription' => $inscription['statut'],
                'montant_total_cents' => $totalCents,
                'montant_total' => $totalCents / 100,
                'total_paye_cents' => $payeCents,
                'total_paye' => $payeCents / 100,
                'reste_cents' => $resteCents,
                'reste' => $resteCents / 100,
                'pourcentage' => $pourcentage,
                'echeances' => $lignesEcheances,
                'nb_echeances' => count($lignesEcheances),
                'nb_retard' => $nbRetard,
                'montant_retard_cents' => $retardCents,
                'montant_retard' => $retardCents / 100,
                'statut' => self::calculerStatut($inscription, $resteCents, $lignesEcheances, $aPartiel, $nbRetard),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function situation(int $inscriptionId): array
    {
        $situations = self::situations([$inscriptionId]);

        return $situations[$inscriptionId] ?? [
            'inscription_id' => $inscriptionId,
            'mode_paiement' => 'libre',
            'statut_inscription' => 'inconnue',
            'montant_total_cents' => 0,
            'montant_total' => 0.0,
            'total_paye_cents' => 0,
            'total_paye' => 0.0,
            'reste_cents' => 0,
            'reste' => 0.0,
            'pourcentage' => 0.0,
            'echeances' => [],
            'nb_echeances' => 0,
            'nb_retard' => 0,
            'montant_retard_cents' => 0,
            'montant_retard' => 0.0,
            'statut' => self::STATUT_A_VENIR,
        ];
    }

    /**
     * @param array<string, mixed> $inscription
     * @param array<int, array<string, mixed>> $echeances
     */
    private static function calculerStatut(array $inscription, int $resteCents, array $echeances, bool $aPartiel, int $nbRetard): string
    {
        if ($inscription['statut'] === 'annulee') {
            return 'annulee';
        }

        if ($resteCents === 0) {
            return self::STATUT_SOLDE;
        }

        if ($inscription['mode_paiement'] !== 'tranches' || $echeances === []) {
            return self::STATUT_NON_SOLDE;
        }

        if ($nbRetard > 0) {
            return self::STATUT_EN_RETARD;
        }

        $toutesFutures = true;
        foreach ($echeances as $echeance) {
            if ((string) $echeance['date_echeance'] <= date('Y-m-d')) {
                $toutesFutures = false;
                break;
            }
        }

        if ($toutesFutures && (int) $inscription['total_paye'] === 0) {
            return self::STATUT_A_VENIR;
        }

        if ($aPartiel) {
            return self::STATUT_PARTIEL;
        }

        return self::STATUT_A_JOUR;
    }

    public static function libelleStatut(string $statut): string
    {
        return match ($statut) {
            self::STATUT_SOLDE => 'Soldé',
            self::STATUT_EN_RETARD => 'En retard',
            self::STATUT_PARTIEL => 'Partiellement réglé',
            self::STATUT_A_JOUR => 'À jour',
            self::STATUT_A_VENIR => 'À venir',
            self::STATUT_NON_SOLDE => 'Non soldé',
            'annulee' => 'Annulée',
            default => 'Inconnu',
        };
    }

    /**
     * Totaux globaux sur un ensemble d'inscriptions.
     *
     * @param array<int, array<string, mixed>> $situations
     * @return array<string, float|int>
     */
    public static function totaux(array $situations): array
    {
        $total = 0;
        $paye = 0;
        $reste = 0;
        $retard = 0;
        $soldees = 0;
        $enRetard = 0;

        foreach ($situations as $situation) {
            $total += (int) $situation['montant_total_cents'];
            $paye += (int) $situation['total_paye_cents'];
            $reste += (int) $situation['reste_cents'];
            $retard += (int) $situation['montant_retard_cents'];
            if ($situation['statut'] === self::STATUT_SOLDE) {
                $soldees++;
            }
            if ($situation['statut'] === self::STATUT_EN_RETARD) {
                $enRetard++;
            }
        }

        return [
            'montant_total' => $total / 100,
            'total_paye' => $paye / 100,
            'reste' => $reste / 100,
            'montant_retard' => $retard / 100,
            'nb_soldees' => $soldees,
            'nb_en_retard' => $enRetard,
            'nb_inscriptions' => count($situations),
        ];
    }
}

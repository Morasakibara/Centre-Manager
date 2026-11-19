<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AnneeAcademique;
use App\Models\Echeance;
use App\Models\Inscription;
use App\Models\Paiement;

final class TableauBordService
{
    /**
     * @return array<string, mixed>
     */
    public static function donnees(): array
    {
        $pdo = Database::pdo();
        $annee = AnneeAcademique::courante();

        $totalEtudiants = (int) $pdo->query("SELECT COUNT(*) FROM etudiants WHERE statut <> 'archive'")->fetchColumn();
        $etudiantsActifs = (int) $pdo->query("SELECT COUNT(*) FROM etudiants WHERE statut = 'actif'")->fetchColumn();

        $inscriptionsAnnee = $annee !== null ? Inscription::parAnnee((int) $annee['id']) : [];
        $idsAnnee = array_map(static fn (array $row): int => (int) $row['id'], $inscriptionsAnnee);
        $situationsAnnee = CalculFinancierService::situations($idsAnnee);
        $totauxAnnee = CalculFinancierService::totaux($situationsAnnee);

        $toutesInscriptions = Inscription::actives();
        $idsGlobal = array_map(static fn (array $row): int => (int) $row['id'], $toutesInscriptions);
        $situationsGlobal = CalculFinancierService::situations($idsGlobal);
        $totauxGlobal = CalculFinancierService::totaux($situationsGlobal);

        $debutMois = date('Y-m-01 00:00:00');
        $finMois = date('Y-m-t 23:59:59');
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM paiements
             WHERE statut = 'valide' AND date_paiement BETWEEN :debut AND :fin"
        );
        $stmt->execute(['debut' => $debutMois, 'fin' => $finMois]);
        $recettesMois = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM paiements
             WHERE statut = 'valide' AND DATE(date_paiement) = CURDATE()"
        );
        $stmt->execute();
        $recettesJour = (float) $stmt->fetchColumn();

        // Serie des 6 derniers mois
        $serie = [];
        for ($i = 5; $i >= 0; $i--) {
            $debut = date('Y-m-01', strtotime('-' . $i . ' months'));
            $fin = date('Y-m-t', strtotime('-' . $i . ' months'));
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(montant), 0) FROM paiements
                 WHERE statut = 'valide' AND DATE(date_paiement) BETWEEN :debut AND :fin"
            );
            $stmt->execute(['debut' => $debut, 'fin' => $fin]);
            $serie[] = [
                'label' => self::moisFrancais((int) date('n', strtotime($debut))) . ' ' . date('y', strtotime($debut)),
                'montant' => (float) $stmt->fetchColumn(),
            ];
        }

        $repartition = [
            'solde' => 0,
            'en_retard' => 0,
            'partiel' => 0,
            'a_jour' => 0,
            'a_venir' => 0,
            'non_solde' => 0,
        ];
        foreach ($situationsAnnee as $situation) {
            $repartition[$situation['statut']] = ($repartition[$situation['statut']] ?? 0) + 1;
        }

        $echeancesUrgentes = Echeance::enRetard($annee !== null ? (int) $annee['id'] : null);
        $echeancesUrgentes = array_slice($echeancesUrgentes, 0, 8);

        return [
            'annee' => $annee,
            'total_etudiants' => $totalEtudiants,
            'etudiants_actifs' => $etudiantsActifs,
            'nb_inscriptions_annee' => count($inscriptionsAnnee),
            'totaux_annee' => $totauxAnnee,
            'totaux_global' => $totauxGlobal,
            'recettes_mois' => $recettesMois,
            'recettes_jour' => $recettesJour,
            'serie_recettes' => $serie,
            'repartition' => $repartition,
            'derniers_paiements' => Paiement::recents(8),
            'echeances_urgentes' => $echeancesUrgentes,
            'prochaines_echeances' => array_slice(Echeance::aVenir(6), 0, 6),
        ];
    }

    private static function moisFrancais(int $mois): string
    {
        $noms = [1 => 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

        return $noms[$mois] ?? '';
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AnneeAcademique;
use App\Models\Echeance;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Paiement;

/**
 * Construction des jeux de donnees des rapports.
 * Les vues, le PDF et l'Excel consomment la meme structure.
 */
final class RapportService
{
    public const TYPES = [
        'general' => 'Situation financière générale',
        'recettes' => 'Recettes par période',
        'paiements' => 'Paiements détaillés',
        'soldees' => 'Inscriptions soldées',
        'non_soldees' => 'Inscriptions non soldées',
        'retards' => 'Échéances en retard',
        'individuel' => 'Situation financière individuelle',
        'synthese' => 'Synthèse par formation et année',
    ];

    /**
     * @param array<string, mixed> $filtres
     * @return array<string, mixed>
     */
    public static function construire(string $type, array $filtres): array
    {
        return match ($type) {
            'recettes' => self::recettes($filtres),
            'paiements' => self::paiements($filtres),
            'soldees' => self::inscriptionsParStatut($filtres, 'soldees'),
            'non_soldees' => self::inscriptionsParStatut($filtres, 'non_soldees'),
            'retards' => self::retards($filtres),
            'individuel' => self::individuel($filtres),
            'synthese' => self::synthese($filtres),
            default => self::general($filtres),
        };
    }

    /** @param array<string, mixed> $filtres */
    private static function base(string $type, array $filtres): array
    {
        $centreNom = (string) config('centre.nom', 'Centre');
        $sousTitre = self::periodeTexte($filtres);

        return [
            'type' => $type,
            'titre' => self::TYPES[$type] ?? 'Rapport',
            'sous_titre' => $sousTitre,
            'filtres_texte' => self::filtresTexte($filtres),
            'generee_le' => date('d/m/Y H:i'),
            'centre' => [
                'nom' => $centreNom,
                'adresse' => (string) config('centre.adresse', ''),
                'telephone' => (string) config('centre.telephone', ''),
                'email' => (string) config('centre.email', ''),
            ],
            'resume' => [],
            'sections' => [],
        ];
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    private static function inscriptionsFiltrees(array $filtres): array
    {
        $rows = Inscription::allFiltres([
            'annee' => $filtres['annee_id'] ?? null,
            'formation' => $filtres['formation_id'] ?? null,
            'etudiant' => $filtres['etudiant_id'] ?? null,
        ]);

        $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['statut'] !== 'annulee'));

        if (!empty($filtres['mode_inscription'])) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['mode_paiement'] === $filtres['mode_inscription']
            ));
        }

        if (!empty($filtres['debut'])) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['date_inscription'] >= $filtres['debut']));
        }
        if (!empty($filtres['fin'])) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['date_inscription'] <= $filtres['fin']));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array{situations: array<int, array<string, mixed>>, totaux: array<string, float|int>}
     */
    private static function situationsFiltrees(array $filtres): array
    {
        $inscriptions = self::inscriptionsFiltrees($filtres);
        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $inscriptions
        ));

        if (!empty($filtres['statut_financier'])) {
            $statut = (string) $filtres['statut_financier'];
            $situations = array_filter(
                $situations,
                static fn (array $situation): bool => $situation['statut'] === $statut
            );
        }

        return [
            'situations' => $situations,
            'totaux' => CalculFinancierService::totaux($situations),
            'inscriptions' => $inscriptions,
        ];
    }

    /** @param array<string, mixed> $filtres */
    private static function general(array $filtres): array
    {
        $rapport = self::base('general', $filtres);
        $donnees = self::situationsFiltrees($filtres);
        $situations = $donnees['situations'];
        $inscriptions = $donnees['inscriptions'];

        $etudiants = [];
        foreach ($inscriptions as $inscription) {
            if (isset($situations[(int) $inscription['id']])) {
                $etudiants[(int) $inscription['etudiant_id']] = true;
            }
        }

        $totaux = $donnees['totaux'];

        $rapport['resume'] = [
            ['libelle' => 'Étudiants concernés', 'valeur' => count($etudiants), 'type' => 'int'],
            ['libelle' => 'Inscriptions concernées', 'valeur' => (int) $totaux['nb_inscriptions'], 'type' => 'int'],
            ['libelle' => 'Montant attendu', 'valeur' => (float) $totaux['montant_total'], 'type' => 'money'],
            ['libelle' => 'Paiements validés', 'valeur' => (float) $totaux['total_paye'], 'type' => 'money'],
            ['libelle' => 'Reste à encaisser', 'valeur' => (float) $totaux['reste'], 'type' => 'money'],
            ['libelle' => 'Inscriptions soldées', 'valeur' => (int) $totaux['nb_soldees'], 'type' => 'int'],
            ['libelle' => 'Inscriptions en retard', 'valeur' => (int) $totaux['nb_en_retard'], 'type' => 'int'],
        ];

        if (!empty($filtres['debut']) || !empty($filtres['fin'])) {
            $recettes = Paiement::validesPourPeriode(
                $filtres['debut'] ?? null,
                $filtres['fin'] ?? null,
                isset($filtres['annee_id']) ? (int) $filtres['annee_id'] : null,
                isset($filtres['formation_id']) ? (int) $filtres['formation_id'] : null
            );
            $totalPeriode = array_sum(array_map(static fn (array $row): float => (float) $row['montant'], $recettes));
            $rapport['resume'][] = [
                'libelle' => 'Recettes encaissées sur la période',
                'valeur' => $totalPeriode,
                'type' => 'money',
            ];
        }

        // Synthese par formation et annee
        $groupes = [];
        foreach ($inscriptions as $inscription) {
            $id = (int) $inscription['id'];
            if (!isset($situations[$id])) {
                continue;
            }
            $cle = $inscription['formation_nom'] . '|' . $inscription['annee_libelle'];
            if (!isset($groupes[$cle])) {
                $groupes[$cle] = [
                    'formation' => $inscription['formation_nom'],
                    'annee' => $inscription['annee_libelle'],
                    'nb' => 0,
                    'attendu' => 0,
                    'paye' => 0,
                    'reste' => 0,
                ];
            }
            $groupes[$cle]['nb']++;
            $groupes[$cle]['attendu'] += (int) $situations[$id]['montant_total_cents'];
            $groupes[$cle]['paye'] += (int) $situations[$id]['total_paye_cents'];
            $groupes[$cle]['reste'] += (int) $situations[$id]['reste_cents'];
        }

        $lignesGroupees = [];
        $totauxGroupe = ['nb' => 0, 'attendu' => 0, 'paye' => 0, 'reste' => 0];
        ksort($groupes);
        foreach ($groupes as $groupe) {
            $lignesGroupees[] = [
                'formation' => $groupe['formation'],
                'annee' => $groupe['annee'],
                'nb' => $groupe['nb'],
                'attendu' => $groupe['attendu'] / 100,
                'paye' => $groupe['paye'] / 100,
                'reste' => $groupe['reste'] / 100,
            ];
            $totauxGroupe['nb'] += $groupe['nb'];
            $totauxGroupe['attendu'] += $groupe['attendu'];
            $totauxGroupe['paye'] += $groupe['paye'];
            $totauxGroupe['reste'] += $groupe['reste'];
        }

        $rapport['sections'][] = [
            'titre' => 'Synthèse par formation et année académique',
            'colonnes' => [
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'annee', 'libelle' => 'Année', 'type' => 'texte'],
                ['cle' => 'nb', 'libelle' => 'Inscriptions', 'type' => 'int'],
                ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                ['cle' => 'paye', 'libelle' => 'Payé', 'type' => 'money'],
                ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
            ],
            'lignes' => $lignesGroupees,
            'totaux' => [
                'nb' => $totauxGroupe['nb'],
                'attendu' => $totauxGroupe['attendu'] / 100,
                'paye' => $totauxGroupe['paye'] / 100,
                'reste' => $totauxGroupe['reste'] / 100,
            ],
        ];

        // Detail des inscriptions
        $detail = [];
        $detailTotaux = ['attendu' => 0, 'paye' => 0, 'reste' => 0];
        foreach ($inscriptions as $inscription) {
            $id = (int) $inscription['id'];
            if (!isset($situations[$id])) {
                continue;
            }
            $situation = $situations[$id];
            $detail[] = [
                'matricule' => $inscription['matricule'],
                'etudiant' => $inscription['etudiant_nom'] . ' ' . $inscription['etudiant_prenom'],
                'formation' => $inscription['formation_nom'],
                'annee' => $inscription['annee_libelle'],
                'mode' => mode_paiement_label($inscription['mode_paiement']),
                'attendu' => $situation['montant_total'],
                'paye' => $situation['total_paye'],
                'reste' => $situation['reste'],
                'statut' => CalculFinancierService::libelleStatut($situation['statut']),
            ];
            $detailTotaux['attendu'] += (int) $situation['montant_total_cents'];
            $detailTotaux['paye'] += (int) $situation['total_paye_cents'];
            $detailTotaux['reste'] += (int) $situation['reste_cents'];
        }

        $rapport['sections'][] = [
            'titre' => 'Détail des inscriptions',
            'colonnes' => [
                ['cle' => 'matricule', 'libelle' => 'Matricule', 'type' => 'texte'],
                ['cle' => 'etudiant', 'libelle' => 'Étudiant', 'type' => 'texte'],
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'annee', 'libelle' => 'Année', 'type' => 'texte'],
                ['cle' => 'mode', 'libelle' => 'Mode', 'type' => 'texte'],
                ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                ['cle' => 'paye', 'libelle' => 'Payé', 'type' => 'money'],
                ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'texte'],
            ],
            'lignes' => $detail,
            'totaux' => [
                'attendu' => $detailTotaux['attendu'] / 100,
                'paye' => $detailTotaux['paye'] / 100,
                'reste' => $detailTotaux['reste'] / 100,
            ],
        ];

        // Retards
        $retards = self::lignesRetards($filtres);
        if ($retards['lignes'] !== []) {
            $rapport['sections'][] = [
                'titre' => 'Impayés et échéances en retard',
                'colonnes' => $retards['colonnes'],
                'lignes' => $retards['lignes'],
                'totaux' => $retards['totaux'],
            ];
        }

        return $rapport;
    }

    /** @param array<string, mixed> $filtres */
    private static function recettes(array $filtres): array
    {
        $rapport = self::base('recettes', $filtres);
        $paiements = Paiement::validesPourPeriode(
            $filtres['debut'] ?? null,
            $filtres['fin'] ?? null,
            isset($filtres['annee_id']) ? (int) $filtres['annee_id'] : null,
            isset($filtres['formation_id']) ? (int) $filtres['formation_id'] : null
        );

        if (!empty($filtres['mode_paiement'])) {
            $paiements = array_values(array_filter(
                $paiements,
                static fn (array $row): bool => $row['mode_paiement'] === $filtres['mode_paiement']
            ));
        }

        $lignes = [];
        $total = 0.0;
        $parMode = [];
        foreach ($paiements as $paiement) {
            $lignes[] = [
                'date' => substr((string) $paiement['date_paiement'], 0, 10),
                'recu' => (string) $paiement['numero_recu'],
                'matricule' => $paiement['matricule'],
                'etudiant' => $paiement['nom'] . ' ' . $paiement['prenom'],
                'formation' => $paiement['formation_nom'],
                'annee' => $paiement['annee_libelle'],
                'mode' => mode_paiement_label((string) $paiement['mode_paiement']),
                'montant' => (float) $paiement['montant'],
                'utilisateur' => $paiement['encaisse_par'],
            ];
            $total += (float) $paiement['montant'];
            $parMode[$paiement['mode_paiement']] = ($parMode[$paiement['mode_paiement']] ?? 0) + (float) $paiement['montant'];
        }

        $rapport['resume'] = [
            ['libelle' => 'Nombre de paiements validés', 'valeur' => count($lignes), 'type' => 'int'],
            ['libelle' => 'Total des recettes validées', 'valeur' => $total, 'type' => 'money'],
            ['libelle' => 'Devise', 'valeur' => (string) config('devise', 'XAF'), 'type' => 'texte'],
        ];

        $rapport['sections'][] = [
            'titre' => 'Détail des recettes',
            'colonnes' => [
                ['cle' => 'date', 'libelle' => 'Date', 'type' => 'date'],
                ['cle' => 'recu', 'libelle' => 'N° reçu', 'type' => 'texte'],
                ['cle' => 'matricule', 'libelle' => 'Matricule', 'type' => 'texte'],
                ['cle' => 'etudiant', 'libelle' => 'Étudiant', 'type' => 'texte'],
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'mode', 'libelle' => 'Mode', 'type' => 'texte'],
                ['cle' => 'montant', 'libelle' => 'Montant', 'type' => 'money'],
                ['cle' => 'utilisateur', 'libelle' => 'Encaissé par', 'type' => 'texte'],
            ],
            'lignes' => $lignes,
            'totaux' => ['montant' => $total],
        ];

        $lignesMode = [];
        foreach ($parMode as $mode => $montant) {
            $lignesMode[] = [
                'mode' => mode_paiement_label((string) $mode),
                'montant' => $montant,
            ];
        }

        $rapport['sections'][] = [
            'titre' => 'Répartition par mode de paiement',
            'colonnes' => [
                ['cle' => 'mode', 'libelle' => 'Mode de paiement', 'type' => 'texte'],
                ['cle' => 'montant', 'libelle' => 'Montant', 'type' => 'money'],
            ],
            'lignes' => $lignesMode,
            'totaux' => ['montant' => $total],
        ];

        return $rapport;
    }

    /** @param array<string, mixed> $filtres */
    private static function paiements(array $filtres): array
    {
        $rapport = self::base('paiements', $filtres);
        $resultat = Paiement::paginate([
            'q' => $filtres['q'] ?? null,
            'annee' => $filtres['annee_id'] ?? null,
            'formation' => $filtres['formation_id'] ?? null,
            'mode' => $filtres['mode_paiement'] ?? null,
            'statut' => $filtres['statut_paiement'] ?? null,
            'debut' => $filtres['debut'] ?? null,
            'fin' => $filtres['fin'] ?? null,
            'etudiant' => $filtres['etudiant_id'] ?? null,
        ], 1, 10000);

        $lignes = [];
        $totalValide = 0.0;
        $totalAnnule = 0.0;
        foreach ($resultat['rows'] as $paiement) {
            $montant = (float) $paiement['montant'];
            if ($paiement['statut'] === 'valide') {
                $totalValide += $montant;
            } else {
                $totalAnnule += $montant;
            }
            $lignes[] = [
                'date' => substr((string) $paiement['date_paiement'], 0, 10),
                'recu' => (string) $paiement['numero_recu'],
                'matricule' => $paiement['matricule'],
                'etudiant' => $paiement['nom'] . ' ' . $paiement['prenom'],
                'formation' => $paiement['formation_nom'],
                'mode' => mode_paiement_label((string) $paiement['mode_paiement']),
                'montant' => $montant,
                'statut' => $paiement['statut'] === 'valide' ? 'Validé' : 'Annulé',
                'utilisateur' => $paiement['encaisse_par'],
            ];
        }

        $rapport['resume'] = [
            ['libelle' => 'Paiements listés', 'valeur' => count($lignes), 'type' => 'int'],
            ['libelle' => 'Total validé', 'valeur' => $totalValide, 'type' => 'money'],
            ['libelle' => 'Total annulé (non comptabilisé)', 'valeur' => $totalAnnule, 'type' => 'money'],
        ];

        $rapport['sections'][] = [
            'titre' => 'Paiements détaillés',
            'colonnes' => [
                ['cle' => 'date', 'libelle' => 'Date', 'type' => 'date'],
                ['cle' => 'recu', 'libelle' => 'N° reçu', 'type' => 'texte'],
                ['cle' => 'matricule', 'libelle' => 'Matricule', 'type' => 'texte'],
                ['cle' => 'etudiant', 'libelle' => 'Étudiant', 'type' => 'texte'],
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'mode', 'libelle' => 'Mode', 'type' => 'texte'],
                ['cle' => 'montant', 'libelle' => 'Montant', 'type' => 'money'],
                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'texte'],
                ['cle' => 'utilisateur', 'libelle' => 'Encaissé par', 'type' => 'texte'],
            ],
            'lignes' => $lignes,
            'totaux' => ['montant' => $totalValide],
        ];

        return $rapport;
    }

    /** @param array<string, mixed> $filtres */
    private static function inscriptionsParStatut(array $filtres, string $mode): array
    {
        $type = $mode === 'soldees' ? 'soldees' : 'non_soldees';
        $rapport = self::base($type, $filtres);
        $donnees = self::situationsFiltrees($filtres);
        $situations = $donnees['situations'];
        $inscriptions = $donnees['inscriptions'];

        $lignes = [];
        $totaux = ['attendu' => 0, 'paye' => 0, 'reste' => 0];
        foreach ($inscriptions as $inscription) {
            $id = (int) $inscription['id'];
            if (!isset($situations[$id])) {
                continue;
            }
            $situation = $situations[$id];
            $estSoldee = $situation['statut'] === CalculFinancierService::STATUT_SOLDE;

            if ($mode === 'soldees' && !$estSoldee) {
                continue;
            }
            if ($mode === 'non_soldees' && $estSoldee) {
                continue;
            }

            $lignes[] = [
                'matricule' => $inscription['matricule'],
                'etudiant' => $inscription['etudiant_nom'] . ' ' . $inscription['etudiant_prenom'],
                'formation' => $inscription['formation_nom'],
                'annee' => $inscription['annee_libelle'],
                'mode' => mode_paiement_label($inscription['mode_paiement']),
                'attendu' => $situation['montant_total'],
                'paye' => $situation['total_paye'],
                'reste' => $situation['reste'],
                'statut' => CalculFinancierService::libelleStatut($situation['statut']),
            ];
            $totaux['attendu'] += (int) $situation['montant_total_cents'];
            $totaux['paye'] += (int) $situation['total_paye_cents'];
            $totaux['reste'] += (int) $situation['reste_cents'];
        }

        $rapport['resume'] = [
            ['libelle' => 'Inscriptions listées', 'valeur' => count($lignes), 'type' => 'int'],
            ['libelle' => 'Montant attendu', 'valeur' => $totaux['attendu'] / 100, 'type' => 'money'],
            ['libelle' => 'Total payé', 'valeur' => $totaux['paye'] / 100, 'type' => 'money'],
            ['libelle' => 'Reste dû', 'valeur' => $totaux['reste'] / 100, 'type' => 'money'],
        ];

        $rapport['sections'][] = [
            'titre' => $mode === 'soldees' ? 'Inscriptions soldées' : 'Inscriptions non soldées',
            'colonnes' => [
                ['cle' => 'matricule', 'libelle' => 'Matricule', 'type' => 'texte'],
                ['cle' => 'etudiant', 'libelle' => 'Étudiant', 'type' => 'texte'],
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'annee', 'libelle' => 'Année', 'type' => 'texte'],
                ['cle' => 'mode', 'libelle' => 'Mode', 'type' => 'texte'],
                ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                ['cle' => 'paye', 'libelle' => 'Payé', 'type' => 'money'],
                ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'texte'],
            ],
            'lignes' => $lignes,
            'totaux' => [
                'attendu' => $totaux['attendu'] / 100,
                'paye' => $totaux['paye'] / 100,
                'reste' => $totaux['reste'] / 100,
            ],
        ];

        return $rapport;
    }

    /** @param array<string, mixed> $filtres */
    private static function retards(array $filtres): array
    {
        $rapport = self::base('retards', $filtres);
        $donnees = self::lignesRetards($filtres);

        $rapport['resume'] = [
            ['libelle' => 'Échéances en retard', 'valeur' => count($donnees['lignes']), 'type' => 'int'],
            ['libelle' => 'Montant restant dû', 'valeur' => $donnees['totaux']['reste'] ?? 0, 'type' => 'money'],
        ];
        $rapport['sections'][] = [
            'titre' => 'Échéances échues impayées',
            'colonnes' => $donnees['colonnes'],
            'lignes' => $donnees['lignes'],
            'totaux' => $donnees['totaux'],
        ];

        return $rapport;
    }

    /** @param array<string, mixed> $filtres */
    private static function individuel(array $filtres): array
    {
        $rapport = self::base('individuel', $filtres);
        $etudiantId = (int) ($filtres['etudiant_id'] ?? 0);
        $etudiant = $etudiantId > 0 ? Etudiant::find($etudiantId) : null;

        if ($etudiant === null) {
            $rapport['resume'] = [['libelle' => 'Étudiant', 'valeur' => 'Aucun étudiant sélectionné', 'type' => 'texte']];
            return $rapport;
        }

        $inscriptions = Inscription::deEtudiant($etudiantId);
        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $inscriptions
        ));

        $rapport['sous_titre'] = sprintf(
            '%s %s — Matricule %s',
            $etudiant['prenom'],
            $etudiant['nom'],
            $etudiant['matricule']
        );

        $rapport['resume'] = [
            ['libelle' => 'Téléphone', 'valeur' => (string) $etudiant['telephone'], 'type' => 'texte'],
            ['libelle' => 'Statut', 'valeur' => ucfirst((string) $etudiant['statut']), 'type' => 'texte'],
            ['libelle' => 'Date d\'enregistrement', 'valeur' => date_fr((string) $etudiant['date_enregistrement']), 'type' => 'texte'],
        ];

        $lignesInscriptions = [];
        $lignesPaiements = [];
        $lignesEcheances = [];
        $totaux = ['attendu' => 0, 'paye' => 0, 'reste' => 0];

        foreach ($inscriptions as $inscription) {
            $id = (int) $inscription['id'];
            $situation = $situations[$id] ?? null;
            if ($situation === null) {
                continue;
            }

            $lignesInscriptions[] = [
                'formation' => $inscription['formation_nom'],
                'annee' => $inscription['annee_libelle'],
                'mode' => mode_paiement_label($inscription['mode_paiement']),
                'attendu' => $situation['montant_total'],
                'paye' => $situation['total_paye'],
                'reste' => $situation['reste'],
                'statut' => CalculFinancierService::libelleStatut($situation['statut']),
            ];
            $totaux['attendu'] += (int) $situation['montant_total_cents'];
            $totaux['paye'] += (int) $situation['total_paye_cents'];
            $totaux['reste'] += (int) $situation['reste_cents'];

            foreach (Paiement::deInscription($id) as $paiement) {
                $lignesPaiements[] = [
                    'date' => substr((string) $paiement['date_paiement'], 0, 10),
                    'recu' => (string) $paiement['numero_recu'],
                    'formation' => $paiement['formation_nom'],
                    'montant' => (float) $paiement['montant'],
                    'mode' => mode_paiement_label((string) $paiement['mode_paiement']),
                    'statut' => $paiement['statut'] === 'valide' ? 'Validé' : 'Annulé',
                ];
            }

            foreach ($situation['echeances'] as $echeance) {
                $lignesEcheances[] = [
                    'formation' => $inscription['formation_nom'],
                    'libelle' => $echeance['libelle'],
                    'date' => $echeance['date_echeance'],
                    'attendu' => $echeance['montant_attendu'],
                    'regle' => $echeance['montant_regle'],
                    'reste' => $echeance['reste'],
                    'etat' => $echeance['en_retard'] ? 'En retard' : ucfirst((string) $echeance['statut_echeance']),
                ];
            }
        }

        $rapport['resume'][] = ['libelle' => 'Total attendu', 'valeur' => $totaux['attendu'] / 100, 'type' => 'money'];
        $rapport['resume'][] = ['libelle' => 'Total payé', 'valeur' => $totaux['paye'] / 100, 'type' => 'money'];
        $rapport['resume'][] = ['libelle' => 'Solde restant', 'valeur' => $totaux['reste'] / 100, 'type' => 'money'];

        $rapport['sections'][] = [
            'titre' => 'Inscriptions',
            'colonnes' => [
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'annee', 'libelle' => 'Année', 'type' => 'texte'],
                ['cle' => 'mode', 'libelle' => 'Mode', 'type' => 'texte'],
                ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                ['cle' => 'paye', 'libelle' => 'Payé', 'type' => 'money'],
                ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'texte'],
            ],
            'lignes' => $lignesInscriptions,
            'totaux' => [
                'attendu' => $totaux['attendu'] / 100,
                'paye' => $totaux['paye'] / 100,
                'reste' => $totaux['reste'] / 100,
            ],
        ];

        if ($lignesPaiements !== []) {
            $rapport['sections'][] = [
                'titre' => 'Paiements enregistrés',
                'colonnes' => [
                    ['cle' => 'date', 'libelle' => 'Date', 'type' => 'date'],
                    ['cle' => 'recu', 'libelle' => 'N° reçu', 'type' => 'texte'],
                    ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                    ['cle' => 'montant', 'libelle' => 'Montant', 'type' => 'money'],
                    ['cle' => 'mode', 'libelle' => 'Mode', 'type' => 'texte'],
                    ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'texte'],
                ],
                'lignes' => $lignesPaiements,
                'totaux' => [],
            ];
        }

        if ($lignesEcheances !== []) {
            $rapport['sections'][] = [
                'titre' => 'Échéancier',
                'colonnes' => [
                    ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                    ['cle' => 'libelle', 'libelle' => 'Échéance', 'type' => 'texte'],
                    ['cle' => 'date', 'libelle' => 'Date limite', 'type' => 'date'],
                    ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                    ['cle' => 'regle', 'libelle' => 'Réglé', 'type' => 'money'],
                    ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
                    ['cle' => 'etat', 'libelle' => 'État', 'type' => 'texte'],
                ],
                'lignes' => $lignesEcheances,
                'totaux' => [],
            ];
        }

        return $rapport;
    }

    /** @param array<string, mixed> $filtres */
    private static function synthese(array $filtres): array
    {
        $rapport = self::base('synthese', $filtres);
        $donnees = self::situationsFiltrees($filtres);
        $situations = $donnees['situations'];
        $inscriptions = $donnees['inscriptions'];

        $groupes = [];
        foreach ($inscriptions as $inscription) {
            $id = (int) $inscription['id'];
            if (!isset($situations[$id])) {
                continue;
            }
            $cle = $inscription['formation_nom'] . '|' . $inscription['annee_libelle'];
            if (!isset($groupes[$cle])) {
                $groupes[$cle] = [
                    'formation' => $inscription['formation_nom'],
                    'annee' => $inscription['annee_libelle'],
                    'nb' => 0,
                    'attendu' => 0,
                    'paye' => 0,
                    'reste' => 0,
                    'retard' => 0,
                ];
            }
            $groupes[$cle]['nb']++;
            $groupes[$cle]['attendu'] += (int) $situations[$id]['montant_total_cents'];
            $groupes[$cle]['paye'] += (int) $situations[$id]['total_paye_cents'];
            $groupes[$cle]['reste'] += (int) $situations[$id]['reste_cents'];
            $groupes[$cle]['retard'] += (int) $situations[$id]['montant_retard_cents'];
        }

        ksort($groupes);
        $lignes = [];
        $totaux = ['nb' => 0, 'attendu' => 0, 'paye' => 0, 'reste' => 0, 'taux' => 0.0];
        foreach ($groupes as $groupe) {
            $taux = $groupe['attendu'] > 0 ? round($groupe['paye'] / $groupe['attendu'] * 100, 1) : 0.0;
            $lignes[] = [
                'formation' => $groupe['formation'],
                'annee' => $groupe['annee'],
                'nb' => $groupe['nb'],
                'attendu' => $groupe['attendu'] / 100,
                'paye' => $groupe['paye'] / 100,
                'reste' => $groupe['reste'] / 100,
                'taux' => $taux,
            ];
            $totaux['nb'] += $groupe['nb'];
            $totaux['attendu'] += $groupe['attendu'];
            $totaux['paye'] += $groupe['paye'];
            $totaux['reste'] += $groupe['reste'];
        }
        $totaux['taux'] = $totaux['attendu'] > 0 ? round($totaux['paye'] / $totaux['attendu'] * 100, 1) : 0.0;

        $rapport['resume'] = [
            ['libelle' => 'Formations concernées', 'valeur' => count($groupes), 'type' => 'int'],
            ['libelle' => 'Inscriptions', 'valeur' => (int) $totaux['nb'], 'type' => 'int'],
            ['libelle' => 'Montant attendu', 'valeur' => $totaux['attendu'] / 100, 'type' => 'money'],
            ['libelle' => 'Montant encaissé', 'valeur' => $totaux['paye'] / 100, 'type' => 'money'],
            ['libelle' => 'Reste à encaisser', 'valeur' => $totaux['reste'] / 100, 'type' => 'money'],
            ['libelle' => 'Taux de recouvrement', 'valeur' => $totaux['taux'] . ' %', 'type' => 'texte'],
        ];

        $rapport['sections'][] = [
            'titre' => 'Synthèse par formation et année académique',
            'colonnes' => [
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'annee', 'libelle' => 'Année', 'type' => 'texte'],
                ['cle' => 'nb', 'libelle' => 'Inscriptions', 'type' => 'int'],
                ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                ['cle' => 'paye', 'libelle' => 'Encaissé', 'type' => 'money'],
                ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
                ['cle' => 'taux', 'libelle' => 'Taux (%)', 'type' => 'int'],
            ],
            'lignes' => $lignes,
            'totaux' => [
                'nb' => $totaux['nb'],
                'attendu' => $totaux['attendu'] / 100,
                'paye' => $totaux['paye'] / 100,
                'reste' => $totaux['reste'] / 100,
                'taux' => $totaux['taux'],
            ],
        ];

        return $rapport;
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array{colonnes: array<int, array<string, string>>, lignes: array<int, array<string, mixed>>, totaux: array<string, float>}
     */
    private static function lignesRetards(array $filtres): array
    {
        $lignesBrutes = Echeance::enRetard(
            isset($filtres['annee_id']) ? (int) $filtres['annee_id'] : null,
            $filtres['debut'] ?? null,
            $filtres['fin'] ?? null
        );

        if (!empty($filtres['formation_id'])) {
            $lignesBrutes = array_values(array_filter(
                $lignesBrutes,
                static fn (array $row): bool => (int) $row['formation_id'] === (int) $filtres['formation_id']
            ));
        }

        $lignes = [];
        $totalReste = 0.0;
        foreach ($lignesBrutes as $row) {
            $reste = (float) $row['montant_attendu'] - (float) $row['montant_regle'];
            $jours = max(0, (int) floor((time() - strtotime((string) $row['date_echeance'])) / 86400));
            $lignes[] = [
                'matricule' => $row['matricule'],
                'etudiant' => $row['nom'] . ' ' . $row['prenom'],
                'formation' => $row['formation_nom'],
                'libelle' => $row['libelle'],
                'date' => $row['date_echeance'],
                'jours' => $jours,
                'attendu' => (float) $row['montant_attendu'],
                'regle' => (float) $row['montant_regle'],
                'reste' => $reste,
            ];
            $totalReste += $reste;
        }

        return [
            'colonnes' => [
                ['cle' => 'matricule', 'libelle' => 'Matricule', 'type' => 'texte'],
                ['cle' => 'etudiant', 'libelle' => 'Étudiant', 'type' => 'texte'],
                ['cle' => 'formation', 'libelle' => 'Formation', 'type' => 'texte'],
                ['cle' => 'libelle', 'libelle' => 'Échéance', 'type' => 'texte'],
                ['cle' => 'date', 'libelle' => 'Date limite', 'type' => 'date'],
                ['cle' => 'jours', 'libelle' => 'Jours de retard', 'type' => 'int'],
                ['cle' => 'attendu', 'libelle' => 'Attendu', 'type' => 'money'],
                ['cle' => 'regle', 'libelle' => 'Réglé', 'type' => 'money'],
                ['cle' => 'reste', 'libelle' => 'Reste', 'type' => 'money'],
            ],
            'lignes' => $lignes,
            'totaux' => ['reste' => $totalReste],
        ];
    }

    /** @param array<string, mixed> $filtres */
    public static function periodeTexte(array $filtres): string
    {
        $debut = !empty($filtres['debut']) ? date_fr((string) $filtres['debut']) : null;
        $fin = !empty($filtres['fin']) ? date_fr((string) $filtres['fin']) : null;

        if ($debut !== null && $fin !== null) {
            return 'Période du ' . $debut . ' au ' . $fin;
        }
        if ($debut !== null) {
            return 'À partir du ' . $debut;
        }
        if ($fin !== null) {
            return 'Jusqu\'au ' . $fin;
        }

        return 'Toutes périodes — situation cumulée';
    }

    /** @param array<string, mixed> $filtres */
    public static function filtresTexte(array $filtres): string
    {
        $morceaux = [self::periodeTexte($filtres)];

        if (!empty($filtres['annee_id'])) {
            $annee = AnneeAcademique::find((int) $filtres['annee_id']);
            $morceaux[] = 'Année : ' . ($annee['libelle'] ?? '—');
        }
        if (!empty($filtres['formation_id'])) {
            $formation = Formation::find((int) $filtres['formation_id']);
            $morceaux[] = 'Formation : ' . ($formation['nom'] ?? '—');
        }
        if (!empty($filtres['mode_paiement'])) {
            $morceaux[] = 'Mode : ' . mode_paiement_label((string) $filtres['mode_paiement']);
        }
        if (!empty($filtres['statut_financier'])) {
            $morceaux[] = 'Statut financier : ' . CalculFinancierService::libelleStatut((string) $filtres['statut_financier']);
        }

        return implode(' · ', $morceaux);
    }

    /**
     * Valeurs disponibles pour les listes deroulantes des filtres.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'annees' => AnneeAcademique::all(),
            'formations' => Formation::all(),
            'etudiants' => Etudiant::tousActifs(),
            'types' => self::TYPES,
        ];
    }
}

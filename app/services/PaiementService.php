<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use DomainException;
use PDO;

/**
 * Enregistrement et annulation des paiements.
 * Toute operation financiere est transactionnelle et verrouille l'inscription.
 */
final class PaiementService
{
    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, paiement_id?: int}
     */
    public static function enregistrer(array $data, int $utilisateurId): array
    {
        $inscriptionId = (int) ($data['inscription_id'] ?? 0);
        $montantCents = Money::toCents($data['montant'] ?? 0);

        if ($inscriptionId <= 0) {
            return ['success' => false, 'message' => 'Inscription invalide.'];
        }

        if ($montantCents <= 0) {
            return ['success' => false, 'message' => 'Le montant du paiement doit être strictement positif.'];
        }

        $datePaiement = self::normaliserDate((string) ($data['date_paiement'] ?? ''));
        if ($datePaiement === null) {
            return ['success' => false, 'message' => 'La date de paiement est invalide.'];
        }

        $mode = (string) ($data['mode_paiement'] ?? 'especes');
        $modesAutorises = ['especes', 'orange_money', 'mtn_mobile_money', 'virement', 'autre'];
        if (!in_array($mode, $modesAutorises, true)) {
            return ['success' => false, 'message' => 'Mode de paiement invalide.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare("SELECT * FROM inscriptions WHERE id = :id FOR UPDATE");
            $lock->execute(['id' => $inscriptionId]);
            $inscription = $lock->fetch();

            if ($inscription === false) {
                throw new DomainException('Inscription introuvable.');
            }

            if ($inscription['statut'] !== 'active') {
                throw new DomainException('Seule une inscription active peut recevoir un paiement.');
            }

            $somme = $pdo->prepare(
                "SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE inscription_id = :id AND statut = 'valide'"
            );
            $somme->execute(['id' => $inscriptionId]);
            $dejaPayeCents = Money::toCents($somme->fetchColumn());
            $montantTotalCents = Money::toCents($inscription['montant_total']);
            $resteCents = max(0, $montantTotalCents - $dejaPayeCents);

            if ($montantCents > $resteCents) {
                throw new DomainException(sprintf(
                    'Le paiement dépasse le solde de l\'inscription (%s %s restants).',
                    number_format($resteCents / 100, 0, ',', ' '),
                    (string) config('devise', 'XAF')
                ));
            }

            $insert = $pdo->prepare(
                'INSERT INTO paiements
                    (inscription_id, utilisateur_id, montant, mode_paiement, reference, observation, date_paiement, statut)
                 VALUES
                    (:inscription, :utilisateur, :montant, :mode, :reference, :observation, :date, :statut)'
            );
            $insert->execute([
                'inscription' => $inscriptionId,
                'utilisateur' => $utilisateurId,
                'montant' => Money::fromCents($montantCents),
                'mode' => $mode,
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'observation' => trim((string) ($data['observation'] ?? '')) ?: null,
                'date' => $datePaiement,
                'statut' => 'valide',
            ]);

            $paiementId = (int) $pdo->lastInsertId();

            $numeroRecu = 'REC-' . date('Y', strtotime($datePaiement)) . '-' . str_pad((string) $paiementId, 6, '0', STR_PAD_LEFT);
            $update = $pdo->prepare('UPDATE paiements SET numero_recu = :recu WHERE id = :id');
            $update->execute(['recu' => $numeroRecu, 'id' => $paiementId]);

            $affecte = 0;
            if ($inscription['mode_paiement'] === 'tranches') {
                $affecte = self::affecterFIFO($paiementId, $inscriptionId, $montantCents);

                if ($affecte > $montantCents) {
                    throw new DomainException('Incohérence dans la répartition du paiement.');
                }
            }

            AuditService::log('paiement_creation', 'paiement', $paiementId, [
                'inscription_id' => $inscriptionId,
                'montant' => Money::fromCents($montantCents),
                'mode' => $mode,
                'numero_recu' => $numeroRecu,
                'affecte' => Money::fromCents($affecte),
            ], $utilisateurId);

            $pdo->commit();

            return [
                'success' => true,
                'message' => sprintf('Paiement de %s %s enregistré (reçu %s).', number_format($montantCents / 100, 0, ',', ' '), (string) config('devise', 'XAF'), $numeroRecu),
                'paiement_id' => $paiementId,
            ];
        } catch (DomainException $exception) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            Database::log('Erreur paiement : ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Une erreur technique a empêché l\'enregistrement du paiement.'];
        }
    }

    /**
     * Repartit un paiement sur les echeances impayees les plus anciennes (FIFO).
     * Retourne le montant affecte en centimes.
     */
    public static function affecterFIFO(int $paiementId, int $inscriptionId, int $montantCents): int
    {
        $pdo = Database::pdo();

        $echeances = $pdo->prepare(
            'SELECT * FROM echeances WHERE inscription_id = :id ORDER BY date_echeance ASC, id ASC FOR UPDATE'
        );
        $echeances->execute(['id' => $inscriptionId]);
        $lignes = $echeances->fetchAll();

        if ($lignes === []) {
            return 0;
        }

        $affectations = $pdo->prepare(
            "SELECT ap.echeance_id, COALESCE(SUM(ap.montant_affecte), 0) AS total
             FROM affectations_paiements ap
             INNER JOIN paiements p ON p.id = ap.paiement_id AND p.statut = 'valide'
             WHERE ap.inscription_id = :id
             GROUP BY ap.echeance_id"
        );
        $affectations->execute(['id' => $inscriptionId]);
        $dejaAffecte = [];
        foreach ($affectations->fetchAll() as $row) {
            $dejaAffecte[(int) $row['echeance_id']] = Money::toCents($row['total']);
        }

        $restant = $montantCents;
        $totalAffecte = 0;

        $insert = $pdo->prepare(
            'INSERT INTO affectations_paiements (paiement_id, echeance_id, inscription_id, montant_affecte)
             VALUES (:paiement, :echeance, :inscription, :montant)'
        );

        foreach ($lignes as $echeance) {
            if ($restant <= 0) {
                break;
            }

            $echeanceId = (int) $echeance['id'];
            $attenduCents = Money::toCents($echeance['montant_attendu']);
            $regleCents = $dejaAffecte[$echeanceId] ?? 0;
            $resteEcheance = max(0, $attenduCents - $regleCents);

            if ($resteEcheance <= 0) {
                continue;
            }

            $affectation = min($restant, $resteEcheance);
            if ($affectation <= 0) {
                continue;
            }

            $insert->execute([
                'paiement' => $paiementId,
                'echeance' => $echeanceId,
                'inscription' => $inscriptionId,
                'montant' => Money::fromCents($affectation),
            ]);

            $dejaAffecte[$echeanceId] = $regleCents + $affectation;
            $restant -= $affectation;
            $totalAffecte += $affectation;
        }

        return $totalAffecte;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function annuler(int $paiementId, string $motif, int $utilisateurId): array
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            return ['success' => false, 'message' => 'Un motif d\'annulation d\'au moins 5 caractères est obligatoire.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT * FROM paiements WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $paiementId]);
            $paiement = $lock->fetch();

            if ($paiement === false) {
                throw new DomainException('Paiement introuvable.');
            }

            if ($paiement['statut'] !== 'valide') {
                throw new DomainException('Ce paiement est déjà annulé.');
            }

            $update = $pdo->prepare(
                "UPDATE paiements
                 SET statut = 'annule', motif_annulation = :motif, annule_par = :utilisateur, date_annulation = NOW()
                 WHERE id = :id"
            );
            $update->execute(['motif' => $motif, 'utilisateur' => $utilisateurId, 'id' => $paiementId]);

            AuditService::log('paiement_annulation', 'paiement', $paiementId, [
                'inscription_id' => (int) $paiement['inscription_id'],
                'montant' => $paiement['montant'],
                'motif' => $motif,
            ], $utilisateurId);

            $pdo->commit();

            return ['success' => true, 'message' => 'Paiement annulé. Il reste visible dans l\'historique et n\'est plus comptabilisé.'];
        } catch (DomainException $exception) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            Database::log('Erreur annulation paiement : ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Une erreur technique a empêché l\'annulation.'];
        }
    }

    private static function normaliserDate(string $valeur): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $valeur);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function modes(): array
    {
        return [
            'especes' => 'Espèces',
            'orange_money' => 'Orange Money',
            'mtn_mobile_money' => 'MTN Mobile Money',
            'virement' => 'Virement',
            'autre' => 'Autre',
        ];
    }
}

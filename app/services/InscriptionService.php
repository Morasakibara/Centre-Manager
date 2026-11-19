<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Money;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Inscription;
use DomainException;
use PDOException;

final class InscriptionService
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, array{libelle?: string, montant: string, date_echeance: string}> $echeancier
     * @return array{success: bool, message: string, inscription_id?: int}
     */
    public static function creer(array $data, array $echeancier = []): array
    {
        $etudiant = Etudiant::find((int) ($data['etudiant_id'] ?? 0));
        if ($etudiant === null) {
            return ['success' => false, 'message' => 'Étudiant introuvable.'];
        }
        if ($etudiant['statut'] !== 'actif') {
            return ['success' => false, 'message' => 'Seul un étudiant actif peut être inscrit.'];
        }

        $formation = Formation::find((int) ($data['formation_id'] ?? 0));
        if ($formation === null) {
            return ['success' => false, 'message' => 'Formation introuvable.'];
        }
        if ($formation['statut'] !== 'actif') {
            return ['success' => false, 'message' => 'Cette formation est inactive : impossible de créer une nouvelle inscription.'];
        }

        $annee = AnneeAcademique::find((int) ($data['annee_academique_id'] ?? 0));
        if ($annee === null) {
            return ['success' => false, 'message' => 'Année académique introuvable.'];
        }
        if ($annee['statut'] !== 'ouverte') {
            return ['success' => false, 'message' => 'Cette année académique est clôturée : aucune nouvelle inscription possible.'];
        }

        $date = (string) ($data['date_inscription'] ?? '');
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dateObj === false || $dateObj->format('Y-m-d') !== $date) {
            return ['success' => false, 'message' => 'La date d\'inscription est invalide.'];
        }

        $mode = (string) ($data['mode_paiement'] ?? 'libre');
        if (!in_array($mode, ['libre', 'tranches'], true)) {
            return ['success' => false, 'message' => 'Mode de paiement invalide.'];
        }

        $fraisFormationCents = Money::toCents($formation['frais_formation']);
        $montantSaisi = $data['montant_total'] ?? null;
        $montantCents = ($montantSaisi === null || $montantSaisi === '')
            ? $fraisFormationCents
            : Money::toCents($montantSaisi);

        if ($montantCents <= 0) {
            return ['success' => false, 'message' => 'Le montant convenu doit être strictement positif.'];
        }

        if ($montantCents !== $fraisFormationCents && !Auth::isAdmin()) {
            return ['success' => false, 'message' => 'Seul un administrateur peut ajuster le montant convenu par rapport au tarif de la formation.'];
        }

        try {
            $lignes = $mode === 'tranches'
                ? EcheanceService::normaliser($echeancier, $montantCents)
                : [];
        } catch (DomainException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $exists = $pdo->prepare(
                'SELECT COUNT(*) FROM inscriptions WHERE etudiant_id = :etudiant AND annee_academique_id = :annee'
            );
            $exists->execute([
                'etudiant' => (int) $data['etudiant_id'],
                'annee' => (int) $data['annee_academique_id'],
            ]);
            if ((int) $exists->fetchColumn() > 0) {
                throw new DomainException('Cet étudiant possède déjà une inscription pour cette année académique.');
            }

            $inscriptionId = Inscription::create([
                'etudiant_id' => (int) $data['etudiant_id'],
                'formation_id' => (int) $data['formation_id'],
                'annee_academique_id' => (int) $data['annee_academique_id'],
                'date_inscription' => $date,
                'montant_total' => Money::fromCents($montantCents),
                'montant_initial' => Money::fromCents($fraisFormationCents),
                'mode_paiement' => $mode,
                'observation' => (string) ($data['observation'] ?? ''),
                'created_by' => Auth::id(),
            ]);

            if ($lignes !== []) {
                EcheanceService::inserer($inscriptionId, $lignes);
            }

            $details = [
                'etudiant_id' => (int) $data['etudiant_id'],
                'formation_id' => (int) $data['formation_id'],
                'annee_academique_id' => (int) $data['annee_academique_id'],
                'montant_total' => Money::fromCents($montantCents),
                'montant_initial' => Money::fromCents($fraisFormationCents),
                'mode_paiement' => $mode,
                'nb_echeances' => count($lignes),
            ];
            if ($montantCents !== $fraisFormationCents) {
                $details['ajustement_montant'] = true;
            }

            AuditService::log('inscription_creation', 'inscription', $inscriptionId, $details);

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Inscription enregistrée avec succès.',
                'inscription_id' => $inscriptionId,
            ];
        } catch (DomainException $exception) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        } catch (PDOException $exception) {
            $pdo->rollBack();
            if ($exception->getCode() === '23000') {
                return ['success' => false, 'message' => 'Une inscription existe déjà pour cet étudiant et cette année académique.'];
            }
            Database::log('Erreur creation inscription : ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Une erreur technique a empêché la création de l\'inscription.'];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function annuler(int $inscriptionId, string $motif): array
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            return ['success' => false, 'message' => 'Un motif d\'annulation d\'au moins 5 caractères est requis.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT * FROM inscriptions WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $inscriptionId]);
            $inscription = $lock->fetch();

            if ($inscription === false) {
                throw new DomainException('Inscription introuvable.');
            }
            if ($inscription['statut'] === 'annulee') {
                throw new DomainException('Cette inscription est déjà annulée.');
            }

            $paiements = $pdo->prepare(
                "SELECT COUNT(*) FROM paiements WHERE inscription_id = :id AND statut = 'valide'"
            );
            $paiements->execute(['id' => $inscriptionId]);
            if ((int) $paiements->fetchColumn() > 0) {
                throw new DomainException(
                    'Cette inscription possède des paiements validés. Annulez d\'abord les paiements concernés, puis l\'inscription.'
                );
            }

            Inscription::changerStatut($inscriptionId, 'annulee', $motif);
            AuditService::log('inscription_annulation', 'inscription', $inscriptionId, ['motif' => $motif]);

            $pdo->commit();

            return ['success' => true, 'message' => 'Inscription annulée. Le dossier reste consultable dans l\'historique.'];
        } catch (DomainException $exception) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            Database::log('Erreur annulation inscription : ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Une erreur technique a empêché l\'annulation.'];
        }
    }

    /**
     * Cloture une inscription (la dette eventuelle est conservee).
     *
     * @return array{success: bool, message: string}
     */
    public static function terminer(int $inscriptionId, string $motif = ''): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT * FROM inscriptions WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $inscriptionId]);
            $inscription = $lock->fetch();

            if ($inscription === false) {
                throw new DomainException('Inscription introuvable.');
            }
            if ($inscription['statut'] !== 'active') {
                throw new DomainException('Seule une inscription active peut être clôturée.');
            }

            Inscription::changerStatut($inscriptionId, 'terminee');
            AuditService::log('inscription_cloture', 'inscription', $inscriptionId, [
                'motif' => $motif,
                'solde_conserve' => true,
            ]);

            $pdo->commit();

            return ['success' => true, 'message' => 'Inscription clôturée. Le solde éventuel reste dû et tracé.'];
        } catch (DomainException $exception) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            Database::log('Erreur cloture inscription : ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Une erreur technique a empêché la clôture.'];
        }
    }
}

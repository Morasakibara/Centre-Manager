<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Models\Echeance;
use DomainException;

final class EcheanceService
{
    /**
     * Valide un echeancier et retourne les lignes normalisees.
     *
     * @param array<int, array{libelle?: string, montant: string|float, date_echeance: string}> $lignes
     * @return array<int, array{libelle: string, montant: string, date_echeance: string}>
     */
    public static function normaliser(array $lignes, int $montantTotalCents): array
    {
        $resultat = [];
        $somme = 0;

        foreach ($lignes as $index => $ligne) {
            $montant = Money::toCents($ligne['montant'] ?? 0);
            $date = (string) ($ligne['date_echeance'] ?? '');

            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj === false || $dateObj->format('Y-m-d') !== $date) {
                throw new DomainException(sprintf('La date de l\'échéance %d est invalide.', $index + 1));
            }

            if ($montant <= 0) {
                throw new DomainException(sprintf('Le montant de l\'échéance %d doit être strictement positif.', $index + 1));
            }

            $somme += $montant;
            $resultat[] = [
                'libelle' => trim((string) ($ligne['libelle'] ?? '')) ?: ('Tranche ' . ($index + 1)),
                'montant' => Money::fromCents($montant),
                'date_echeance' => $date,
            ];
        }

        if ($resultat === []) {
            throw new DomainException('Un échéancier doit contenir au moins une échéance.');
        }

        if ($somme !== $montantTotalCents) {
            throw new DomainException(sprintf(
                'La somme des échéances (%s %s) doit être égale au montant total de l\'inscription (%s %s).',
                number_format($somme / 100, 0, ',', ' '),
                (string) config('devise', 'XAF'),
                number_format($montantTotalCents / 100, 0, ',', ' '),
                (string) config('devise', 'XAF')
            ));
        }

        return $resultat;
    }

    /**
     * @param array<int, array{libelle: string, montant: string, date_echeance: string}> $lignes
     */
    public static function inserer(int $inscriptionId, array $lignes): void
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO echeances (inscription_id, libelle, montant_attendu, date_echeance)
             VALUES (:inscription, :libelle, :montant, :date)'
        );

        foreach ($lignes as $ligne) {
            $statement->execute([
                'inscription' => $inscriptionId,
                'libelle' => $ligne['libelle'],
                'montant' => $ligne['montant'],
                'date' => $ligne['date_echeance'],
            ]);
        }
    }

    /**
     * Ajoute une échéance a une inscription existante, en verifiant
     * que le total de l'echeancier ne depasse pas le montant convenu.
     */
    public static function ajouter(int $inscriptionId, string $libelle, mixed $montant, string $date): void
    {
        $montantCents = Money::toCents($montant);
        if ($montantCents <= 0) {
            throw new DomainException('Le montant de l\'échéance doit être strictement positif.');
        }

        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dateObj === false || $dateObj->format('Y-m-d') !== $date) {
            throw new DomainException('La date de l\'échéance est invalide.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT montant_total FROM inscriptions WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $inscriptionId]);
            $montantTotal = $lock->fetchColumn();
            if ($montantTotal === false) {
                throw new DomainException('Inscription introuvable.');
            }

            $somme = $pdo->prepare('SELECT COALESCE(SUM(montant_attendu), 0) FROM echeances WHERE inscription_id = :id');
            $somme->execute(['id' => $inscriptionId]);
            $dejaPlanifie = Money::toCents($somme->fetchColumn());

            if ($dejaPlanifie + $montantCents > Money::toCents($montantTotal)) {
                throw new DomainException('Le total de l\'échéancier dépasserait le montant convenu de l\'inscription.');
            }

            \App\Models\Echeance::create($inscriptionId, $libelle !== '' ? $libelle : 'Échéance', Money::fromCents($montantCents), $date);
            AuditService::log('echeance_creation', 'echeance', null, [
                'inscription_id' => $inscriptionId,
                'montant' => Money::fromCents($montantCents),
                'date_echeance' => $date,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function supprimer(int $echeanceId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT * FROM echeances WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $echeanceId]);
            $echeance = $lock->fetch();
            if ($echeance === false) {
                throw new DomainException('Échéance introuvable.');
            }

            $affectations = $pdo->prepare('SELECT COUNT(*) FROM affectations_paiements WHERE echeance_id = :id');
            $affectations->execute(['id' => $echeanceId]);
            if ((int) $affectations->fetchColumn() > 0) {
                throw new DomainException('Cette échéance a reçu des paiements : elle ne peut pas être supprimée.');
            }

            Echeance::supprimer($echeanceId);
            AuditService::log('echeance_suppression', 'echeance', $echeanceId, [
                'inscription_id' => (int) $echeance['inscription_id'],
                'montant' => $echeance['montant_attendu'],
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
